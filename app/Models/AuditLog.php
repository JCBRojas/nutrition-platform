<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Registro de auditoría (inmutable): quién hizo qué, cuándo y desde dónde.
 * Se crea con App\Services\AuditLogger. No se edita ni se borra desde la aplicación.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** Eventos y su etiqueta en la UI */
    public const EVENTS = [
        'login' => 'Inicio de sesión',
        'login_failed' => 'Intento fallido de inicio de sesión',
        'lockout' => 'Bloqueo por intentos fallidos',
        'logout' => 'Cierre de sesión',
        'password_reset' => 'Restablecimiento de contraseña',
        'two_factor_enabled' => '2FA activada',
        'two_factor_disabled' => '2FA desactivada',
        'recovery_codes' => 'Códigos de recuperación 2FA regenerados',
        'privacy_accepted' => 'Aceptó la política de datos',
        'user_created' => 'Usuario creado',
        'user_updated' => 'Usuario modificado',
        'user_deactivated' => 'Usuario desactivado',
        'user_activated' => 'Usuario activado',
        'user_deleted' => 'Usuario eliminado',
        'user_restored' => 'Usuario restaurado',
        'role_created' => 'Rol creado',
        'role_deleted' => 'Rol eliminado',
        'permissions_updated' => 'Permisos actualizados',
        'price_updated' => 'Precio modificado',
        'export' => 'Exportación de datos',
        'history_viewed' => 'Consulta de historial de dieta',
        'backup_verified' => 'Verificación de respaldo',
    ];

    protected $fillable = ['user_id', 'event', 'description', 'subject_type', 'subject_id', 'meta', 'ip', 'user_agent'];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El registro de auditoría no se modifica.'));
        static::deleting(fn () => throw new LogicException('El registro de auditoría no se borra.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function eventLabel(): string
    {
        return self::EVENTS[$this->event] ?? $this->event;
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
