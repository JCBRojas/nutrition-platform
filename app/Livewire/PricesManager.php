<?php

namespace App\Livewire;

use App\Services\AuditLogger;
use App\Services\BillingService;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class PricesManager extends Component
{
    /** Formulario de nuevo precio (fila tipo × servicio en edición) */
    public string $editType = '';

    public string $editService = '';

    public string $price = '';

    public string $validFrom = '';

    /** SCRUM-26: un precio de $ 0 solo se guarda con confirmación explícita */
    public bool $confirmZero = false;

    /** Filtros de la tabla */
    #[Url(as: 'tipo', except: '')]
    public string $typeFilter = '';

    #[Url(as: 'sin_precio', except: false)]
    public bool $onlyMissing = false;

    /** Mes del valor y de la descarga (YYYY-MM) */
    #[Url(as: 'mes', except: '')]
    public string $month = '';

    /** Fila "tipo.servicio" con historial desplegado */
    public ?string $openHistory = null;

    public function mount(): void
    {
        $this->authorize('managePrices');
        $this->validFrom = now()->toDateString();
        $this->month = $this->month ?: now()->format('Y-m');
    }

    public function edit(string $type, string $service): void
    {
        $this->editType = $type;
        $this->editService = $service;
        $this->price = '';
        $this->validFrom = now()->toDateString();
        $this->confirmZero = false;
        $this->resetErrorBag();
    }

    public function save(BillingService $billing): void
    {
        $this->authorize('managePrices');

        // Acepta "15.000", "15000" o "15000,50"
        $this->price = str_replace(',', '.', str_replace(['.', ' ', '$'], '', $this->price));

        $this->withValidator(fn ($validator) => $validator->after(function ($validator) {
            if (! $validator->errors()->has('price') && is_numeric($this->price) && (float) $this->price == 0.0 && ! $this->confirmZero) {
                $validator->errors()->add('price', 'Un precio de $ 0 significa que este servicio no se cobra. Si es correcto, marca «Confirmo que no se cobra» y guarda de nuevo.');
            }
        }));

        $data = $this->validate([
            'editType' => ['required', Rule::in(array_keys(BillingService::types()))],
            'editService' => ['required', Rule::in(BillingService::types()[$this->editType]['services'] ?? [])],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'validFrom' => ['required', 'date_format:Y-m-d'],
            'confirmZero' => ['boolean'],
        ], [
            'price.numeric' => 'Escribe el precio en pesos, sin letras. Ejemplo: 15000.',
            'editService.in' => 'Ese tipo de dieta no factura este servicio.',
        ]);

        $billing->setPrice($data['editType'], $data['editService'], (float) $data['price'], CarbonImmutable::parse($data['validFrom']), auth()->id());

        $label = BillingService::types()[$data['editType']]['name'].' / '.BillingService::serviceLabel($data['editService']);
        AuditLogger::log('price_updated', "Precio de {$label}: {$data['price']} desde {$data['validFrom']}", null, [
            'company_id' => app(CurrentCompany::class)->id(), 'billing_type' => $data['editType'], 'service' => $data['editService'], 'price' => (float) $data['price'], 'valid_from' => $data['validFrom'],
            'confirmed_zero' => (float) $data['price'] == 0.0,
        ]);
        $this->cancel();
        session()->flash('success', "Precio de {$label} guardado.");
    }

    public function cancel(): void
    {
        $this->reset(['editType', 'editService', 'price', 'confirmZero']);
        $this->validFrom = now()->toDateString();
        $this->resetErrorBag();
    }

    public function toggleHistory(string $key): void
    {
        $this->openHistory = $this->openHistory === $key ? null : $key;
    }

    private function selectedMonth(): CarbonImmutable
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $this->month)
            ? CarbonImmutable::createFromFormat('!Y-m', $this->month)
            : CarbonImmutable::now()->startOfMonth();

        return $month->isAfter(now()) ? CarbonImmutable::now()->startOfMonth() : $month;
    }

    public function render(BillingService $billing)
    {
        $current = $billing->pricesAt(now());
        $rows = collect(BillingService::rows())
            ->when($this->typeFilter !== '', fn ($c) => $c->where('type', $this->typeFilter))
            ->when($this->onlyMissing, fn ($c) => $c->reject(fn ($r) => $current->has("{$r['type']}.{$r['service']}")))
            ->groupBy('type');

        $month = $this->selectedMonth();
        $monthly = $billing->monthly($month);

        [$historyType, $historyService] = $this->openHistory ? explode('.', $this->openHistory) : [null, null];

        return view('livewire.prices-manager', [
            'company' => app(CurrentCompany::class)->get(),
            'types' => BillingService::types(),
            'services' => BillingService::services(),
            'rows' => $rows,
            'current' => $current,
            'missingCount' => collect(BillingService::rows())->reject(fn ($r) => $current->has("{$r['type']}.{$r['service']}"))->count(),
            'monthly' => $monthly,
            'monthValue' => $month->format('Y-m'),
            'monthName' => ucfirst($month->locale('es')->isoFormat('MMMM YYYY')),
            'history' => $historyType ? $billing->history($historyType, $historyService) : collect(),
        ]);
    }
}
