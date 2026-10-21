<div class="content" id="page-auditoria">
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

        <div class="mb-4">
            <h2 class="card-title">Auditoría</h2>
            <p class="al-help">Accesos, cambios de usuarios, roles y precios, exportaciones y consultas del historial. El registro no se puede modificar ni borrar.</p>
        </div>

        <div class="filters-bar mb-4">
            <div class="filter-group">
                <input type="search" class="filter-input" placeholder="Buscar usuario, descripción o IP"
                    aria-label="Buscar usuario, descripción o IP" wire:model.live.debounce.400ms="search">
            </div>
            <div class="filter-group">
                <select class="filter-select" wire:model.live="event" aria-label="Tipo de evento">
                    <option value="">Todos los eventos</option>
                    @foreach ($events as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group al-dates">
                <label>Desde <input type="date" class="filter-input" wire:model.live="from"></label>
                <label>Hasta <input type="date" class="filter-input" wire:model.live="to"></label>
            </div>
            @if ($event || $search || $from || $to)
                <button type="button" class="btn btn-ghost btn-sm" wire:click="clearFilters">Quitar filtros</button>
            @endif
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table-wrap">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Evento</th>
                            <th>Descripción</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr wire:key="audit-{{ $log->id }}">
                                <td class="al-nowrap">
                                    {{ $log->created_at->format('d/m/Y') }}
                                    <div class="al-meta">{{ $log->created_at->format('h:i:s a') }}</div>
                                </td>
                                <td>
                                    {{ $log->user?->name ?? 'Sistema / sin sesión' }}
                                    @if ($log->user)
                                        <div class="al-meta">{{ $log->user->email }}</div>
                                    @endif
                                </td>
                                <td><span @class(['tag', 'al-tag', 'al-tag-warn' => in_array($log->event, ['login_failed', 'lockout', 'two_factor_disabled', 'user_deleted', 'role_deleted'], true)])>{{ $log->eventLabel() }}</span></td>
                                <td>
                                    {{ $log->description }}
                                    @if ($log->meta)
                                        <details class="al-details">
                                            <summary>Detalle</summary>
                                            <pre>{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </details>
                                    @endif
                                </td>
                                <td class="al-nowrap">
                                    {{ $log->ip ?? '—' }}
                                    @if ($log->user_agent)
                                        <div class="al-meta al-ua" title="{{ $log->user_agent }}">{{ \Illuminate\Support\Str::limit($log->user_agent, 32) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty" style="padding:24px">No hay registros con estos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $logs->links('pagination.table', ['livewire' => true]) }}
        </div>
    </div>

    <style>
        .al-help { margin: 2px 0 0; font-size: 13px; color: var(--text-muted); }
        .al-dates { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .al-dates label { display: inline-flex; gap: 6px; align-items: center; font-size: 12.5px; color: var(--text-muted); }
        .al-nowrap { white-space: nowrap; }
        .al-meta { font-size: 11.5px; color: var(--text-muted); }
        .al-ua { max-width: 220px; overflow: hidden; text-overflow: ellipsis; }
        .al-tag { background: var(--surface2); color: var(--text); white-space: nowrap; }
        .al-tag-warn { background: var(--color-advertencia-suave); color: var(--color-advertencia); font-weight: 600; }
        .al-details summary { cursor: pointer; font-size: 12px; color: var(--text-muted); }
        .al-details pre { margin: 6px 0 0; padding: 8px; background: var(--surface2); border-radius: 6px; font-size: 11.5px; white-space: pre-wrap; max-width: 520px; }
    </style>
</div>
