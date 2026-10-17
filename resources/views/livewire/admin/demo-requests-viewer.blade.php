<div class="content" id="page-demos">
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
        <div class="mb-4">
            <h2 class="card-title">Solicitudes de demo</h2>
            <p class="dq-help">Enviadas desde «Solicitar demo» en la página de inicio de sesión. {{ $newCount }} sin contactar.</p>
        </div>

        <div class="filters-bar mb-4">
            <div class="filter-group">
                <select class="filter-select" wire:model.live="status" aria-label="Estado">
                    <option value="">Todos los estados</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table-wrap">
                    <thead>
                        <tr><th>Fecha</th><th>Contacto</th><th>Institución</th><th>Mensaje</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $r)
                            <tr wire:key="demo-{{ $r->id }}">
                                <td class="dq-nowrap">{{ $r->created_at->format('d/m/Y') }}<div class="dq-meta">{{ $r->created_at->format('h:i a') }}</div></td>
                                <td>
                                    <strong>{{ $r->name }}</strong>
                                    @if ($r->position) <div class="dq-meta">{{ $r->position }}</div> @endif
                                    <div class="dq-meta"><a href="mailto:{{ $r->email }}">{{ $r->email }}</a> · <a href="tel:{{ preg_replace('/[^+0-9]/', '', $r->phone) }}">{{ $r->phone }}</a></div>
                                </td>
                                <td>{{ $r->institution }}@if ($r->beds) <div class="dq-meta">{{ $r->beds }} camas</div> @endif</td>
                                <td class="dq-msg">{{ $r->message ?: '—' }}</td>
                                <td>
                                    <select class="filter-select dq-status" aria-label="Estado de la solicitud de {{ $r->name }}"
                                        wire:change="setStatus({{ $r->id }}, $event.target.value)">
                                        @foreach ($statuses as $key => $label)
                                            <option value="{{ $key }}" @selected($r->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty" style="padding:24px">No hay solicitudes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $requests->links('pagination.table', ['livewire' => true]) }}
        </div>
    </div>

    <style>
        .dq-help { margin: 2px 0 0; font-size: 13px; color: var(--text-muted); }
        .dq-meta { font-size: 12px; color: var(--text-muted); }
        .dq-nowrap { white-space: nowrap; }
        .dq-msg { max-width: 360px; font-size: 13px; white-space: pre-line; }
        .dq-status { min-width: 130px; }
    </style>
</div>
