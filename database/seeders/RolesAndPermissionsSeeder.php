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
            'continueDiets',
            'generateFiles',
            'viewUsers',
            'createUsers',
            'editUsers',
            'deleteUsers',
            'manageRoles',
            'managePrices',
            'viewAuditLog',
            'manageSubscription',
            'approvePayments',
            'viewDemoRequests',
            'manageCompanies',
            'viewCompanies',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Roles
        $nutricionista = Role::firstOrCreate(['name' => 'nutricionista', 'guard_name' => 'web']);
        $despachador = Role::firstOrCreate(['name' => 'despachador', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $jefe = Role::firstOrCreate(['name' => 'jefe_nutricion', 'guard_name' => 'web']);
        $jefeDespacho = Role::firstOrCreate(['name' => 'jefe_despacho', 'guard_name' => 'web']);

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
            'continueDiets',
        ]);

        // Asignar permisos a Jefe de nutrición
        $jefe->syncPermissions([
            'viewDiets',
            'viewReports',
            'generateFiles',
            'managePrices',
            'manageSubscription',
            'viewUsers',   // solo despachadores (config security.assignable_roles)
            'createUsers',
        ]);

        // Asignar permisos a Jefe de despacho: registra las empresas que atiende la cocina (NUT-RN-003)
        $jefeDespacho->syncPermissions([
            'manageCompanies',
            'viewCompanies',
            'viewDiets',
            'viewDispatches',
            'viewReports',
            'generateFiles',
        ]);

        // Asignar permisos a Despachador
        $despachador->syncPermissions([
            'viewDiets',
            'confirmDelivery',
            'viewDispatches',
            'generateFiles',
        ]);

        // Contraseña de los usuarios demo: SEED_USER_PASSWORD en .env, o aleatoria (se imprime una vez).
        // Nunca contraseñas fijas en el código.
        $password = env('SEED_USER_PASSWORD') ?: Str::password(16);
        if (! env('SEED_USER_PASSWORD')) {
            $this->command?->warn("Contraseña generada para los usuarios demo nuevos: {$password}");
        }

        // Crear usuario Nutricionista
        $userNutricionista = User::firstOrCreate(
            ['email' => 'camilo@gmail.com'],
            [
                'name' => 'Nutricionista Principal',
                'password' => Hash::make($password),
            ]
        );

        $userNutricionista->assignRole($nutricionista);

        // Crear usuario Despachador
        $userDespachador = User::firstOrCreate(
            ['email' => 'mariana@gmail.com'],
            [
                'name' => 'Despachador Principal',
                'password' => Hash::make($password),
            ]
        );

        $userDespachador->assignRole($despachador);

        // Crear usuario Administrador (contraseña: SEED_USER_PASSWORD o la generada arriba)
        $userAdmin = User::firstOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@nutritrack.com')],
            [
                'name' => 'Administrador',
                'password' => Hash::make($password),
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

        // Crear usuario Jefe de despacho (contraseña: SEED_USER_PASSWORD o la generada arriba)
        $userJefeDespacho = User::firstOrCreate(
            ['email' => env('SEED_JEFE_DESPACHO_EMAIL', 'jefe.despacho@nutritrack.com')],
            [
                'name' => 'Jefe de Despacho',
                'password' => Hash::make($password),
            ]
        );

        $userJefeDespacho->assignRole($jefeDespacho);

        // Usuarios demo: acceso a todas las empresas (NUT-RN-003-05)
        User::whereIn('id', [$userNutricionista->id, $userDespachador->id, $userJefe->id, $userJefeDespacho->id])
            ->update(['all_companies' => true]);
    }
}
