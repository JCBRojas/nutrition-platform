<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Día de servicio de una dieta: autorización para despachar (creación o «Continuar dieta»)
 * y cierre del día (todos los tiempos recibidos → Completada).
 */
class DietDay extends Model
{
    /** Solo los de dietas de la empresa activa (CompanyScope) */
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public const VIA_CREATION = 'creacion';

    public const VIA_CONTINUATION = 'continuacion';

    protected $fillable = [
        'diet_id',
        'service_date',
        'authorized_via',
        'authorized_by',
        'authorized_at',
        'completed_at',
        'completed_history_id',
    ];

    protected $casts = [
        'service_date' => 'date',
        'authorized_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function scopeOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query->whereDate('service_date', $date->toDateString());
    }

    public function scopeAuthorized(Builder $query): Builder
    {
        return $query->whereNotNull('authorized_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Crea o actualiza el día de una dieta. Busca con whereDate: según el motor, la columna
     * date se guarda como 'Y-m-d' o 'Y-m-d H:i:s' (updateOrCreate no encontraría la fila).
     */
    public static function upsertFor(int $dietId, CarbonInterface $date, array $values): self
    {
        $day = static::where('diet_id', $dietId)->on($date)->first()
            ?? new static(['diet_id' => $dietId, 'service_date' => $date->toDateString()]);
        $day->fill($values)->save();

        return $day;
    }

    public function isAuthorized(): bool
    {
        return $this->authorized_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function diet()
    {
        return $this->belongsTo(Diet::class);
    }

    public function authorizedBy()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function completedVersion()
    {
        return $this->belongsTo(DietHistory::class, 'completed_history_id');
    }
}
