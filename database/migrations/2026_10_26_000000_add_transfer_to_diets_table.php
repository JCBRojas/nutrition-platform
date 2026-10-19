<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alta con envío a otro lugar: el paciente sale (p. ej. remitido a otra IPS cercana) y
 * cocina debe enviarle ese día uno o más tiempos de comida al destino indicado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diets', function (Blueprint $table) {
            $table->string('transfer_destination', 150)->nullable()->after('status');
            $table->json('transfer_meals')->nullable()->after('transfer_destination');
            $table->date('transfer_date')->nullable()->after('transfer_meals');
        });
    }

    public function down(): void
    {
        Schema::table('diets', function (Blueprint $table) {
            $table->dropColumn(['transfer_destination', 'transfer_meals', 'transfer_date']);
        });
    }
};
