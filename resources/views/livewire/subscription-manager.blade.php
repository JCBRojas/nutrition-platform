@php
    $money = fn ($v) => '$'.number_format((float) $v, 0, ',', '.');
    $date = fn ($d) => $d?->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    $annualSaving = max(0, $planPrices['monthly'] * 12 - $planPrices['annual']);
    $featureList = mb_strtolower(\Illuminate\Support\Arr::join(array_values($features), ', ', ' y '));
@endphp
<div class="content" id="page-suscripcion">
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

        <div class="sb-head mb-4">
            <div>
                <h2 class="card-title">Suscripción</h2>
                <p class="sb-help">Plan de la institución. El plan Premium habilita {{ $featureList }}.</p>
            </div>
            <span @class(['sb-plan-badge', 'is-premium' => $isPremium])>
                Plan {{ $isPremium ? 'Premium' : 'Básico' }}
            </span>
        </div>

        @if (session('premium_required'))
            <div class="danger-banner mb-4" role="alert">
                {{ session('premium_required') }}
                @unless ($canManage || $canApprove)
                    Pide al administrador o al jefe de nutrición que active el plan.
                @endunless
            </div>
        @endif
        @if ($notice)
            <div @class(['info-banner' => ! $noticeError, 'danger-banner' => $noticeError, 'mb-4']) role="status">{{ $notice }}</div>
        @endif
        @if ($isPremium && $renewalDue)
            <div class="danger-banner mb-4" role="status">El plan Premium vence el {{ $date($paidUntil) }}. Renueva para no perder el acceso a {{ $featureList }}.</div>
        @endif

        {{-- ===================== PLANES ===================== --}}
        <div class="sb-plans mb-6">
            <section @class(['sb-plan', 'is-current' => ! $isPremium]) aria-labelledby="plan-basic">
                <div class="sb-plan-top">
                    <h3 id="plan-basic">{{ $plans['basic']['name'] }}</h3>
                    @unless ($isPremium) <span class="tag sb-current">Plan actual</span> @endunless
                </div>
                <div class="sb-price">Gratis</div>
                <p class="sb-desc">{{ $plans['basic']['description'] }}</p>
                <ul class="sb-list">
                    <li>Registro y versiones de dietas</li>
                    <li>Despacho de cocina y recepción</li>
                    <li>«Continuar dieta» y cierre diario</li>
                    <li>Reporte nutricional y Excel del mes</li>
                </ul>
            </section>

            <section @class(['sb-plan', 'sb-plan-premium', 'is-current' => $isPremium]) aria-labelledby="plan-premium">
                <div class="sb-plan-top">
                    <h3 id="plan-premium">{{ $plans['premium']['name'] }}</h3>
                    @if ($isPremium) <span class="tag sb-current">Plan actual</span> @endif
                </div>
                <div class="sb-price">{{ $money($planPrices['monthly']) }} <small>/ mes</small></div>
                <div class="sb-price-alt">o {{ $money($planPrices['annual']) }} al año @if ($annualSaving > 0) (ahorras {{ $money($annualSaving) }}) @endif</div>
                <p class="sb-desc">{{ $plans['premium']['description'] }}</p>
                <ul class="sb-list sb-list-premium">
                    <li>Todo lo del plan Básico</li>
                    @foreach ($features as $label)
                        <li><strong>{{ $label }}</strong></li>
                    @endforeach
                </ul>
                @if ($isPremium)
                    <p class="sb-until">Vigente hasta el <strong>{{ $date($paidUntil) }}</strong>.</p>
                @endif
            </section>
        </div>

        {{-- ===================== PAGAR ===================== --}}
        @if ($canManage)
            <section class="card sb-section mb-6" aria-labelledby="pay-title">
                <h3 id="pay-title" class="sb-title">{{ $isPremium ? 'Renovar el plan Premium' : 'Pagar el plan Premium' }}</h3>

                @if ($pendingPayment)
                    <div class="info-banner" role="status">
                        Hay un pago pendiente de revisión: {{ $pendingPayment->periodLabel() }} por {{ $money($pendingPayment->amount) }}
                        ({{ $pendingPayment->methodLabel() }}, ref. {{ $pendingPayment->reference }}), reportado el {{ $pendingPayment->created_at->format('d/m/Y h:i a') }}.
                        El plan se activa cuando el administrador lo apruebe.
                    </div>
                @else
                    <p class="sb-help">1. Elige el periodo y el método. 2. Paga con los datos indicados. 3. Escribe la referencia y adjunta el comprobante. El administrador lo revisa y activa el plan.</p>

                    <form wire:submit="submitPayment" class="sb-form">
                        <fieldset class="sb-field">
                            <legend>Periodo</legend>
                            <div class="sb-options">
                                @foreach ($periods as $key => $p)
                                    <label @class(['sb-option', 'is-selected' => $period === $key])>
                                        <input type="radio" wire:model.live="period" value="{{ $key }}">
                                        <span><strong>{{ $p['name'] }}</strong> · {{ $money($planPrices[$key]) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="sb-field">
                            <legend>Método de pago</legend>
                            <div class="sb-options">
                                @foreach ($methods as $key => $m)
                                    <label @class(['sb-option', 'is-selected' => $method === $key])>
                                        <input type="radio" wire:model.live="method" value="{{ $key }}">
                                        <span>{{ $m['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="sb-instructions" role="note">
                                <strong>Cómo pagar · {{ $methods[$method]['name'] ?? '' }}:</strong>
                                {{ $methods[$method]['instructions'] ?? '' }}
                                <div>Valor a pagar: <strong>{{ $money($planPrices[$period] ?? 0) }}</strong></div>
                            </div>
                        </fieldset>

                        <div class="sb-grid">
                            <label class="sb-field">
                                <span class="sb-label">Referencia o número de transacción</span>
                                <input type="text" class="filter-input" wire:model="reference" maxlength="100" placeholder="Ej.: 0012345678">
                                @error('reference') <span class="sb-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="sb-field">
                                <span class="sb-label">Comprobante (JPG, PNG o PDF, máx. 5 MB)</span>
                                <input type="file" class="filter-input" wire:model="receipt" accept=".jpg,.jpeg,.png,.pdf">
                                <span class="sb-help" wire:loading wire:target="receipt">Subiendo archivo…</span>
                                @error('receipt') <span class="sb-error">{{ $message }}</span> @enderror
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="submitPayment,receipt">Reportar pago</button>
                    </form>
                @endif
            </section>
        @elseif (! $canApprove)
            <p class="sb-help mb-6">Para activar o renovar el plan Premium, contacta al administrador o al jefe de nutrición.</p>
        @endif

        {{-- ===================== REVISIÓN (ADMIN) ===================== --}}
        @if ($canApprove)
            <section class="card sb-section mb-6" aria-labelledby="review-title">
                <h3 id="review-title" class="sb-title">Pagos por revisar</h3>
                @php $pending = $payments->where('status', \App\Models\SubscriptionPayment::PENDING); @endphp
                @forelse ($pending as $p)
                    <div class="sb-review" wire:key="review-{{ $p->id }}">
                        <div>
                            <strong>{{ $p->periodLabel() }} · {{ $money($p->amount) }}</strong> por {{ $p->methodLabel() }}
                            <div class="sb-meta">Ref. {{ $p->reference }} · {{ $p->submittedBy?->name }} · {{ $p->created_at->format('d/m/Y h:i a') }}</div>
                            <a href="{{ route('subscription.receipt', $p) }}" class="sb-link" target="_blank" rel="noopener">Ver comprobante</a>
                        </div>
                        @if ($rejectingId === $p->id)
                            <form wire:submit="reject" class="sb-reject">
                                <input type="text" class="filter-input" wire:model="rejectReason" placeholder="Motivo del rechazo" maxlength="500">
                                @error('rejectReason') <span class="sb-error">{{ $message }}</span> @enderror
                                <div class="sb-actions">
                                    <button type="submit" class="btn btn-sm sb-danger">Rechazar</button>
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('rejectingId', null)">Cancelar</button>
                                </div>
                            </form>
                        @else
                            <div class="sb-actions">
                                <button type="button" class="btn btn-success btn-sm" wire:click="approve({{ $p->id }})"
                                    wire:confirm="¿Aprobar el pago de {{ $money($p->amount) }}? Se activa o extiende el plan Premium {{ mb_strtolower($p->periodLabel()) }}.">Aprobar</button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="startReject({{ $p->id }})">Rechazar</button>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="sb-help">No hay pagos pendientes.</p>
                @endforelse
            </section>

            <div class="sb-admin-grid mb-6">
                <section class="card sb-section" aria-labelledby="prices-title">
                    <h3 id="prices-title" class="sb-title">Precios del plan Premium (COP)</h3>
                    <form wire:submit="savePrices" class="sb-form">
                        @foreach ($periods as $key => $p)
                            <label class="sb-field">
                                <span class="sb-label">{{ $p['name'] }}</span>
                                <input type="text" inputmode="numeric" class="filter-input" wire:model="prices.{{ $key }}">
                                @error("prices.{$key}") <span class="sb-error">{{ $message }}</span> @enderror
                            </label>
                        @endforeach
                        <p class="sb-help">Los pagos ya reportados conservan el valor con el que se reportaron.</p>
                        <button type="submit" class="btn btn-primary btn-sm">Guardar precios</button>
                    </form>
                </section>

                <section class="card sb-section" aria-labelledby="grant-title">
                    <h3 id="grant-title" class="sb-title">Activar Premium sin pago</h3>
                    <form wire:submit="grant" class="sb-form">
                        <label class="sb-field">
                            <span class="sb-label">Periodo</span>
                            <select class="filter-select" wire:model="grantPeriod">
                                @foreach ($periods as $key => $p)
                                    <option value="{{ $key }}">{{ $p['name'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="sb-field">
                            <span class="sb-label">Motivo</span>
                            <input type="text" class="filter-input" wire:model="grantNotes" maxlength="500" placeholder="Periodo de prueba, cortesía, pago por otro medio…">
                            @error('grantNotes') <span class="sb-error">{{ $message }}</span> @enderror
                        </label>
                        <button type="submit" class="btn btn-ghost btn-sm" wire:confirm="¿Activar el plan Premium sin un pago registrado? Queda en la auditoría.">Activar</button>
                    </form>
                </section>
            </div>
        @endif

        {{-- ===================== HISTORIAL ===================== --}}
        @if ($canManage || $canApprove)
            <section class="card" aria-labelledby="history-title">
                <h3 id="history-title" class="sb-title sb-pad">Historial de pagos</h3>
                <div class="table-responsive">
                    <table class="table-wrap">
                        <thead>
                            <tr><th>Fecha</th><th>Periodo</th><th>Valor</th><th>Método</th><th>Referencia</th><th>Estado</th><th>Revisión</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $p)
                                <tr wire:key="pay-{{ $p->id }}">
                                    <td class="sb-nowrap">{{ $p->created_at->format('d/m/Y') }}<div class="sb-meta">{{ $p->submittedBy?->name }}</div></td>
                                    <td>{{ $p->periodLabel() }}</td>
                                    <td class="sb-nowrap">{{ $money($p->amount) }}</td>
                                    <td>{{ $p->methodLabel() }}</td>
                                    <td>{{ $p->reference }}</td>
                                    <td><span @class(['tag', 'sb-st-'.$p->status])>{{ $p->statusLabel() }}</span></td>
                                    <td>
                                        @if ($p->reviewed_at)
                                            {{ $p->reviewedBy?->name }} · {{ $p->reviewed_at->format('d/m/Y') }}
                                            @if ($p->review_notes) <div class="sb-meta">{{ $p->review_notes }}</div> @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty">Aún no hay pagos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>

    <style>
        .sb-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
        .sb-help { margin: 2px 0 0; font-size: 13px; color: var(--text-muted); }
        .sb-plan-badge { padding: 5px 12px; border-radius: 999px; font-size: 12.5px; font-weight: 700; background: var(--surface2, #f0ede8); color: var(--text); }
        .sb-plan-badge.is-premium { background: #fef3c7; color: #92400e; }
        .sb-plans { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
        .sb-plan { border: 1px solid var(--border, #e2ddd6); border-radius: 12px; padding: 18px; background: var(--surface, #fff); }
        .sb-plan.is-current { border: 2px solid var(--accent, #2a7d6f); }
        .sb-plan-premium { background: linear-gradient(180deg, #fffbeb 0, var(--surface, #fff) 60%); }
        .sb-plan-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .sb-plan h3 { margin: 0; font-size: 17px; font-weight: 700; }
        .sb-current { background: var(--accent-light, #e6f4f1); color: var(--accent, #2a7d6f); font-weight: 600; }
        .sb-price { margin-top: 8px; font-size: 26px; font-weight: 700; }
        .sb-price small { font-size: 13px; font-weight: 500; color: var(--text-muted); }
        .sb-price-alt, .sb-meta { font-size: 12.5px; color: var(--text-muted); }
        .sb-desc { font-size: 13.5px; margin: 10px 0 6px; }
        .sb-list { margin: 0; padding-left: 18px; list-style: disc; font-size: 13.5px; line-height: 1.7; }
        .sb-until { margin: 10px 0 0; font-size: 13px; }
        .sb-section { padding: 16px 18px; }
        .sb-title { margin: 0 0 8px; font-size: 15px; font-weight: 700; }
        .sb-pad { padding: 14px 16px 0; }
        .sb-form { display: grid; gap: 12px; margin-top: 10px; }
        .sb-field { display: grid; gap: 6px; border: 0; padding: 0; margin: 0; min-width: 0; }
        .sb-field legend, .sb-label { font-size: 12.5px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; }
        .sb-options { display: flex; flex-wrap: wrap; gap: 8px; }
        .sb-option { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border: 1px solid var(--border, #e2ddd6); border-radius: 8px; cursor: pointer; font-size: 13.5px; }
        .sb-option.is-selected { border-color: var(--accent, #2a7d6f); background: var(--accent-light, #e6f4f1); }
        .sb-option input { accent-color: var(--accent, #2a7d6f); }
        .sb-instructions { margin-top: 6px; padding: 10px 12px; border-radius: 8px; background: var(--surface2, #f6f4f1); font-size: 13px; line-height: 1.55; }
        .sb-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
        .sb-error { color: #b42318; font-size: 12.5px; }
        .sb-review { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 0; border-top: 1px solid var(--border, #e2ddd6); font-size: 13.5px; }
        .sb-review:first-of-type { border-top: 0; }
        .sb-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .sb-reject { display: grid; gap: 6px; min-width: min(320px, 100%); }
        .sb-danger { background: #b42318; color: #fff; border: 0; }
        .sb-link { font-size: 12.5px; color: var(--blue, #2563eb); text-decoration: underline; }
        .sb-admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; }
        .sb-nowrap { white-space: nowrap; }
        .sb-st-pending { background: #fef3c7; color: #92400e; }
        .sb-st-approved { background: #ccfbf1; color: #0f766e; }
        .sb-st-rejected { background: #fee2e2; color: #b42318; }
    </style>
</div>
