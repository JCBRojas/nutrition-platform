<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dominios de correo autorizados para iniciar sesión (NUT-RN-002, SCRUM-6). Una sola lista para
 * toda la plataforma; la administra el admin en /admin/dominios (permiso manageEmailDomains).
 * La tabla queda vacía: con la lista vacía no se restringe el acceso (NUT-RN-002-07).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 253)->unique(); // minúsculas, sin @
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::firstOrCreate(['name' => 'manageEmailDomains', 'guard_name' => 'web']);
        Role::where('guard_name', 'web')->where('name', 'admin')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('email_domains');
        Permission::where('name', 'manageEmailDomains')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
