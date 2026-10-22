<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Dominio de correo autorizado para iniciar sesión (NUT-RN-002). Una sola lista para la plataforma.
 * Con la lista vacía no se restringe; con al menos un dominio (activo o no), solo entran los
 * correos de un dominio activo. La coincidencia es exacta (sin subdominios).
 */
class EmailDomain extends Model
{
    /** Formato de dominio: etiquetas de letras, números y guiones, con al menos un punto */
    public const PATTERN = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    protected $fillable = ['domain', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Minúsculas, sin espacios ni «@» inicial */
    public static function normalize(string $domain): string
    {
        return ltrim(Str::lower(trim($domain)), '@');
    }

    /** Dominio de un correo (lo que va después de la última @), en minúsculas */
    public static function of(string $email): string
    {
        return Str::lower(trim(Str::afterLast($email, '@')));
    }

    /** La lista está vacía: no se restringe el acceso (instalación inicial) */
    public static function unrestricted(): bool
    {
        return ! static::query()->exists();
    }

    /** El correo pertenece a un dominio autorizado (o la lista está vacía) */
    public static function allows(string $email): bool
    {
        return static::unrestricted() || static::active()->where('domain', static::of($email))->exists();
    }

    /** Usuarios (incluidos los eliminados) cuyo correo es de este dominio */
    public function users(): Builder
    {
        return User::withTrashed()->whereRaw('LOWER(email) LIKE ?', ['%@'.$this->domain]);
    }
}
