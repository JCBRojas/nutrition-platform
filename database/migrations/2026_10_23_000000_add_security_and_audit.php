<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seguridad y cumplimiento:
 * - users.privacy_accepted_at / privacy_version: aceptación de la política de tratamiento de datos.
 * - audit_logs: registro de auditoría inmutable (accesos, cambios de usuarios/roles/precios,
 *   exportaciones y consultas de historial). Sin datos clínicos en la descripción.
 * - Permiso viewAuditLog para el rol admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('privacy_accepted_at')->nullable()->after('whatsapp_opt_in');
            $table->string('privacy_version', 20)->nullable()->after('privacy_accepted_at');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 50);
            $table->string('description', 255);
            $table->nullableMorphs('subject');
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
            $table->index('created_at');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::firstOrCreate(['name' => 'viewAuditLog', 'guard_name' => 'web']);
        Role::where('guard_name', 'web')->where('name', 'admin')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['privacy_accepted_at', 'privacy_version']);
        });

        Permission::where('name', 'viewAuditLog')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
