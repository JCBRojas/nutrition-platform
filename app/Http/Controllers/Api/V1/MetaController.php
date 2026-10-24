<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DietsChanged;
use App\Http\Controllers\Controller;
use App\Models\DietDispatchVoid;
use App\Services\BillingService;
use App\Services\Reports\DietReportService;
use Illuminate\Http\JsonResponse;

/**
 * Datos públicos para la app de escritorio antes y después de iniciar sesión: versión mínima,
 * catálogos (las mismas claves y etiquetas de config/) y conexión de tiempo real. Sin datos
 * clínicos ni secretos (la clave de Pusher es pública, igual que VITE_PUSHER_APP_KEY).
 */
class MetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $schedule = config('timeFood.schedule');
        $key = config('broadcasting.default') === 'pusher' ? config('broadcasting.connections.pusher.key') : null;

        return response()->json([
            'app' => config('app.name'),
            'min_desktop_version' => config('desktop.min_version'),
            'web_url' => rtrim(config('app.url'), '/'),
            'catalogs' => [
                // En orden cronológico, con su hora (fraccionada no tiene)
                'meals' => collect(DietReportService::MEALS)->map(fn ($key) => [
                    'key' => $key,
                    'label' => config("timeFood.labels.{$key}", $key),
                    'time' => $schedule[$key] ?? null,
                ])->values(),
                'consistency' => config('consistency.labels'),
                'specifications' => config('specifications.labels'),
                'billing_types' => BillingService::selectableTypes(),
                'void_kinds' => DietDispatchVoid::LABELS,
            ],
            'realtime' => [
                'enabled' => filled($key),
                'key' => $key,
                'host' => config('desktop.realtime.host'),
                'port' => config('desktop.realtime.port'),
                'scheme' => config('desktop.realtime.scheme'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'auth_endpoint' => '/api/v1/broadcasting/auth',
                'channel' => 'private-'.DietsChanged::CHANNEL,
                'event' => '.'.DietsChanged::EVENT,
            ],
        ]);
    }
}
