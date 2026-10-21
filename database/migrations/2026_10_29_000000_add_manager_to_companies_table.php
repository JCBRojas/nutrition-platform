<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El jefe de despacho administra sus empresas (NUT-RN-003-20 a -24):
 * - companies.manager_id: jefe de despacho de la empresa (la predeterminada, al primero que exista)
 * - el jefe de despacho crea, ve y edita usuarios (de sus empresas)
 * - despachadores y jefes de despacho sin «Todas»: los despachadores con «Todas» pasan a la predeterminada
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $roleIds = fn (string $role) => DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $role)->where('roles.guard_name', 'web')
            ->where('model_type', (new \App\Models\User)->getMorphClass())
            ->pluck('model_id');

        $defaultId = DB::table('companies')->where('is_default', true)->value('id');
        $firstManager = $roleIds('jefe_despacho')->sort()->first();
        if ($defaultId && $firstManager) {
            DB::table('companies')->whereNull('manager_id')->update(['manager_id' => $firstManager]);
        }

        foreach ($roleIds('despachador') as $userId) {
            $hasSpecific = DB::table('company_user')->where('user_id', $userId)->exists();
            if (! $hasSpecific && $defaultId && DB::table('users')->where('id', $userId)->value('all_companies')) {
                DB::table('company_user')->insert(['company_id' => $defaultId, 'user_id' => $userId]);
            }
        }
        DB::table('users')->whereIn('id', $roleIds('despachador')->merge($roleIds('jefe_despacho')))->update(['all_companies' => false]);

        $permissions = collect(['viewUsers', 'createUsers', 'editUsers'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        Role::where('guard_name', 'web')->where('name', 'jefe_despacho')->first()?->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
        });

        Role::where('guard_name', 'web')->where('name', 'jefe_despacho')->first()?->revokePermissionTo(['viewUsers', 'createUsers', 'editUsers']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
