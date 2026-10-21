<div class="content" id="page-empresas">
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

        {{-- ===================== ENCABEZADO ===================== --}}
        <div class="tabs-wrapper mb-4" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
            <div>
                <h2 class="card-title">Empresas</h2>
                <p class="co-help">Empresas para las que la cocina despacha la alimentación. No se borran: se desactivan y conservan su historial.</p>
            </div>

            @if ($canManage)
                <button type="button" class="btn btn-primary" wire:click="create">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    Nueva empresa
                </button>
            @endif
        </div>

        @if (session('success'))
            <div class="info-banner mb-4">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="danger-banner mb-4">{{ session('error') }}</div>
        @endif

        {{-- ===================== FILTROS ===================== --}}
        <div class="filters-bar mb-4">
            <div class="filter-group">
                <input type="search" class="filter-input" placeholder="Buscar nombre, NIT o contacto" aria-label="Buscar empresa"
                    wire:model.live.debounce.400ms="search">
            </div>
            <div class="filter-group">
                <select class="filter-select" wire:model.live="status" aria-label="Estado">
                    <option value="active">Activas</option>
                    <option value="inactive">Desactivadas</option>
                    <option value="all">Todas</option>
                </select>
            </div>
        </div>

        {{-- ===================== TABLA ===================== --}}
        <div class="card">
            <div class="table-responsive">
                <table class="table-wrap">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>NIT</th>
                            <th>Contacto</th>
                            <th class="text-right">Dietas</th>
                            <th>Estado</th>
                            @if ($canManage)
                                <th class="text-right">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($companies as $company)
                            <tr wire:key="company-{{ $company->id }}" @class(['cancelled-row' => ! $company->is_active])>
                                <td>
                                    <strong>{{ $company->name }}</strong>
                                    @if ($company->is_default)
                                        <div class="co-meta">Predeterminada</div>
                                    @endif
                                </td>
                                <td class="co-nowrap">{{ $company->nit ?: '—' }}</td>
                                <td>
                                    @if ($company->contact_name)
                                        {{ $company->contact_name }}
                                        <div class="co-meta">
                                            <a href="mailto:{{ $company->contact_email }}">{{ $company->contact_email }}</a>
                                            · <a href="tel:{{ $company->contact_phone }}">{{ $company->contact_phone }}</a>
                                        </div>
                                    @else
                                        <span class="co-meta">Sin datos de contacto</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ $company->diets_count }}</td>
                                <td>
                                    @if ($company->is_active)
                                        <span class="badge badge-success">Activa</span>
                                    @else
                                        <span class="badge badge-pendiente">Desactivada</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td class="text-right co-nowrap">
                                        <button class="btn btn-info btn-sm" wire:click="edit({{ $company->id }})">Editar</button>
                                        @unless ($company->is_default && $company->is_active)
                                            <button class="btn btn-ghost btn-sm" wire:click="toggleActive({{ $company->id }})"
                                                @if ($company->is_active) wire:confirm="¿Desactivar {{ $company->name }}? Dejará de aparecer en los selectores; su historial se conserva." @endif>
                                                {{ $company->is_active ? 'Desactivar' : 'Reactivar' }}
                                            </button>
                                        @endunless
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 6 : 5 }}" class="empty" style="padding:24px">No hay empresas con estos filtros</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $companies->onEachSide(1)->links('pagination.table', ['livewire' => true]) }}
    </div>

    {{-- ===================== MODAL FORMULARIO ===================== --}}
    @if ($canManage)
        <div @class(['overlay', 'open' => $showForm]) wire:keydown.escape.window="closeForm">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="company-form-title">
                <div class="modal-header">
                    <h2 id="company-form-title">{{ $editingId ? 'Editar empresa' : 'Nueva empresa' }}</h2>
                    <button type="button" class="close-btn" wire:click="closeForm" aria-label="Cerrar">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="save">
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="co-name">Nombre <span class="req">*</span></label>
                                <input id="co-name" type="text" wire:model="name" maxlength="150" autocomplete="off">
                                @error('name') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label for="co-nit">NIT <span class="req">*</span></label>
                                <input id="co-nit" type="text" wire:model="nit" placeholder="900123456-8" autocomplete="off">
                                <div class="form-hint">Con guion y dígito de verificación.</div>
                                @error('nit') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="co-contact">Contacto <span class="req">*</span></label>
                                <input id="co-contact" type="text" wire:model="contact_name" autocomplete="off">
                                @error('contact_name') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label for="co-phone">Teléfono del contacto <span class="req">*</span></label>
                                <input id="co-phone" type="tel" wire:model="contact_phone" placeholder="+573001234567">
                                @error('contact_phone') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="co-email">Correo del contacto <span class="req">*</span></label>
                                <input id="co-email" type="email" wire:model="contact_email" autocomplete="off">
                                @error('contact_email') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" wire:click="closeForm">Cancelar</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            {{ $editingId ? 'Guardar cambios' : 'Crear empresa' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <style>
        .co-help { margin: 2px 0 0; font-size: 13px; color: var(--text-muted); }
        .co-meta { font-size: 12px; color: var(--text-muted); }
        .co-nowrap { white-space: nowrap; }
    </style>
</div>
