<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipo de dieta para facturación (claves de config/billing.php → types), por versión
        Schema::table('diet_histories', function (Blueprint $table) {
            $table->string('billing_type', 40)->nullable()->after('specifications');
        });

        // Precio por bandeja de cada tipo × servicio, con vigencia desde una fecha.
        // El precio aplicable a un día es el de mayor valid_from <= ese día.
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->string('billing_type', 40);   // config('billing.types')
            $table->string('service', 30);        // config('billing.services')
            $table->decimal('price', 12, 2);      // COP por bandeja
            $table->date('valid_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['billing_type', 'service', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');

        Schema::table('diet_histories', function (Blueprint $table) {
            $table->dropColumn('billing_type');
        });
    }
};
