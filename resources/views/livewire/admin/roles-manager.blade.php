<div class="content" id="page-roles">
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

        <div class="tabs-wrapper mb-4" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
            <h2 class="card-title">Roles y permisos</h2>
            <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">← Usuarios</a>
        </div>

        @if (session('success'))
            <div class="info-banner mb-4">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="danger-banner mb-4">{{ session('error') }}</div>
        @endif

        {{-- ===================== NUEVO ROL ===================== --}}
        <form wire:submit="createRole" class="filters-bar mb-4">
            <div class="filter-group">
                <input type="text" class="filter-input" placeholder="Nuevo rol (p. ej. auxiliar_cocina)" wire:model="newRole">
                @error('newRole') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary">Crear rol</button>
        </form>

        {{-- ===================== MATRIZ ===================== --}}
        <form wire:submit="save">
            <div class="card">
                <div class="table-responsive">
                    <table class="table-wrap">
                        <thead>
                            <tr>
                                <th>Permiso</th>
                                @foreach ($roles as $role)
                                    <th class="text-center">
                                        {{ ucfirst($role->name) }}
                                        <div class="form-hint">{{ $role->users_count }} usuario(s)</div>
                                        @if ($role->name !== $adminRole && $role->users_count === 0)
                                            <button type="button" class="btn btn-danger btn-sm"
                                                wire:click="deleteRole('{{ $role->name }}')"
                                                wire:confirm="¿Eliminar el rol {{ $role->name }}?">Eliminar</button>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permissions as $permission)
                                <tr wire:key="perm-{{ $permission }}">
                                    <td><code>{{ $permission }}</code></td>
                                    @foreach ($roles as $role)
                                        <td class="text-center">
                                            @if ($role->name === $adminRole)
                                                <input type="checkbox" checked disabled title="El rol admin tiene todos los permisos">
                                            @else
                                                <input type="checkbox" value="{{ $permission }}"
                                                    wire:model="matrix.{{ $role->name }}">
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4" style="text-align:right">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Guardar permisos</button>
            </div>
        </form>
    </div>
</div>
