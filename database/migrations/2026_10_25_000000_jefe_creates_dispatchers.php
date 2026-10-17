<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El jefe de nutrición puede ver y crear usuarios con rol despachador
 * (la restricción de roles está en config security.assignable_roles).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect(['viewUsers', 'createUsers'])->map(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        Role::where('guard_name', 'web')->where('name', 'jefe_nutricion')->first()?->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->where('name', 'jefe_nutricion')->first()?->revokePermissionTo(['viewUsers', 'createUsers']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
