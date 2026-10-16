<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Despachos de cocina: un registro por dieta × día × tiempo de comida.
 * El despachador lo crea al despachar; el nutricionista confirma la recepción.
 * Solo los despachos recibidos cuentan para la facturación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diet_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diet_history_id')->constrained()->cascadeOnDelete(); // versión despachada
            $table->date('service_date');
            $table->string('meal', 30); // clave de config/timeFood.php
            $table->foreignId('dispatched_by')->constrained('users');
            $table->timestamp('dispatched_at');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['diet_id', 'service_date', 'meal']);
            $table->index(['service_date', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diet_dispatches');
    }
};
