<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Suscripción de la institución (plan básico gratis / premium mensual o anual) con pago manual:
 * - plan_prices: precio vigente de cada periodo del premium (lo edita el admin).
 * - subscription_payments: pagos reportados con su comprobante; el admin los aprueba o rechaza.
 * - subscriptions: periodos de premium activados (al aprobar un pago o a mano por el admin).
 * Permisos: manageSubscription (reportar pagos: admin y jefe de nutrición) y approvePayments (admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->string('period', 20)->unique(); // monthly | annual
            $table->decimal('price', 12, 2);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->string('period', 20);
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference', 100);
            $table->string('receipt_path')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_notes', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('plan', 20)->default('premium');
            $table->string('period', 20);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('payment_id')->nullable()->constrained('subscription_payments')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        $now = now();
        foreach (config('subscriptions.default_prices') as $period => $price) {
            \Illuminate\Support\Facades\DB::table('plan_prices')->insert(['period' => $period, 'price' => $price, 'created_at' => $now, 'updated_at' => $now]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $manage = Permission::firstOrCreate(['name' => 'manageSubscription', 'guard_name' => 'web']);
        $approve = Permission::firstOrCreate(['name' => 'approvePayments', 'guard_name' => 'web']);
        Role::where('guard_name', 'web')->where('name', 'admin')->first()?->givePermissionTo([$manage, $approve]);
        Role::where('guard_name', 'web')->where('name', 'jefe_nutricion')->first()?->givePermissionTo($manage);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('plan_prices');

        Permission::whereIn('name', ['manageSubscription', 'approvePayments'])->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
