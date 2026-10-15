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

            $user->save();
            $user->syncRoles($roles);

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
        });
    }

    public function restore(User $user): User
    {
        $user->restore();
        $user->update(['is_active' => true]);

        return $user;
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
