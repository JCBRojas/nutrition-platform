<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.app')]
class RolesManager extends Component
{
    /** @var array<string, array<int, string>> rol => permisos marcados */
    public array $matrix = [];

    public string $newRole = '';

    public function mount(): void
    {
        $this->authorize('manageRoles');
        $this->loadMatrix();
    }

    public function save(): void
    {
        $this->authorize('manageRoles');

        $valid = Permission::pluck('name')->all();

        foreach (Role::where('name', '!=', UserService::ADMIN_ROLE)->get() as $role) {
            $role->syncPermissions(array_values(array_intersect($this->matrix[$role->name] ?? [], $valid)));
        }

        // El rol admin conserva siempre todos los permisos
        Role::where('name', UserService::ADMIN_ROLE)->first()?->syncPermissions($valid);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->loadMatrix();
        session()->flash('success', 'Permisos actualizados');
    }

    public function createRole(): void
    {
        $this->authorize('manageRoles');

        $this->validate([
            'newRole' => ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/', Rule::unique('roles', 'name')],
        ], [
            'newRole.regex' => 'Usa minúsculas y guion bajo, p. ej. auxiliar_cocina.',
        ]);

        Role::create(['name' => $this->newRole]);
        $this->reset('newRole');
        $this->loadMatrix();
        session()->flash('success', 'Rol creado');
    }

    public function deleteRole(string $name): void
    {
        $this->authorize('manageRoles');

        $role = Role::where('name', $name)->first();

        if (! $role || $role->name === UserService::ADMIN_ROLE || $this->usersCount($role) > 0) {
            session()->flash('error', 'No se puede eliminar el rol admin ni un rol con usuarios asignados.');
            return;
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->loadMatrix();
        session()->flash('success', 'Rol eliminado');
    }

    /** Incluye usuarios eliminados (soft delete): conservan sus roles al restaurarse */
    private function usersCount(Role $role): int
    {
        return User::withTrashed()->role($role)->count();
    }

    private function loadMatrix(): void
    {
        $this->matrix = Role::with('permissions')->get()
            ->mapWithKeys(fn ($role) => [$role->name => $role->permissions->pluck('name')->all()])
            ->all();
    }

    public function render()
    {
        return view('livewire.admin.roles-manager', [
            'roles' => Role::orderBy('name')->get()
                ->each(fn (Role $role) => $role->users_count = $this->usersCount($role)),
            'permissions' => Permission::orderBy('name')->pluck('name'),
            'adminRole' => UserService::ADMIN_ROLE,
        ]);
    }
}
