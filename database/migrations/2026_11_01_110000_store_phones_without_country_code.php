<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los teléfonos se guardan sin indicativo (10 dígitos, App\Rules\LocalPhone):
 * «+573001234567» → «3001234567» en usuarios y contactos de empresas.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([['users', 'phone'], ['companies', 'contact_phone']] as [$table, $column]) {
            DB::table($table)->where($column, 'like', '+57%')->whereRaw("LENGTH({$column}) = 13")
                ->update([$column => DB::raw("SUBSTR({$column}, 4)")]);
        }
    }

    public function down(): void
    {
        foreach ([['users', 'phone'], ['companies', 'contact_phone']] as [$table, $column]) {
            DB::table($table)->whereRaw("LENGTH({$column}) = 10")
                ->update([$column => DB::raw("CONCAT('+57', {$column})")]);
        }
    }
};
