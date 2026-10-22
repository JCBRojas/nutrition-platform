<div class="content" id="page-usuarios">
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

        {{-- ===================== ENCABEZADO ===================== --}}
        <div class="tabs-wrapper mb-4" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
            <h2 class="card-title">Usuarios</h2>

            <div style="display:flex;gap:8px">
                @can('manageRoles')
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost">Roles y permisos</a>
                @endcan
                @can('create', App\Models\User::class)
                    <button type="button" class="btn btn-primary" wire:click="create">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Nuevo usuario
                    </button>
                @endcan
            </div>
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
                <input type="search" class="filter-input" placeholder="Buscar nombre, correo o teléfono"
                    wire:model.live.debounce.400ms="search">
            </div>
            <div class="filter-group">
                <select class="filter-select" wire:model.live="role">
                    <option value="">Todos los roles</option>
                    @foreach ($filterRoles as $r)
                        <option value="{{ $r }}">{{ ucfirst($r) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <select class="filter-select" wire:model.live="status">
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
                    <option value="trashed">Eliminados</option>
                    <option value="all">Todos</option>
                </select>
            </div>
        </div>

        {{-- ===================== TABLA ===================== --}}
        <div class="card">
            <div class="table-responsive">
                <table class="table-wrap">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Roles</th>
                            @if ($assignsCompanies)
                                <th>Empresas</th>
                            @endif
                            <th>WhatsApp</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}" @class(['cancelled-row' => $user->trashed()])>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone ?? '—' }}</td>
                                <td>
                                    @forelse ($user->roles as $r)
                                        <span class="role-badge role-{{ $r->name }}">{{ ucfirst($r->name) }}</span>
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>
                                @if ($assignsCompanies)
                                    <td>
                                        @if ($user->hasAllCompanies())
                                            <span class="badge badge-success">Todas</span>
                                        @else
                                            {{ $user->companies->pluck('name')->join(', ') ?: '—' }}
                                        @endif
                                    </td>
                                @endif
                                <td>{{ $user->whatsapp_opt_in ? 'Sí' : 'No' }}</td>
                                <td>
                                    @if ($user->trashed())
                                        <span class="badge badge-danger">Eliminado</span>
                                    @elseif ($user->is_active)
                                        <span class="badge badge-success">Activo</span>
                                    @else
                                        <span class="badge badge-pendiente">Inactivo</span>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap">
                                    @if ($user->trashed())
                                        @can('restore', $user)
                                            <button class="btn btn-success btn-sm" wire:click="restore({{ $user->id }})">
                                                Restaurar
                                            </button>
                                        @endcan
                                    @else
                                        @can('update', $user)
                                            <button class="btn btn-info btn-sm" wire:click="edit({{ $user->id }})">Editar</button>
                                            @unless ($user->is(auth()->user()))
                                                <button class="btn btn-ghost btn-sm" wire:click="toggleActive({{ $user->id }})">
                                                    {{ $user->is_active ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            @endunless
                                        @endcan
                                        @can('delete', $user)
                                            <button class="btn btn-danger btn-sm" wire:click="delete({{ $user->id }})"
                                                wire:confirm="¿Eliminar a {{ $user->name }}? Podrás restaurarlo después.">
                                                Eliminar
                                            </button>
                                        @endcan
                                        @if (auth()->user()->cannot('update', $user) && auth()->user()->cannot('delete', $user))
                                            <span style="color:var(--color-texto-suave);font-size:12px">Solo lectura</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $assignsCompanies ? 8 : 7 }}" class="empty">No hay usuarios con estos filtros</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $users->onEachSide(1)->links('pagination.table', ['livewire' => true]) }}
    </div>

    {{-- ===================== MODAL FORMULARIO ===================== --}}
    <div @class(['overlay', 'open' => $showForm]) wire:keydown.escape.window="closeForm">
        <div class="modal">
            <div class="modal-header">
                <h2>{{ $editingId ? 'Editar usuario' : 'Nuevo usuario' }}</h2>
                <button type="button" class="close-btn" wire:click="closeForm">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>

            <form wire:submit="save">
                <div class="modal-body">
                    @error('user')
                        <div class="danger-banner mb-4">{{ $message }}</div>
                    @enderror

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre <span class="req">*</span></label>
                            <input type="text" wire:model="name" autocomplete="off">
                            @error('name') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label>Correo <span class="req">*</span></label>
                            <input type="email" wire:model="email" autocomplete="off">
                            @error('email') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Teléfono (WhatsApp)</label>
                            <input type="tel" wire:model="phone" inputmode="numeric" maxlength="14" placeholder="3001234567">
                            @error('phone') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label>Roles</label>
                            @foreach ($allRoles as $r)
                                <label style="display:inline-flex;align-items:center;gap:6px;margin-right:12px;font-weight:400">
                                    <input type="checkbox" value="{{ $r }}" wire:model="roles"> {{ ucfirst($r) }}
                                </label>
                            @endforeach
                            @error('roles.*') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if ($assignsCompanies)
                        <fieldset class="form-group" style="border:0;padding:0;margin:0 0 14px">
                            <legend style="font-weight:600;font-size:13px;margin-bottom:6px">Empresas <span class="req">*</span></legend>
                            @if ($offersAllCompanies)
                                <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;margin-bottom:6px">
                                    <input type="checkbox" wire:model.live="all_companies"> Todas las empresas (incluye las que se creen después)
                                </label>
                                <div class="form-hint" style="margin-bottom:6px">A los despachadores y jefes de despacho se les asignan empresas específicas.</div>
                            @endif
                            @if ($offersOwnCompanies && $companyOptions->where('is_active', true)->isNotEmpty())
                                @php
                                    $ownIds = $companyOptions->where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->all();
                                    $allOwn = array_diff($ownIds, $companies) === [];
                                @endphp
                                <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;margin-bottom:6px">
                                    <input type="checkbox" wire:click="toggleOwnCompanies" @checked($allOwn)> Todas mis empresas
                                </label>
                            @endif
                            @unless ($all_companies)
                                <div>
                                    @forelse ($companyOptions as $c)
                                        <label style="display:inline-flex;align-items:center;gap:6px;margin-right:12px;font-weight:400">
                                            <input type="checkbox" value="{{ $c->id }}" wire:model="companies">
                                            {{ $c->name }}@unless ($c->is_active) <span class="form-hint">(desactivada)</span>@endunless
                                        </label>
                                    @empty
                                        <div class="form-hint">No hay empresas activas.</div>
                                    @endforelse
                                </div>
                            @endunless
                            @error('companies') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                            @error('companies.*') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                        </fieldset>
                    @endif

                    <div class="form-row">
                        <div class="form-group">
                            <label>Contraseña @unless ($editingId)<span class="req">*</span>@endunless</label>
                            <input type="password" wire:model="password" autocomplete="new-password">
                            {{-- SCRUM-25: las reglas se ven desde el inicio y, al fallar, todos los errores a la vez --}}
                            <div class="form-hint">Mínimo 10 caracteres, con mayúsculas, minúsculas y números.@if ($editingId) Déjala vacía para conservar la actual.@endif</div>
                            @foreach ($errors->get('password') as $message)
                                <div class="form-hint text-red-600">{{ $message }}</div>
                            @endforeach
                        </div>
                        <div class="form-group">
                            <label>Confirmar contraseña</label>
                            <input type="password" wire:model="password_confirmation" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="form-row">
                        <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400">
                            <input type="checkbox" wire:model="is_active"> Usuario activo
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400">
                            <input type="checkbox" wire:model="whatsapp_opt_in"> Acepta notificaciones por WhatsApp
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" wire:click="closeForm">Cancelar</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        {{ $editingId ? 'Guardar cambios' : 'Crear usuario' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
