<?php

namespace App\Support;

use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Str;

/**
 * Menú lateral (NUT-CMP-002): resuelve config('navigation.sections') para un usuario.
 * Deja solo los enlaces que el usuario puede abrir y calcula, una vez por petición,
 * cuáles están bloqueados por el plan Premium.
 */
class Navigation
{
    /**
     * @return list<array{label: string, items: list<array{id: string, label: string, icon: string, href: string, active: bool, locked: bool}>}>
     */
    public static function sections(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $subscriptions = app(SubscriptionService::class);
        $sections = [];

        foreach (config('navigation.sections') as $section) {
            $items = [];

            foreach ($section['items'] as $item) {
                if (! self::canSee($user, $item)) {
                    continue;
                }

                $locked = isset($item['premium']) && ! $subscriptions->hasFeature($item['premium'], $user);

                $items[] = [
                    'id' => $item['id'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'href' => route($locked ? 'subscription.index' : $item['route']),
                    'active' => request()->routeIs(...($item['active'] ?? [$item['route']])),
                    'locked' => $locked,
                ];
            }

            if ($items) {
                $sections[] = ['label' => $section['label'], 'items' => $items];
            }
        }

        return $sections;
    }

    public static function roleLabel(?User $user): string
    {
        $role = $user?->getRoleNames()->first();

        if (! $role) {
            return 'Sin rol';
        }

        return config("navigation.role_labels.$role") ?? (string) Str::of($role)->replace('_', ' ')->ucfirst();
    }

    /** Iniciales del nombre para el avatar (máximo dos) */
    public static function initials(?User $user): string
    {
        return collect(preg_split('/\s+/', trim($user?->name ?? '')))
            ->filter()->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: '?';
    }

    private static function canSee(User $user, array $item): bool
    {
        if (isset($item['role'])) {
            return $user->hasRole($item['role']);
        }

        return $user->canAny((array) $item['can']);
    }
}
