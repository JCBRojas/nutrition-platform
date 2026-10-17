<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * premium:{función} (config subscriptions.premium_features): sin plan premium envía a /suscripcion.
 * El rol admin pasa siempre. También es middleware persistente de Livewire.
 */
class EnsurePremiumFeature
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($this->subscriptions->hasFeature($feature, $request->user())) {
            return $next($request);
        }

        $label = config("subscriptions.premium_features.{$feature}", 'Esta función');
        $message = "«{$label}» está disponible en el plan Premium.";

        if ($request->expectsJson()) {
            abort(402, $message);
        }

        return redirect()->route('subscription.index')->with('premium_required', $message);
    }
}
