<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diet extends Model
{
    use HasFactory;

    /**
     * Estados de diets.status (columna tinyint; no usar cast boolean: 2 se leería como true).
     */
    public const CANCELLED = 0;

    public const ACTIVE = 1;

    public const FINISHED = 2; // alta: el paciente salió de la institución

    /** Valores de diet_histories.changes que cierran una dieta */
    public const CLOSING_CHANGES = ['cancelada', 'finalizada'];

    public const STATUS_LABELS = [
        self::ACTIVE => 'Activa',
        self::CANCELLED => 'Cancelada',
        self::FINISHED => 'Finalizada',
    ];

    protected $fillable = [
        'habitation',
        'name_patient',
        'status',
        'confirmed',
    ];

    protected $casts = [
        'status' => 'integer',
        'confirmed' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', self::ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Desconocido';
    }

    public function history()
    {
        return $this->hasMany(DietHistory::class, 'diet_id');
    }

    public function currentVersion()
    {
        return $this->hasOne(DietHistory::class, 'diet_id')->latestOfMany('version');
    }
}
