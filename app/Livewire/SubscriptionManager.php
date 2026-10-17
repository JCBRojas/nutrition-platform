<?php

namespace App\Livewire;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * /suscripcion: plan de la institución, pago manual del premium (permiso manageSubscription)
 * y revisión de pagos, precios y activación manual (permiso approvePayments, admin).
 */
#[Layout('layouts.app')]
class SubscriptionManager extends Component
{
    use WithFileUploads;

    // Reportar pago
    public string $period = 'monthly';

    public string $method = 'transferencia';

    public string $reference = '';

    public $receipt = null;

    // Revisión (admin)
    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public array $prices = [];

    public string $grantPeriod = 'monthly';

    public string $grantNotes = '';

    public ?string $notice = null;

    public bool $noticeError = false;

    public function mount(SubscriptionService $subscriptions): void
    {
        $this->prices = array_map(fn ($p) => (string) (int) $p, $subscriptions->prices());
    }

    public function submitPayment(SubscriptionService $subscriptions): void
    {
        abort_unless(auth()->user()->can('manageSubscription'), 403);

        $this->validate([
            'period' => ['required', Rule::in(array_keys(config('subscriptions.periods')))],
            'method' => ['required', Rule::in(array_keys(config('subscriptions.payment_methods')))],
            'reference' => ['required', 'string', 'max:100'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('subscriptions.receipt_max_kb')],
        ], [
            'reference.required' => 'Escribe el número de referencia o de la transacción.',
            'receipt.required' => 'Adjunta el comprobante de pago.',
            'receipt.mimes' => 'El comprobante debe ser una imagen (JPG o PNG) o un PDF.',
            'receipt.max' => 'El comprobante no debe superar 5 MB.',
        ]);

        $this->attempt(function () use ($subscriptions) {
            $subscriptions->submitPayment(auth()->user(), $this->period, $this->method, trim($this->reference), $this->receipt);
            $this->reset(['reference', 'receipt']);

            return 'Pago reportado. El administrador revisará el comprobante y activará el plan Premium.';
        });
    }

    public function approve(int $paymentId, SubscriptionService $subscriptions): void
    {
        abort_unless(auth()->user()->can('approvePayments'), 403);

        $this->attempt(function () use ($paymentId, $subscriptions) {
            $subscription = $subscriptions->approve(SubscriptionPayment::findOrFail($paymentId), auth()->user());

            return "Pago aprobado. Premium vigente hasta el {$subscription->ends_at->locale('es')->isoFormat('D [de] MMMM [de] YYYY')}.";
        });
    }

    public function startReject(int $paymentId): void
    {
        $this->rejectingId = $paymentId;
        $this->rejectReason = '';
    }

    public function reject(SubscriptionService $subscriptions): void
    {
        abort_unless(auth()->user()->can('approvePayments'), 403);
        $this->validate(['rejectReason' => ['required', 'string', 'max:500']], ['rejectReason.required' => 'Escribe el motivo del rechazo.']);

        $this->attempt(function () use ($subscriptions) {
            $subscriptions->reject(SubscriptionPayment::findOrFail($this->rejectingId), auth()->user(), trim($this->rejectReason));
            $this->reset(['rejectingId', 'rejectReason']);

            return 'Pago rechazado.';
        });
    }

    public function savePrices(SubscriptionService $subscriptions): void
    {
        abort_unless(auth()->user()->can('approvePayments'), 403);

        $this->prices = array_map(fn ($p) => str_replace(['.', ' ', '$', ','], '', (string) $p), $this->prices);
        $this->validate(
            collect(config('subscriptions.periods'))->mapWithKeys(fn ($_, $period) => ["prices.{$period}" => ['required', 'integer', 'min:1', 'max:9999999999']])->all(),
            ['prices.*.integer' => 'Escribe el precio en pesos, sin letras. Ejemplo: 250000.', 'prices.*.min' => 'El precio debe ser mayor que 0.']
        );

        $current = $subscriptions->prices();
        foreach ($this->prices as $period => $price) {
            if ((float) $price !== $current[$period]) {
                $subscriptions->setPrice($period, (float) $price, auth()->id());
            }
        }

        $this->flash('Precios del plan Premium guardados.');
    }

    public function grant(SubscriptionService $subscriptions): void
    {
        abort_unless(auth()->user()->can('approvePayments'), 403);
        $this->validate([
            'grantPeriod' => ['required', Rule::in(array_keys(config('subscriptions.periods')))],
            'grantNotes' => ['required', 'string', 'max:500'],
        ], ['grantNotes.required' => 'Escribe el motivo (por ejemplo, periodo de prueba o pago recibido por otro medio).']);

        $subscription = $subscriptions->grant($this->grantPeriod, auth()->user(), trim($this->grantNotes));
        $this->reset('grantNotes');
        $this->flash("Premium activado hasta el {$subscription->ends_at->locale('es')->isoFormat('D [de] MMMM [de] YYYY')}.");
    }

    private function attempt(callable $action): void
    {
        try {
            $this->flash($action());
        } catch (ValidationException $e) {
            $this->flash(collect($e->errors())->flatten()->first(), true);
        }
    }

    private function flash(string $message, bool $error = false): void
    {
        $this->notice = $message;
        $this->noticeError = $error;
    }

    public function render(SubscriptionService $subscriptions)
    {
        $user = auth()->user();
        $canManage = $user->can('manageSubscription');
        $canApprove = $user->can('approvePayments');

        return view('livewire.subscription-manager', [
            'current' => $subscriptions->current(),
            'paidUntil' => $subscriptions->paidUntil(),
            'isPremium' => $subscriptions->isPremium(),
            'renewalDue' => $subscriptions->renewalDue(),
            'planPrices' => $subscriptions->prices(),
            'plans' => config('subscriptions.plans'),
            'features' => config('subscriptions.premium_features'),
            'periods' => config('subscriptions.periods'),
            'methods' => config('subscriptions.payment_methods'),
            'canManage' => $canManage,
            'canApprove' => $canApprove,
            'pendingPayment' => SubscriptionPayment::where('status', SubscriptionPayment::PENDING)->with('submittedBy:id,name')->latest()->first(),
            'payments' => $canManage || $canApprove
                ? SubscriptionPayment::with(['submittedBy:id,name', 'reviewedBy:id,name'])->latest()->limit(20)->get()
                : collect(),
            'periodsHistory' => $canApprove ? Subscription::with('activatedBy:id,name')->orderByDesc('starts_at')->limit(10)->get() : collect(),
        ]);
    }
}
