<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El jefe de despacho tiene «Facturación y precios» (managePrices, función Premium prices):
 * digita los precios de sus empresas y descarga la facturación del mes.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::firstOrCreate(['name' => 'managePrices', 'guard_name' => 'web']);
        Role::where('guard_name', 'web')->where('name', 'jefe_despacho')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->where('name', 'jefe_despacho')->first()?->revokePermissionTo('managePrices');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
