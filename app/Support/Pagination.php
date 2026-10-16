<?php

namespace App\Support;

/**
 * Regla de paginación de tablas (skill table-pagination): 15 por página por defecto,
 * parámetros estándar `page` y `per_page`.
 */
class Pagination
{
    public const PER_PAGE = 15;

    /** Valores aceptados en ?per_page= (cualquier otro vuelve a 15) */
    public const PER_PAGE_OPTIONS = [15, 30, 50];

    public static function perPage(mixed $value): int
    {
        return in_array((int) $value, self::PER_PAGE_OPTIONS, true) ? (int) $value : self::PER_PAGE;
    }
}
