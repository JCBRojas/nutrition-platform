<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\EmailDomain;
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
            'manageEmailDomains',
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

        // Asignar permisos a Jefe de nutrición: lo mismo que el nutricionista y, además, descarga
        // reportes y crea nutricionistas; conserva precios y suscripción
        $jefe->syncPermissions([
            'createDiets',
            'editDiets',
            'viewDiets',
            'viewReports',
            'confirmReception',
            'viewDispatches',
            'viewActiveDiets',
            'continueDiets',
            'generateFiles',
            'managePrices',
            'manageSubscription',
            'viewUsers',   // solo nutricionistas (config security.assignable_roles)
            'createUsers',
        ]);

        // Asignar permisos a Jefe de despacho: administra sus empresas y los despachadores de ellas (NUT-RN-003)
        $jefeDespacho->syncPermissions([
            'manageCompanies',
            'viewCompanies',
            'viewDiets',
            'viewDispatches', // Despachos del día (NUT-RN-003-25)
            'viewReports',
            'generateFiles',
            'viewUsers',      // solo despachadores de sus empresas (config security.assignable_roles)
            'createUsers',
            'editUsers',
            'manageSubscription', // paga el Premium del operador del despacho (NUT-RN-003-17)
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

        // NUT-RN-002-07: en la instalación inicial (lista vacía) se autoriza el dominio del admin para que
        // no quede sin acceso. Si la lista ya tiene dominios, no se toca (el admin la administra).
        if (config('security.seed_admin_email_domain') && ! EmailDomain::query()->exists()) {
            EmailDomain::create(['domain' => EmailDomain::of($userAdmin->email), 'is_active' => true]);
        }

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

        // Usuarios demo (NUT-RN-003-05, -20, -24): nutrición con «Todas»; el jefe de despacho administra
        // la empresa predeterminada y el despachador la tiene como empresa específica
        User::whereIn('id', [$userNutricionista->id, $userJefe->id])->update(['all_companies' => true]);
        if ($default = Company::default()) {
            $default->manager_id ??= $userJefeDespacho->id;
            $default->save();
            $userDespachador->companies()->syncWithoutDetaching([$default->id]);
        }
    }
}
