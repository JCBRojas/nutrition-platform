<?php

namespace App\Services;

use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Plan de la institución y pagos manuales del premium.
 *
 * - Premium = existe un periodo en `subscriptions` vigente ahora. Al vencer, vuelve solo al básico.
 * - Las funciones de config('subscriptions.premium_features') exigen premium, salvo para el rol admin.
 * - Aprobar un pago agrega un periodo que empieza al terminar el vigente (renovar antes no pierde días).
 */
class SubscriptionService
{
    public const DISK = 'local';

    /** Memoria por petición: el menú consulta el plan varias veces */
    private ?Subscription $current = null;

    private bool $loaded = false;

    public function current(): ?Subscription
    {
        if (! $this->loaded) {
            $this->current = Subscription::current()->orderByDesc('ends_at')->first();
            $this->loaded = true;
        }

        return $this->current;
    }

    public function forget(): void
    {
        $this->loaded = false;
        $this->current = null;
    }

    public function isPremium(): bool
    {
        return $this->current() !== null;
    }

    /** Fin del último periodo pagado (incluye periodos futuros ya aprobados) */
    public function paidUntil(): ?CarbonImmutable
    {
        $end = Subscription::where('ends_at', '>', now())->max('ends_at');

        return $end ? CarbonImmutable::parse($end) : null;
    }

    public function planKey(): string
    {
        return $this->isPremium() ? 'premium' : 'basic';
    }

    public function hasFeature(string $feature, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! array_key_exists($feature, config('subscriptions.premium_features'))) {
            return true; // no es exclusiva del premium
        }

        return ($user && $user->hasRole(UserService::ADMIN_ROLE)) || $this->isPremium();
    }

    /** Vence en pocos días y no hay un periodo siguiente ya pagado */
    public function renewalDue(): bool
    {
        $until = $this->paidUntil();

        return $until !== null && $until->lte(now()->addDays(config('subscriptions.renewal_notice_days')));
    }

    /* ===================== PRECIOS ===================== */

    /** @return array<string, float> periodo => precio */
    public function prices(): array
    {
        $saved = PlanPrice::pluck('price', 'period')->map(fn ($p) => (float) $p)->all();

        return collect(config('subscriptions.periods'))
            ->mapWithKeys(fn ($_, $period) => [$period => $saved[$period] ?? (float) config("subscriptions.default_prices.{$period}")])
            ->all();
    }

    public function setPrice(string $period, float $price, int $userId): void
    {
        $this->ensurePeriod($period);
        $before = $this->prices()[$period];

        PlanPrice::updateOrCreate(['period' => $period], ['price' => $price, 'updated_by' => $userId]);
        AuditLogger::log('plan_price_updated', 'Precio del premium '.mb_strtolower(config("subscriptions.periods.{$period}.name")).': '.number_format($price, 0, ',', '.'), null, ['period' => $period, 'before' => $before, 'after' => $price]);
    }

    /* ===================== PAGOS ===================== */

    public function submitPayment(User $user, string $period, string $method, string $reference, UploadedFile $receipt): SubscriptionPayment
    {
        $this->ensurePeriod($period);

        if (! array_key_exists($method, config('subscriptions.payment_methods'))) {
            throw ValidationException::withMessages(['method' => 'Elige un método de pago válido.']);
        }

        if (SubscriptionPayment::where('status', SubscriptionPayment::PENDING)->exists()) {
            throw ValidationException::withMessages(['payment' => 'Ya hay un pago pendiente de revisión. Espera a que el administrador lo revise.']);
        }

        $amount = $this->prices()[$period];
        $path = $receipt->store('subscription-receipts', self::DISK);

        $payment = SubscriptionPayment::create([
            'period' => $period,
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'receipt_path' => $path,
            'status' => SubscriptionPayment::PENDING,
            'submitted_by' => $user->id,
        ]);

        AuditLogger::log('payment_submitted', "Reportó pago del premium {$payment->periodLabel()} por {$payment->methodLabel()}", $payment, ['amount' => $amount, 'reference' => $reference]);

        return $payment;
    }

    public function approve(SubscriptionPayment $payment, User $admin, ?string $notes = null): Subscription
    {
        return DB::transaction(function () use ($payment, $admin, $notes) {
            $payment = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $this->ensurePending($payment);

            $payment->update(['status' => SubscriptionPayment::APPROVED, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'review_notes' => $notes]);
            $subscription = $this->addPeriod($payment->period, $admin->id, $payment->id, $notes);

            AuditLogger::log('payment_approved', "Aprobó el pago del premium {$payment->periodLabel()}; vigente hasta {$subscription->ends_at->format('d/m/Y')}", $payment, ['subscription_id' => $subscription->id]);

            return $subscription;
        });
    }

    public function reject(SubscriptionPayment $payment, User $admin, string $reason): void
    {
        $this->ensurePending($payment);

        $payment->update(['status' => SubscriptionPayment::REJECTED, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'review_notes' => $reason]);
        AuditLogger::log('payment_rejected', "Rechazó el pago del premium {$payment->periodLabel()}", $payment, ['reason' => $reason]);
    }

    /** Activación manual por el admin (cortesía, prueba o pago recibido por otro medio) */
    public function grant(string $period, User $admin, string $notes): Subscription
    {
        $subscription = $this->addPeriod($period, $admin->id, null, $notes);
        AuditLogger::log('subscription_granted', "Activó premium {$subscription->periodLabel()} sin pago; vigente hasta {$subscription->ends_at->format('d/m/Y')}", $subscription, ['notes' => $notes]);

        return $subscription;
    }

    public function receiptDownload(SubscriptionPayment $payment)
    {
        abort_unless($payment->receipt_path && Storage::disk(self::DISK)->exists($payment->receipt_path), 404);

        $extension = pathinfo($payment->receipt_path, PATHINFO_EXTENSION);

        return Storage::disk(self::DISK)->download($payment->receipt_path, "comprobante-pago-{$payment->id}.{$extension}");
    }

    private function addPeriod(string $period, int $userId, ?int $paymentId, ?string $notes): Subscription
    {
        $this->ensurePeriod($period);
        $start = $this->paidUntil() ?? CarbonImmutable::now();

        $subscription = Subscription::create([
            'plan' => 'premium',
            'period' => $period,
            'starts_at' => $start,
            'ends_at' => $start->addMonthsNoOverflow(config("subscriptions.periods.{$period}.months")),
            'payment_id' => $paymentId,
            'activated_by' => $userId,
            'notes' => $notes,
        ]);
        $this->forget();

        return $subscription;
    }

    private function ensurePeriod(string $period): void
    {
        if (! array_key_exists($period, config('subscriptions.periods'))) {
            throw ValidationException::withMessages(['period' => 'Elige un periodo válido.']);
        }
    }

    private function ensurePending(SubscriptionPayment $payment): void
    {
        if (! $payment->isPending()) {
            throw ValidationException::withMessages(['payment' => 'Este pago ya fue revisado.']);
        }
    }
}
