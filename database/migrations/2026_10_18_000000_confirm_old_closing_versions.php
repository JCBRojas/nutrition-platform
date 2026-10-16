<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El despachador ahora debe confirmar también las cancelaciones y altas (modify = 'si').
 * Las de dietas cerradas hace más de un día ya se atendieron: se marcan confirmadas
 * para que no aparezcan como alertas pendientes. Solo cambia el flag `modify`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('diet_histories')
            ->where('modify', 'si')
            ->where('created_at', '<', now()->subDay())
            ->whereIn('diet_id', DB::table('diets')->where('status', '!=', 1)->select('id'))
            ->update(['modify' => 'no']);
    }

    public function down(): void
    {
        // Sin reversa: no se sabe qué versiones estaban pendientes antes.
    }
};
