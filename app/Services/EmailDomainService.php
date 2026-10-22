<?php

namespace App\Services;

use App\Models\EmailDomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lista de dominios de correo autorizados (NUT-RN-002). Ningún cambio puede dejar sin acceso
 * al último admin activo, y tras cada cambio se cierran las sesiones de quienes perdieron el acceso.
 */
class EmailDomainService
{
    public function create(string $domain): EmailDomain
    {
        return DB::transaction(function () use ($domain) {
            $model = EmailDomain::create(['domain' => EmailDomain::normalize($domain), 'is_active' => true]);
            $this->ensureAdminKeepsAccess('Agrega primero el dominio de un administrador activo: este cambio lo dejaría sin acceso.');
            AuditLogger::log('email_domain_created', "Agregó el dominio {$model->domain}", $model, ['domain' => $model->domain]);
            $this->endSessionsWithoutAccess();

            return $model;
        });
    }

    public function toggleActive(EmailDomain $domain): EmailDomain
    {
        return DB::transaction(function () use ($domain) {
            $domain->update(['is_active' => ! $domain->is_active]);
            $this->ensureAdminKeepsAccess('No se puede desactivar: ningún administrador activo podría iniciar sesión.');
            AuditLogger::log('email_domain_updated', ($domain->is_active ? 'Activó' : 'Desactivó')." el dominio {$domain->domain}", $domain, [
                'domain' => $domain->domain,
                'is_active' => $domain->is_active,
            ]);
            $this->endSessionsWithoutAccess();

            return $domain;
        });
    }

    public function delete(EmailDomain $domain): void
    {
        DB::transaction(function () use ($domain) {
            $domain->delete();
            $this->ensureAdminKeepsAccess('No se puede eliminar: ningún administrador activo podría iniciar sesión.');
            AuditLogger::log('email_domain_deleted', "Eliminó el dominio {$domain->domain}", $domain, ['domain' => $domain->domain]);
            $this->endSessionsWithoutAccess();
        });
    }

    /** NUT-RN-002-06: tras el cambio (dentro de la transacción) debe quedar un admin activo que pueda entrar */
    private function ensureAdminKeepsAccess(string $message): void
    {
        $adminCanEnter = User::role(UserService::ADMIN_ROLE)->where('is_active', true)->pluck('email')
            ->contains(fn ($email) => EmailDomain::allows($email));

        if (! $adminCanEnter) {
            throw ValidationException::withMessages(['domain' => $message]);
        }
    }

    /** Cierra las sesiones abiertas de los usuarios cuyo dominio ya no está autorizado */
    private function endSessionsWithoutAccess(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $sessions = DB::table(config('session.table', 'sessions'));
        $userIds = (clone $sessions)->whereNotNull('user_id')->distinct()->pluck('user_id');
        $blocked = User::withTrashed()->whereIn('id', $userIds)->get(['id', 'email'])
            ->reject(fn (User $user) => EmailDomain::allows($user->email))
            ->modelKeys();

        if ($blocked !== []) {
            $sessions->whereIn('user_id', $blocked)->delete();
        }
    }
}
