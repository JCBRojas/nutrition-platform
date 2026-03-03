<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permisos
        $permissions = [
            'createDiets',
            'editDiets',
            'viewDiets',
            'viewReports',
            'confirmDelivery',
            'generateFiles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles
        $nutricionista = Role::firstOrCreate(['name' => 'nutricionista']);
        $despachador   = Role::firstOrCreate(['name' => 'despachador']);

        // Asignar permisos a Nutricionista
        $nutricionista->syncPermissions([
            'createDiets',
            'editDiets',
            'viewDiets',
            'viewReports',
        ]);

        // Asignar permisos a Despachador
        $despachador->syncPermissions([
            'viewDiets',
            'confirmDelivery',
            'generateFiles',
        ]);

        $user = User::find(1);
        if ($user) {
            $user->givePermissionTo('createDiets');
        }

    }
}