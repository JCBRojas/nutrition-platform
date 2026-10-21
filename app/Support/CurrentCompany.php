<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

/**
 * Empresa seleccionada por el usuario autenticado (NUT-RN-003-09 a -12).
 *
 * Se guarda en la sesión ('company_id') y en users.last_company_id. Si la de la sesión ya no
 * es elegible (desactivada o quitada al usuario) se usa la última, luego la predeterminada y
 * luego la primera asignada. Sin usuario autenticado (consola, colas) no hay empresa y los
 * modelos no se filtran; un usuario sin empresas asignadas obtiene 0 (no ve nada).
 *
 * Lo usan los scopes globales de Diet, DietDispatch y DietDay (Scopes\CompanyScope).
 */
class CurrentCompany
{
    public const SESSION_KEY = 'company_id';

    /** @var array<int, int> userId => companyId (resuelto en esta petición) */
    private array $resolved = [];

    /** ID de la empresa activa, 0 si el usuario no tiene ninguna, null sin usuario */
    public function id(): ?int
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        return $this->resolved[$user->id] ??= $this->resolve($user);
    }

    public function get(): ?Company
    {
        return ($id = $this->id()) ? Company::find($id) : null;
    }

    /** ID de la empresa activa o 403 si el usuario no tiene empresas */
    public function require(): int
    {
        $id = $this->id();
        abort_unless($id, 403, 'No tienes empresas asignadas.');

        return $id;
    }

    /** Cambia la empresa activa; 403 si el usuario no puede elegirla (NUT-RN-003-12) */
    public function switch(int $companyId): Company
    {
        $user = auth()->user();
        $company = $user?->selectableCompanies()->whereKey($companyId)->first();
        abort_unless($company, 403);

        $this->remember($user, $company->id);

        return $company;
    }

    public function forget(): void
    {
        $this->resolved = [];
    }

    private function resolve(User $user): int
    {
        $selectable = $user->selectableCompanies()->get(['companies.id', 'is_default'])->keyBy('id');
        $session = request()->hasSession() ? request()->session()->get(self::SESSION_KEY) : null;

        $id = collect([$session, $user->last_company_id, $selectable->firstWhere('is_default', true)?->id, $selectable->keys()->first()])
            ->first(fn ($candidate) => $candidate && $selectable->has($candidate));

        if ($id && (int) $session !== $id) {
            $this->remember($user, $id);
        }

        return (int) $id;
    }

    private function remember(User $user, int $companyId): void
    {
        if (request()->hasSession()) {
            request()->session()->put(self::SESSION_KEY, $companyId);
        }
        if ($user->last_company_id !== $companyId) {
            $user->forceFill(['last_company_id' => $companyId])->saveQuietly();
        }
        $this->resolved[$user->id] = $companyId;
    }
}
