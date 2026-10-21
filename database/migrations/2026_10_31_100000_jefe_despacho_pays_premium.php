<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El Premium lo paga el operador del despacho (NUT-RN-003-17, SCRUM-9): el jefe de despacho
 * reporta el pago (manageSubscription) y el admin lo aprueba.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::firstOrCreate(['name' => 'manageSubscription', 'guard_name' => 'web']);
        Role::where('guard_name', 'web')->where('name', 'jefe_despacho')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->where('name', 'jefe_despacho')->first()?->revokePermissionTo('manageSubscription');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
