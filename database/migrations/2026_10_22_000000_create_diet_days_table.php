<?php

use App\Models\Diet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Día de servicio de una dieta (diet_days): autorización para despachar ese día
 * (creación o «Continuar dieta») y cierre del día cuando se reciben todos sus
 * tiempos de comida (Completada). Solo los días completados se facturan.
 *
 * Las dietas abiertas al migrar quedan autorizadas para hoy, para no bloquear
 * el servicio del día; desde mañana necesitan «Continuar dieta».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diet_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diet_id')->constrained()->cascadeOnDelete();
            $table->date('service_date');
            $table->string('authorized_via', 20)->nullable(); // creacion | continuacion | migracion
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_history_id')->nullable()->constrained('diet_histories')->nullOnDelete();
            $table->timestamps();

            $table->unique(['diet_id', 'service_date']);
            $table->index(['service_date', 'completed_at']);
        });

        $today = now()->toDateString();
        $rows = DB::table('diets')->whereIn('status', Diet::OPEN_STATUSES)->pluck('id')
            ->map(fn ($id) => [
                'diet_id' => $id,
                'service_date' => $today,
                'authorized_via' => 'migracion',
                'authorized_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        foreach ($rows->chunk(500) as $chunk) {
            DB::table('diet_days')->insert($chunk->all());
        }

        // Permiso «Continuar dieta»: nutricionista, enfermería y admin. Los demás roles, desde /admin/roles.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::firstOrCreate(['name' => 'continueDiets', 'guard_name' => 'web']);
        Role::where('guard_name', 'web')->whereIn('name', ['nutricionista', 'enfermeria', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('diet_days');

        DB::table('diets')->where('status', Diet::COMPLETED)->update(['status' => Diet::ACTIVE]);

        Permission::where('name', 'continueDiets')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
