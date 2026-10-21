<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Empresas atendidas por el despacho (NUT-RN-003, SCRUM-7):
 * - companies + empresa predeterminada (is_default) con las dietas existentes
 * - company_user + users.all_companies (los usuarios existentes quedan con «Todas»)
 * - rol jefe_despacho y permisos manageCompanies / viewCompanies
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            // NIT con dígito de verificación (900123456-8). Nulo solo en la predeterminada hasta completarla.
            $table->string('nit', 20)->nullable()->unique();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email', 160)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['company_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('all_companies')->default(false)->after('whatsapp_opt_in');
        });

        Schema::table('diets', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        $defaultId = DB::table('companies')->insertGetId([
            'name' => env('DEFAULT_COMPANY_NAME', 'Empresa principal'),
            'nit' => env('DEFAULT_COMPANY_NIT') ?: null,
            'is_active' => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('diets')->whereNull('company_id')->update(['company_id' => $defaultId]);
        // Hasta hoy todos veían todo: conservan el acceso a todas las empresas
        DB::table('users')->update(['all_companies' => true]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $manage = Permission::firstOrCreate(['name' => 'manageCompanies', 'guard_name' => 'web']);
        $view = Permission::firstOrCreate(['name' => 'viewCompanies', 'guard_name' => 'web']);

        Role::where('guard_name', 'web')->where('name', 'admin')->first()?->givePermissionTo([$manage, $view]);
        Role::firstOrCreate(['name' => 'jefe_despacho', 'guard_name' => 'web'])
            ->givePermissionTo([$manage, $view, ...Permission::where('guard_name', 'web')
                ->whereIn('name', ['viewDiets', 'viewDispatches', 'viewReports', 'generateFiles'])->get()]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('diets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('all_companies');
        });

        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');

        Role::where('guard_name', 'web')->where('name', 'jefe_despacho')->first()?->delete();
        Permission::where('guard_name', 'web')->whereIn('name', ['manageCompanies', 'viewCompanies'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
