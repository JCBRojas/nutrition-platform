<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas usadas por DietService y DietsManager que no tenían migración.
     * `diet_histories.modify` ya existe en algunas BD (creada a mano): se omite si está.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('diet_histories', 'modify')) {
            Schema::table('diet_histories', function (Blueprint $table) {
                $table->enum('modify', ['si', 'no'])->default('no')->after('changes');
            });
        }

        if (! Schema::hasColumn('diets', 'confirmed')) {
            Schema::table('diets', function (Blueprint $table) {
                $table->boolean('confirmed')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('diets', function (Blueprint $table) {
            $table->dropColumn('confirmed');
        });

        Schema::table('diet_histories', function (Blueprint $table) {
            $table->dropColumn('modify');
        });
    }
};
