<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Precios por empresa (NUT-RN-003-14, SCRUM-10): los precios existentes quedan en la
 * empresa predeterminada y la vigencia es única por empresa + tipo + servicio + fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_prices', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        $defaultId = DB::table('companies')->where('is_default', true)->value('id');
        DB::table('service_prices')->whereNull('company_id')->update(['company_id' => $defaultId]);

        Schema::table('service_prices', function (Blueprint $table) {
            $table->dropUnique(['billing_type', 'service', 'valid_from']);
            $table->unique(['company_id', 'billing_type', 'service', 'valid_from']);
        });
    }

    public function down(): void
    {
        // Antes de volver al índice sin empresa solo puede quedar la predeterminada
        $defaultId = DB::table('companies')->where('is_default', true)->value('id');
        DB::table('service_prices')->where('company_id', '!=', $defaultId)->delete();

        Schema::table('service_prices', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'billing_type', 'service', 'valid_from']);
            $table->unique(['billing_type', 'service', 'valid_from']);
        });

        Schema::table('service_prices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
