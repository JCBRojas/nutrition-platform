<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Despacho que no se recibió: el nutricionista reportó que no llegó o el despachador lo
 * anuló por error. Copia del DietDispatch borrado; no cuenta en reportes ni facturación.
 */
class DietDispatchVoid extends Model
{
    public const NOT_ARRIVED = 'not_arrived';

    public const CANCELLED = 'cancelled';

    public const LABELS = [
        self::NOT_ARRIVED => 'No llegó',
        self::CANCELLED => 'Anulado por cocina',
    ];

    /** Solo los de dietas de la empresa activa (CompanyScope) */
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'diet_id',
        'diet_history_id',
        'service_date',
        'meal',
        'dispatched_by',
        'dispatched_at',
        'kind',
        'reason',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'service_date' => 'date',
        'dispatched_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function label(): string
    {
        return self::LABELS[$this->kind] ?? $this->kind;
    }

    public function isNotArrived(): bool
    {
        return $this->kind === self::NOT_ARRIVED;
    }

    public function diet()
    {
        return $this->belongsTo(Diet::class);
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
