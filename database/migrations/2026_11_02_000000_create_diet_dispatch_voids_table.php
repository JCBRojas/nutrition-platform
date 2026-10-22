<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Despachos sin recibir que se sacaron del flujo: el nutricionista reporta que no llegó
 * (not_arrived) o el despachador lo anula por error (cancelled). Guarda la copia del
 * despacho; el registro de diet_dispatches se borra para poder despacharlo de nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diet_dispatch_voids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diet_history_id')->constrained()->cascadeOnDelete(); // versión despachada
            $table->date('service_date');
            $table->string('meal', 30);
            $table->foreignId('dispatched_by')->constrained('users');
            $table->timestamp('dispatched_at');
            $table->string('kind', 20); // not_arrived | cancelled
            $table->string('reason', 255)->nullable();
            $table->foreignId('voided_by')->constrained('users');
            $table->timestamp('voided_at');
            $table->timestamps();

            $table->index(['service_date', 'meal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diet_dispatch_voids');
    }
};
