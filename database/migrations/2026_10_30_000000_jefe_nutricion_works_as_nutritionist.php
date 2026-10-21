<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El jefe de nutrición hace lo mismo que el nutricionista y, además, descarga los reportes y
 * crea nutricionistas (config security.assignable_roles). Conserva precios y suscripción.
 */
return new class extends Migration
{
    private const NUTRITIONIST = ['createDiets', 'editDiets', 'viewDiets', 'viewReports', 'confirmReception',
        'viewDispatches', 'viewActiveDiets', 'continueDiets'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect([...self::NUTRITIONIST, 'generateFiles'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        Role::where('guard_name', 'web')->where('name', 'jefe_nutricion')->first()?->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->where('name', 'jefe_nutricion')->first()
            ?->revokePermissionTo(['createDiets', 'editDiets', 'confirmReception', 'viewDispatches', 'viewActiveDiets', 'continueDiets']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
