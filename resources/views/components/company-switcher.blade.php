{{-- Selector de empresa (NUT-RN-003-09): arriba de cada módulo; solo las empresas asignadas y activas --}}
@php
    $companies = auth()->user()->selectableCompanies()->get(['companies.id', 'name']);
    $currentId = app(\App\Support\CurrentCompany::class)->id();
@endphp

<div class="company-bar" role="region" aria-label="Empresa">
    @if ($companies->isEmpty())
        <span class="company-bar__empty">No tienes empresas asignadas. Pide acceso al administrador.</span>
    @elseif ($companies->count() === 1)
        <span class="company-bar__label">Empresa</span>
        <strong class="company-bar__name">{{ $companies->first()->name }}</strong>
    @else
        <form method="POST" action="{{ route('company.switch') }}" class="company-bar__form">
            @csrf
            <label for="company-switch" class="company-bar__label">Empresa</label>
            <select id="company-switch" name="company_id" class="filter-select company-bar__select">
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected($company->id === $currentId)>{{ $company->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-ghost btn-sm">Cambiar empresa</button>
        </form>
    @endif

    @if (session('company-switched'))
        <span class="company-bar__notice" role="status">{{ session('company-switched') }}</span>
    @endif
</div>

<style>
    .company-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin: 0 0 14px; padding: 10px 14px;
        background: var(--color-superficie); border: 1px solid var(--color-borde); border-radius: 10px; font-size: 13px; color: var(--color-texto); }
    .company-bar__form { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0; }
    .company-bar__label { color: var(--color-texto-suave); font-weight: 600; }
    .company-bar__name { color: var(--color-marca-texto); }
    .company-bar__select { min-width: 200px; max-width: 100%; }
    .company-bar__notice { color: var(--color-exito); }
    .company-bar__empty { color: var(--color-advertencia); }
</style>
