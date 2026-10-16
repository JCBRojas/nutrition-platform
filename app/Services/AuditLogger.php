<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Escribe el registro de auditoría. Nunca interrumpe la operación del usuario: si falla,
 * el error queda en el log. La descripción no debe llevar datos clínicos (solo habitación,
 * IDs o nombres de usuario); los detalles van en meta.
 */
class AuditLogger
{
    public static function log(string $event, string $description, ?Model $subject = null, array $meta = [], ?int $userId = null): void
    {
        rescue(function () use ($event, $description, $subject, $meta, $userId) {
            $request = app()->runningInConsole() ? null : request();

            AuditLog::create([
                'user_id' => $userId ?? auth()->id(),
                'event' => $event,
                'description' => Str::limit($description, 250),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'meta' => $meta ?: null,
                'ip' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            ]);
        });
    }
}
