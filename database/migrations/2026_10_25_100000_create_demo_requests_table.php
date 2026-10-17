<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Solicitudes de demo enviadas desde el login (formulario público).
 * Las ve el admin en /admin/solicitudes-demo (permiso viewDemoRequests).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('institution', 160);
            $table->string('position', 120)->nullable();
            $table->string('email', 160);
            $table->string('phone', 30);
            $table->unsignedInteger('beds')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new'); // new | contacted | discarded
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::firstOrCreate(['name' => 'viewDemoRequests', 'guard_name' => 'web']);
        Role::where('guard_name', 'web')->where('name', 'admin')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
        Permission::where('name', 'viewDemoRequests')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
