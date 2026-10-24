@php
    // Etiquetas del select "Cambios en la dieta" (components/form-diet.blade.php)
    $changeLabels = ['NA' => '—', 'con_cambios' => 'Con cambios', 'aislado' => 'Se aisló', 'ya_no_aislado' => 'Ya no está aislado', 'cancelada' => 'Cancelada', 'finalizada' => 'Finalizada (alta)', 'completada' => '—', 'continuada' => '—'];
    $closedTabs = [
        'cancelled' => ['date' => 'Cancelada', 'tag' => 'tag-cancel', 'label' => 'Cancelada', 'empty' => 'No hay dietas canceladas.'],
        'finished' => ['date' => 'Alta', 'tag' => 'tag-finished', 'label' => 'Finalizada', 'empty' => 'No hay dietas finalizadas. Usa el botón de alta en una dieta activa cuando el paciente salga de la institución.'],
    ];
    $tabNames = ['active' => 'Activas', 'cancelled' => 'Canceladas', 'finished' => 'Finalizadas'];
@endphp
{{--
    Tiempo real: se refresca al llegar DietsChanged (resources/js/live.js); sin WebSocket, cada 5 s.
    Se aplaza mientras hay un modal o un selector de chips abierto (para no interrumpir una
    edición) o la pestaña está oculta, y se aplica al cerrarlo.
--}}
<div x-data x-init="window.ntLive(() => $wire.$refresh(), { fallbackMs: 5000, canRun: () => ! document.querySelector('.overlay.open, .chip-select.open') })">
    <div class="stats-grid">
        <div class="stat-card green">
            <div class="stat-label">Dietas activas</div>
            <div class="stat-value">{{ $counts['active'] }}</div>
            <div class="stat-sub">Pacientes en seguimiento</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Canceladas</div>
            <div class="stat-value">{{ $counts['cancelled'] }}</div>
            <div class="stat-sub">{{ $counts['finished'] }} finalizadas por alta</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">En aislamiento</div>
            <div class="stat-value">{{ $stats['isolated'] }}</div>
            <div class="stat-sub">Dietas activas con protocolo especial</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-label">Con cambios</div>
            <div class="stat-value">{{ $stats['changes'] }}</div>
            <div class="stat-sub">Activas cuya última versión trae cambios</div>
        </div>
    </div>

    @if ($notice)
        <div @class(['nd-notice', 'error' => $noticeError]) role="status">{{ $notice }}</div>
    @endif

    {{-- Pestañas y «Registrar Dieta» en una fila flexible (sin float): en móvil el botón ocupa su propia fila --}}
    <div class="tabs-wrapper nd-tabs mb-4">
        @foreach ($tabNames as $key => $name)
            <button type="button" @class(['btn btn-ghost tab-btn', 'active' => $tab === $key])
                aria-pressed="{{ $tab === $key ? 'true' : 'false' }}" wire:click="setTab('{{ $key }}')">
                {{ $name }} ({{ $counts[$key] }})
            </button>
        @endforeach

        @can('createDiets')
            <button type="button" class="btn btn-primary nd-register" id="btnRegistrar" onclick="openCreateModal()">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Registrar Dieta
            </button>
        @endcan
    </div>

    <div class="card">
        {{-- Búsqueda en tiempo real (vuelve a la página 1) --}}
        <div class="nd-search" role="search">
            <input type="search" class="filter-input" wire:model.live.debounce.300ms="search"
                placeholder="Buscar habitación o paciente" aria-label="Buscar dietas {{ mb_strtolower($tabNames[$tab]) }} por habitación o paciente">
            <span class="nd-search-spinner" wire:loading.delay wire:target="search" aria-hidden="true"></span>
            @if (trim($search) !== '')
                <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('search', '')">Limpiar</button>
                <span class="nd-search-info" role="status">{{ $diets->total() }} resultado(s) para «{{ trim($search) }}»</span>
            @endif
            @if ($tab === 'active')
                <x-meal-legend class="nd-legend" />
            @endif
        </div>

        <div class="table-responsive">
            @if ($tab === 'active')
                <table class="table-wrap">
                    <thead>
                        <tr>
                            <th>Hab.</th>
                            <th>Paciente</th>
                            <th>Día</th>
                            <th>Tiempo</th>
                            <th>Consistencia</th>
                            <th>Especificaciones</th>
                            <th>Observaciones</th>
                            <th>Aislam.</th>
                            <th>Cambios</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($diets as $diet)
                            <tr wire:key="active-{{ $diet->id }}-v{{ $diet->currentVersion?->version }}">
                                <td>
                                    <span class="room-badge">{{ $diet->habitation }}</span>
                                </td>

                                <td>{{ $diet->name_patient }}</td>

                                {{-- Estado del día y autorización de mañana --}}
                                @php $day = $dayStatus[$diet->id] ?? []; @endphp
                                <td>
                                    <div class="nd-day">
                                        @if ($day['completed'] ?? false)
                                            <span class="tag tag-completed" title="Se recibieron todos los tiempos de hoy">Completada</span>
                                        @elseif ($day['authorizedToday'] ?? false)
                                            <span class="tag tag-in-progress" title="{{ ($day['authorizedVia'] ?? null) === \App\Models\DietDay::VIA_AUTOMATIC ? 'Autorizada automáticamente al iniciar el día' : 'Autorizada para hoy' }}; faltan tiempos por recibir">En curso</span>
                                        @else
                                            <span class="tag tag-blocked" title="No se puede despachar hoy: no se continuó ayer">Sin autorizar hoy</span>
                                            @can('continueDiets')
                                            <button type="button" class="nd-next nd-continue" title="Autorizar hoy: cocina podrá despacharla hoy"
                                                    wire:click="authorizeToday({{ $diet->id }})" wire:loading.attr="disabled" wire:target="authorizeToday({{ $diet->id }})"
                                                    wire:confirm="{{ "¿Autorizar hoy la dieta de {$diet->name_patient} (hab. {$diet->habitation})?

Cocina podrá despacharla hoy." }}"
                                                    aria-label="Autorizar hoy la dieta de {{ $diet->name_patient }}">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                                    Autorizar hoy
                                                </button>
                                            @endcan
                                        @endif

                                        @if ($day['authorizedTomorrow'] ?? false)
                                            <span class="nd-next nd-next-ok" title="Continúa mañana: cocina puede despacharla" aria-label="Continúa mañana">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                                Mañana
                                            </span>
                                        @else
                                            @can('continueDiets')
                                            <button type="button" class="nd-next nd-continue nd-icon-only" title="Continuar dieta: autoriza el despacho de mañana"
                                                    wire:click="continueDiet({{ $diet->id }})" wire:loading.attr="disabled" wire:target="continueDiet({{ $diet->id }})"
                                                    wire:confirm="{{ "¿Continuar la dieta de {$diet->name_patient} (hab. {$diet->habitation}) mañana?

Cocina podrá despacharla el día siguiente." }}"
                                                    aria-label="Continuar dieta de {{ $diet->name_patient }} mañana">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="13 6 19 12 13 18"/><line x1="5" y1="12" x2="19" y2="12" style="display: block"/></svg>
                                                </button>
                                            @else
                                                <span class="nd-next nd-next-off" title="Mañana sin autorizar">Mañana —</span>
                                            @endcan
                                        @endif
                                    </div>
                                </td>

                                {{-- Tiempo de comida --}}
                                <td>
                                    @php
                                        $items = $diet->currentVersion->timeFood ?? [];
                                    @endphp

                                    @forelse ($items as $item)
                                        <x-meal-tag :label="$foodLabels[$item] ?? ucfirst(str_replace('_', ' ', $item))"
                                            :status="$delivery[$diet->id][str_replace('_', '', mb_strtolower($item))] ?? null" />
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>

                                {{-- Consistencia --}}
                                <td>
                                    @php
                                        $items = $diet->currentVersion->consistency ?? [];
                                    @endphp

                                    @forelse ($items as $item)
                                        <span class="tag tag-consist">
                                            {{ $consistencyLabels[$item] ?? '' }}
                                        </span>
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>

                                {{-- Especificaciones --}}
                                <td>
                                    @php
                                        $items = $diet->currentVersion->specifications ?? [];
                                    @endphp

                                    @forelse ($items as $item)
                                        <span class="tag tag-espec">
                                            {{ $specLabels[$item] ?? '' }}
                                        </span>
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>

                                <td>{{ $diet->currentVersion->observations ?? '—' }}</td>
                                <td>{{ $diet->currentVersion->isolation ?? 'No' }}</td>
                                <td>{{ $changeLabels[$diet->currentVersion->changes ?? 'NA'] ?? ucfirst(str_replace('_', ' ', $diet->currentVersion->changes)) }}</td>

                                <td class="text-right">
                                    <div class="row-actions">
                                        @can('viewDiets')
                                            @premium('history')
                                                <a href="{{ route('diets.history', ['dieta' => $diet->id]) }}"
                                                    class="btn btn-ghost btn-sm btn-icon" title="Ver historial"
                                                    aria-label="Ver historial de {{ $diet->name_patient }}">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></svg>
                                                </a>
                                            @endpremium
                                        @endcan
                                        @can('editDiets')
                                        <button type="button" class="btn btn-success btn-sm btn-icon" title="Editar dieta"
                                            aria-label="Editar dieta de {{ $diet->name_patient }}"
                                            onclick='openEditModal({{ $diet->id }}, @json($diet->editPayload(array_keys(array_filter($delivery[$diet->id] ?? [], fn ($s) => $s === 'received')))))'>
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
                                        </button>
                                        {{-- Alta y cancelación: el modal informa lo despachado sin recibir y el envío a otro lugar --}}
                                        <button type="button" class="btn btn-info btn-sm btn-icon" title="Finalizar dieta (alta del paciente)"
                                            aria-label="Finalizar dieta de {{ $diet->name_patient }} por alta"
                                            wire:click="startClose({{ $diet->id }}, 'finish')" wire:loading.attr="disabled" wire:target="startClose">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm btn-icon" title="Cancelar dieta"
                                            aria-label="Cancelar dieta de {{ $diet->name_patient }}"
                                            wire:click="startClose({{ $diet->id }}, 'cancel')" wire:loading.attr="disabled" wire:target="startClose">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                        </button>
                                        @endcan
                                    </div>
                                    @can('editDiets')
                                        {{-- wire:ignore: el refresco no toca el modal; si la dieta cambia de versión, la fila (y el modal) se recrean --}}
                                        <div class="overlay" id="modalEditOverlay{{ $diet->id }}" wire:ignore>
                                            <x-modal-edit-diets :diet="$diet" :specLabels="$specLabels"
                                                :consistencyLabels="$consistencyLabels" :foodLabels="$foodLabels" />
                                        </div>
                                    @endcan
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="empty">{{ trim($search) !== '' ? 'No hay dietas activas que coincidan con «'.trim($search).'».' : 'No hay dietas activas.' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                @php $closedTab = $closedTabs[$tab]; @endphp
                <table class="table-wrap">
                    <thead>
                        <tr>
                            <th>Hab.</th>
                            <th>Paciente</th>
                            <th>Tiempo</th>
                            <th>Consistencia</th>
                            <th>Observaciones</th>
                            <th>{{ $closedTab['date'] }}</th>
                            <th>Por</th>
                            <th class="text-right">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($diets as $diet)
                            @php $version = $diet->currentVersion; @endphp
                            <tr class="cancelled-row" wire:key="closed-{{ $diet->id }}">
                                <td><span class="room-badge">{{ $diet->habitation }}</span></td>
                                <td>{{ $diet->name_patient }}</td>
                                <td>
                                    @forelse ($version->timeFood ?? [] as $item)
                                        <span class="tag tag-tiempo">{{ $foodLabels[$item] ?? $item }}</span>
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>
                                <td>
                                    @forelse ($version->consistency ?? [] as $item)
                                        <span class="tag tag-consist">{{ $consistencyLabels[$item] ?? $item }}</span>
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>
                                <td>{{ $version->observations ?? '—' }}</td>
                                <td>{{ $diet->updated_at?->format('d/m/Y h:i a') }}</td>
                                <td>{{ $version?->updatedBy?->name ?? '—' }}</td>
                                <td class="text-right">
                                    <div class="row-actions">
                                        <span class="tag {{ $closedTab['tag'] }}">{{ $closedTab['label'] }}</span>
                                        @can('viewDiets')
                                            @premium('history')
                                                <a href="{{ route('diets.history', ['dieta' => $diet->id]) }}" class="btn btn-ghost btn-sm btn-icon"
                                                    title="Ver historial" aria-label="Ver historial de {{ $diet->name_patient }}">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></svg>
                                                </a>
                                            @endpremium
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="empty">{{ trim($search) !== '' ? 'No hay dietas que coincidan con «'.trim($search).'».' : $closedTab['empty'] }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        {{ $diets->onEachSide(1)->links('pagination.table', ['livewire' => true]) }}
    </div>

    {{-- Confirmar cancelación o alta. Mientras está abierto (.overlay.open) el panel no se refresca --}}
    @if ($closing)
        @php
            $cd = $closing['diet'];
            $sum = $closing['summary'];
            $isFinish = $closingType === 'finish';
        @endphp
        <div class="overlay open" wire:key="close-modal-{{ $cd->id }}" x-on:keydown.escape.window="$wire.cancelClose()"
            role="dialog" aria-modal="true" aria-labelledby="closeModalTitle">
            <div class="modal">
                <div class="modal-header">
                    <h2 id="closeModalTitle">{{ $isFinish ? 'Dar de alta' : 'Cancelar dieta' }} · Hab. {{ $cd->habitation }}</h2>
                    <button type="button" class="close-btn" wire:click="cancelClose" aria-label="Cerrar">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                    </button>
                </div>
                <form wire:submit="confirmClose">
                    <div class="modal-body ncm-body">
                        <p class="ncm-lead">
                            @if ($isFinish)
                                ¿Dar de alta a <strong>{{ $cd->name_patient }}</strong>? La dieta pasa a Finalizadas y deja de verse en dietas activas. El registro y su historial se conservan.
                            @else
                                ¿Cancelar la dieta de <strong>{{ $cd->name_patient }}</strong>? Cocina deja de despacharla desde ahora.
                            @endif
                        </p>

                        {{-- Despachos sin confirmar: salieron de cocina antes del cierre, así que se cobran --}}
                        @if ($sum['pending'])
                            <div class="ncm-box ncm-warn" role="alert">
                                <strong>{{ count($sum['pending']) === 1 ? 'Tiene 1 despacho sin confirmar la recepción' : 'Tiene '.count($sum['pending']).' despachos sin confirmar la recepción' }}:</strong>
                                <ul>
                                    @foreach ($sum['pending'] as $p)
                                        <li>{{ $p['meal'] }} — {{ $p['today'] ? 'hoy' : $p['date'] }}, despachado {{ $p['at'] }}</li>
                                    @endforeach
                                </ul>
                                <p>Ya salió de cocina, así que <strong>se cobrará</strong>. Confirma la recepción en «Despachos por recibir» para que quede en la facturación.</p>
                            </div>
                        @endif

                        @if ($sum['receivedToday'])
                            <p class="ncm-box ncm-info">Hoy ya se recibió: {{ collect($sum['receivedToday'])->map(fn ($m) => mb_strtolower($m))->join(', ', ' y ') }}. Se cobra.</p>
                        @endif

                        @if (! $sum['pending'] && ! $sum['receivedToday'])
                            <p class="ncm-muted">No tiene despachos de hoy ni recepciones pendientes.</p>
                        @endif

                        <p class="ncm-muted">
                            {{ $isFinish
                                ? 'Después de la hora del alta no se despachan ni se cobran más tiempos, salvo los que se envíen a otro lugar.'
                                : 'El día de la cancelación solo se cobra lo despachado antes de cancelar y recibido.' }}
                        </p>

                        {{-- Alta con envío a otro lugar (p. ej. remitido a otra IPS cercana) --}}
                        @if ($isFinish)
                            <fieldset class="ncm-transfer">
                                <legend>¿Se debe despachar la comida a otro lugar?</legend>
                                @if ($sum['candidates'])
                                    <label class="ncm-check">
                                        <input type="checkbox" wire:model.live="transferEnabled">
                                        <span>Sí, enviar a otro lugar (por ejemplo, el paciente fue remitido a otra IPS cercana)</span>
                                    </label>

                                    @if ($transferEnabled)
                                        <div class="form-group">
                                            <label for="transferDestination">Destino <span class="req">*</span></label>
                                            <input type="text" id="transferDestination" wire:model="transferDestination" maxlength="150"
                                                placeholder="Ej.: IPS San José, sede norte — piso 2" autocomplete="off">
                                            @error('transferDestination') <p class="ncm-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="form-group">
                                            <span class="ncm-label">Tiempos que se envían hoy <span class="req">*</span></span>
                                            <div class="ncm-meals">
                                                @foreach ($sum['candidates'] as $key => $label)
                                                    <label class="ncm-check ncm-meal">
                                                        <input type="checkbox" value="{{ $key }}" wire:model="transferMeals">
                                                        <span>{{ $label }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            @error('transferMeals') <p class="ncm-error">{{ $message }}</p> @enderror
                                            <p class="ncm-muted">Cocina los verá en su lista con el destino; se cobran al confirmar la recepción.</p>
                                        </div>
                                    @endif
                                @else
                                    <p class="ncm-muted">No quedan tiempos de hoy por despachar.</p>
                                @endif
                            </fieldset>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" wire:click="cancelClose">Volver</button>
                        <button type="submit" @class(['btn', 'btn-info' => $isFinish, 'btn-danger' => ! $isFinish]) wire:loading.attr="disabled" wire:target="confirmClose">
                            {{ $isFinish ? 'Dar de alta' : 'Cancelar dieta' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
