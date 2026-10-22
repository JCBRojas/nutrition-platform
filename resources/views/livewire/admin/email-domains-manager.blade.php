<div class="content" id="page-domains">
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
        <div class="mb-4">
            <h2 class="card-title">Dominios de correo</h2>
            <p class="ed-help">
                Solo inician sesión los usuarios cuyo correo es de un dominio activo de esta lista (p. ej. con
                <strong>hospitalsanjose.org</strong>, entra maria@hospitalsanjose.org y no maria@gmail.com).
                La coincidencia es exacta: cada subdominio se agrega aparte.
            </p>
        </div>

        @if (session('success'))
            <div class="info-banner mb-4" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="danger-banner mb-4" role="alert">{{ session('error') }}</div>
        @endif

        @if ($domains->isEmpty())
            <div class="info-banner mb-4">La lista está vacía: por ahora cualquier dominio puede iniciar sesión. Agrega primero el dominio de un administrador.</div>
        @endif

        <form wire:submit="add" class="filters-bar mb-4">
            <div class="filter-group">
                <label for="ed-domain" class="sr-only">Dominio</label>
                <input type="text" id="ed-domain" class="filter-input" placeholder="hospitalsanjose.org" maxlength="253"
                    wire:model="domain" autocomplete="off" @error('domain') aria-invalid="true" aria-describedby="ed-domain-error" @enderror>
                @error('domain') <div id="ed-domain-error" class="form-hint text-red-600">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="add">Autorizar dominio</button>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table-wrap">
                    <thead>
                        <tr><th>Dominio</th><th>Usuarios</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($domains as $d)
                            <tr wire:key="domain-{{ $d->id }}">
                                <td><strong>{{ $d->domain }}</strong></td>
                                <td>{{ $d->users_count }}</td>
                                <td>
                                    <span class="ed-badge {{ $d->is_active ? 'ed-on' : 'ed-off' }}">{{ $d->is_active ? 'Activo' : 'Inactivo' }}</span>
                                </td>
                                <td class="ed-actions">
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="toggle({{ $d->id }})" wire:loading.attr="disabled">
                                        {{ $d->is_active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" wire:click="startDelete({{ $d->id }})">Eliminar</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty" style="padding:24px">No hay dominios autorizados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($deleting)
        <div class="overlay open" wire:key="domain-delete-{{ $deleting->id }}" x-on:keydown.escape.window="$wire.cancelDelete()"
            role="dialog" aria-modal="true" aria-labelledby="edDeleteTitle">
            <div class="modal">
                <div class="modal-header">
                    <h2 id="edDeleteTitle">Eliminar {{ $deleting->domain }}</h2>
                    <button type="button" class="close-btn" wire:click="cancelDelete" aria-label="Cerrar">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="ed-lead">
                        {{ $deleting->users_count }} usuario(s) con este dominio ya no podrán iniciar sesión y se cerrarán sus sesiones abiertas.
                        @if ($domains->count() === 1)
                            Es el único dominio: al eliminarlo la lista queda vacía y cualquier dominio podrá ingresar.
                        @endif
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" wire:click="cancelDelete">Volver</button>
                    <button type="button" class="btn btn-danger" wire:click="confirmDelete" wire:loading.attr="disabled">Eliminar dominio</button>
                </div>
            </div>
        </div>
    @endif

    <style>
        .ed-help { margin: 2px 0 0; font-size: 13px; color: var(--color-texto-suave); max-width: 720px; }
        .ed-actions { display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
        .ed-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .ed-on { background: var(--color-exito-suave); color: var(--color-texto); border: 1px solid var(--color-exito); }
        .ed-off { background: var(--color-fondo); color: var(--color-texto-suave); border: 1px solid var(--color-borde); }
        .ed-lead { margin: 0; }
    </style>
</div>
