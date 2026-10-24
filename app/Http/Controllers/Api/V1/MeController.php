<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Usuario de la app de escritorio: lo necesario para armar el menú y elegir empresa.
 * Sin teléfono, 2FA ni datos de la política (SCRUM-18). Responde aunque falte aceptar la
 * política: la app muestra el aviso con el enlace a la web.
 */
class MeController extends Controller
{
    public function __invoke(Request $request, CurrentCompany $company, SubscriptionService $subscriptions): JsonResponse
    {
        $user = $request->user();
        $permissions = $user->getAllPermissions()->pluck('name')->sort()->values();
        $can = fn (string $p) => $user->can($p);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $permissions,
            // Pantalla inicial (como User::homeUrl en la web)
            'home' => match (true) {
                $user->hasRole('despachador') && $can('confirmDelivery') => 'dispatch',
                $can('viewActiveDiets') => 'diets',
                $can('viewDispatches') => 'dispatches',
                default => 'web',
            },
            'companies' => $user->selectableCompanies()->orderBy('name')->get(['companies.id', 'name', 'is_default'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'is_default' => (bool) $c->is_default])->values(),
            'current_company_id' => $company->id() ?: null,
            'premium' => collect(array_keys(config('subscriptions.premium_features')))
                ->filter(fn ($feature) => $subscriptions->hasFeature($feature, $user))->values(),
            'compliance' => [
                'privacy_required' => config('security.privacy.required') && ! $user->hasAcceptedPrivacyPolicy(),
                'two_factor_setup_required' => $user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled(),
            ],
        ]);
    }
}
