<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permiso viewActiveDiets: ver el panel de dietas activas (/dashboard/nutrition),
 * que antes exigía el rol nutricionista. Se da a nutricionista y admin; los demás
 * roles (p. ej. enfermería) lo reciben desde /admin/roles. No usa el seeder para no
 * sobrescribir los permisos ajustados en la matriz.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'viewActiveDiets', 'guard_name' => 'web']);

        Role::where('guard_name', 'web')->whereIn('name', ['nutricionista', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'viewActiveDiets')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
