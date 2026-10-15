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
            'generateFiles',
            'viewUsers',
            'createUsers',
            'editUsers',
            'deleteUsers',
            'manageRoles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles
        $nutricionista = Role::firstOrCreate(['name' => 'nutricionista']);
        $despachador   = Role::firstOrCreate(['name' => 'despachador']);
        $admin         = Role::firstOrCreate(['name' => 'admin']);

        // Admin: todos los permisos
        $admin->syncPermissions(Permission::all());

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

        // Contraseña del administrador: SEED_USER_PASSWORD en .env, o aleatoria (se imprime una vez)
        $password = env('SEED_USER_PASSWORD') ?: Str::password(16);
        if (! env('SEED_USER_PASSWORD')) {
            $this->command?->warn("Contraseña generada para el administrador: {$password}");
        }

        // Crear usuario Nutricionista
        $userNutricionista = User::firstOrCreate(
            ['email' => 'camilo@gmail.com'],
            [
                'name' => 'Nutricionista Principal',
                'password' => Hash::make('nutricionista')
            ]
        );

        $userNutricionista->assignRole($nutricionista);

        // Crear usuario Despachador
        $userDespachador = User::firstOrCreate(
            ['email' => 'mariana@gmail.com'],
            [
                'name' => 'Despachador Principal',
                'password' => Hash::make('despachador')
            ]
        );

        $userDespachador->assignRole($despachador);

        // Crear usuario Administrador (contraseña: admin1234 o la generada arriba)
        $userAdmin = User::firstOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@nutritrack.com')],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin1234') // Cambia esto a $password si deseas usar la contraseña generada
            ]
        );

        $userAdmin->assignRole($admin);
    }
}