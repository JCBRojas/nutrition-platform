<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            'confirmReception',
            'viewDispatches',
            'viewActiveDiets',
            'generateFiles',
            'viewUsers',
            'createUsers',
            'editUsers',
            'deleteUsers',
            'manageRoles',
            'managePrices',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Roles
        $nutricionista = Role::firstOrCreate(['name' => 'nutricionista', 'guard_name' => 'web']);
        $despachador = Role::firstOrCreate(['name' => 'despachador', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $jefe = Role::firstOrCreate(['name' => 'jefe_nutricion', 'guard_name' => 'web']);

        // Admin: todos los permisos
        $admin->syncPermissions(Permission::all());

        // Asignar permisos a Nutricionista
        $nutricionista->syncPermissions([
            'createDiets',
            'editDiets',
            'viewDiets',
            'viewReports',
            'confirmReception',
            'viewDispatches',
            'viewActiveDiets',
        ]);

        // Asignar permisos a Jefe de nutrición
        $jefe->syncPermissions([
            'viewDiets',
            'viewReports',
            'generateFiles',
            'managePrices',
        ]);

        // Asignar permisos a Despachador
        $despachador->syncPermissions([
            'viewDiets',
            'confirmDelivery',
            'viewDispatches',
            'generateFiles',
        ]);

        // Contraseña del jefe de nutrición: SEED_USER_PASSWORD en .env, o aleatoria (se imprime una vez)
        $password = env('SEED_USER_PASSWORD') ?: Str::password(16);
        if (! env('SEED_USER_PASSWORD')) {
            $this->command?->warn("Contraseña generada para el jefe de nutrición (solo si el usuario es nuevo): {$password}");
        }

        // Crear usuario Nutricionista
        $userNutricionista = User::firstOrCreate(
            ['email' => 'camilo@gmail.com'],
            [
                'name' => 'Nutricionista Principal',
                'password' => Hash::make('nutricionista'),
            ]
        );

        $userNutricionista->assignRole($nutricionista);

        // Crear usuario Despachador
        $userDespachador = User::firstOrCreate(
            ['email' => 'mariana@gmail.com'],
            [
                'name' => 'Despachador Principal',
                'password' => Hash::make('despachador'),
            ]
        );

        $userDespachador->assignRole($despachador);

        // Crear usuario Administrador (contraseña: admin1234 o la generada arriba)
        $userAdmin = User::firstOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@nutritrack.com')],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin1234'), // Cambia esto a $password si deseas usar la contraseña generada
            ]
        );

        $userAdmin->assignRole($admin);

        // Crear usuario Jefe de nutrición (contraseña: SEED_USER_PASSWORD o la generada arriba)
        $userJefe = User::firstOrCreate(
            ['email' => env('SEED_JEFE_EMAIL', 'jefe@nutritrack.com')],
            [
                'name' => 'Jefe de Nutrición',
                'password' => Hash::make($password),
            ]
        );

        $userJefe->assignRole($jefe);
    }
}
