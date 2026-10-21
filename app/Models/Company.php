<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Empresa cliente para la que la cocina despacha la alimentación (NUT-RN-003).
 * No se borra: se desactiva (is_active). La predeterminada (is_default) la crea la migración
 * con los datos anteriores al módulo y es con la que se trabaja sin plan Premium.
 */
class Company extends Model
{
    protected $fillable = [
        'name',
        'nit',
        'contact_name',
        'contact_phone',
        'contact_email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public static function default(): ?self
    {
        return static::where('is_default', true)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function diets()
    {
        return $this->hasMany(Diet::class);
    }
}
