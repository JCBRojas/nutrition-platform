<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public const ADMIN_ROLE = 'admin';

    /**
     * Guard de roles y permisos. Siempre explícito: las rutas usan auth:sanctum, que
     * durante la petición cambia el guard por defecto a 'sanctum', y Spatie crearía
     * el rol con ese guard (luego falla "There is no permission named … for guard sanctum").
     */
    public const GUARD = 'web';

    /**
     * Roles que $actor puede asignar: el admin todos; los demás, los de config security.assignable_roles
     * según su rol (el jefe de nutrición solo crea despachadores). null = sin restricción.
     */
    public static function assignableRoles(User $actor): ?array
    {
        if ($actor->hasRole(self::ADMIN_ROLE)) {
            return null;
        }

        return collect(config('security.assignable_roles', []))
            ->only($actor->getRoleNames()->all())
            ->flatten()->unique()->values()->all();
    }

    /**
     * $actor puede editar a $model: sin restricción de roles (admin) siempre; si no, solo usuarios
     * con roles que puede asignar y, si es jefe de despacho, de alguna de sus empresas (NUT-RN-003-23).
     */
    public static function canManage(User $actor, User $model): bool
    {
        $assignable = self::assignableRoles($actor);
        if ($assignable === null) {
            return true;
        }

        $roles = $model->getRoleNames();
        if ($roles->isEmpty() || $roles->diff($assignable)->isNotEmpty()) {
            return false;
        }

        return ! $actor->isDispatchManager()
            || $model->companies()->where('manager_id', $actor->getKey())->exists();
    }

    /**
     * Crear usuario con sus roles
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'password' => Hash::make($data['password']),
                'is_active' => $data['is_active'] ?? true,
                'whatsapp_opt_in' => $data['whatsapp_opt_in'] ?? false,
            ]);

            $user->syncRoles($data['roles'] ?? []);
            $companies = $this->syncCompanies($user, $data);
            AuditLogger::log('user_created', "Creó el usuario {$user->email}", $user, ['roles' => $data['roles'] ?? [], 'companies' => $companies]);

            return $user;
        });
    }

    /**
     * Actualizar datos y roles. La contraseña solo cambia si viene informada.
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $roles = $data['roles'] ?? [];

            if ($user->hasRole(self::ADMIN_ROLE) && ! in_array(self::ADMIN_ROLE, $roles, true)) {
                $this->ensureNotLastAdmin($user);
            }

            if (! ($data['is_active'] ?? true)) {
                $this->ensureCanDeactivate($user);
            }

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'is_active' => $data['is_active'] ?? true,
                'whatsapp_opt_in' => $data['whatsapp_opt_in'] ?? false,
            ]);

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $changed = array_keys($user->getDirty());
            $passwordChanged = in_array('password', $changed, true);
            $user->save();
            $previousRoles = $user->getRoleNames()->all();
            $user->syncRoles($roles);
            $previousCompanies = $this->companyAccess($user);
            $companies = $this->syncCompanies($user, $data);

            AuditLogger::log('user_updated', "Modificó el usuario {$user->email}", $user, [
                'fields' => array_values(array_diff($changed, ['password'])),
                'password_changed' => $passwordChanged,
                'roles' => ['before' => $previousRoles, 'after' => $roles],
                'companies' => ['before' => $previousCompanies, 'after' => $companies],
            ]);

            if (! $user->is_active) {
                $this->endSessions($user);
            }

            return $user;
        });
    }

    public function toggleActive(User $user): User
    {
        if ($user->is_active) {
            $this->ensureCanDeactivate($user);
        }

        $user->update(['is_active' => ! $user->is_active]);
        AuditLogger::log($user->is_active ? 'user_activated' : 'user_deactivated', ($user->is_active ? 'Activó' : 'Desactivó')." el usuario {$user->email}", $user);

        if (! $user->is_active) {
            $this->endSessions($user);
        }

        return $user;
    }

    /**
     * Eliminación lógica: desactiva y aplica soft delete
     */
    public function delete(User $user): void
    {
        $this->ensureCanDeactivate($user);

        DB::transaction(function () use ($user) {
            $user->update(['is_active' => false]);
            $user->delete();
            $this->endSessions($user);
            AuditLogger::log('user_deleted', "Eliminó el usuario {$user->email}", $user);
        });
    }

    public function restore(User $user): User
    {
        $user->restore();
        $user->update(['is_active' => true]);
        AuditLogger::log('user_restored', "Restauró el usuario {$user->email}", $user);

        return $user;
    }

    /**
     * Acceso a empresas (NUT-RN-003-05): «Todas» (all_companies, incluye las futuras) o una o
     * varias específicas. Sin las claves en $data no se modifica. Devuelve el acceso para la auditoría.
     */
    private function syncCompanies(User $user, array $data): string|array
    {
        if (! array_key_exists('all_companies', $data) && ! array_key_exists('companies', $data)) {
            return $this->companyAccess($user);
        }

        $all = (bool) ($data['all_companies'] ?? false);
        $ids = $all ? [] : array_values(array_unique(array_map('intval', $data['companies'] ?? [])));

        // Despachador y jefe de despacho: siempre empresas específicas (las del jefe son las que administra)
        $restricted = $user->hasAnyRole([User::DISPATCHER_ROLE, User::DISPATCH_MANAGER_ROLE]) && ! $user->hasRole(self::ADMIN_ROLE);
        if ($all && $restricted) {
            throw ValidationException::withMessages(['companies' => 'A los despachadores y jefes de despacho se les asignan empresas específicas, no «Todas».']);
        }

        if (! $all && $ids === [] && ! $user->isDispatchManager()) {
            throw ValidationException::withMessages(['companies' => $user->hasRole(User::DISPATCHER_ROLE)
                ? 'Asigna al menos una empresa específica al despachador.'
                : 'Asigna al menos una empresa o marca «Todas las empresas».']);
        }

        $user->forceFill(['all_companies' => $all])->save();
        $user->companies()->sync($ids);

        return $this->companyAccess($user);
    }

    private function companyAccess(User $user): string|array
    {
        return $user->all_companies ? 'all' : $user->companies()->pluck('companies.id')->all();
    }

    private function ensureCanDeactivate(User $user): void
    {
        if ($user->is(auth()->user())) {
            throw ValidationException::withMessages(['user' => 'No puedes desactivar ni eliminar tu propio usuario.']);
        }

        if ($user->hasRole(self::ADMIN_ROLE)) {
            $this->ensureNotLastAdmin($user);
        }
    }

    private function ensureNotLastAdmin(User $user): void
    {
        $otherAdmins = User::role(self::ADMIN_ROLE)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->count();

        if ($otherAdmins === 0) {
            throw ValidationException::withMessages(['user' => 'Debe existir al menos un administrador activo.']);
        }
    }

    /**
     * Cierra las sesiones abiertas del usuario (SESSION_DRIVER=database)
     */
    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }
    }
}
