<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos creados desde /admin/roles quedaban con guard 'sanctum' (las rutas
 * usan auth:sanctum y Spatie tomaba ese guard por defecto), y al asignarles permisos
 * fallaba "There is no permission named … for guard sanctum". Los pasa a 'web'.
 * Si ya existe uno igual en 'web', se conservan sus asignaciones en ese y se borra el duplicado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');

        foreach (['roles' => 'role_id', 'permissions' => 'permission_id'] as $key => $pivotKey) {
            $table = $tables[$key];

            foreach (DB::table($table)->where('guard_name', 'sanctum')->get() as $row) {
                $web = DB::table($table)->where('guard_name', 'web')->where('name', $row->name)->first();

                if (! $web) {
                    DB::table($table)->where('id', $row->id)->update(['guard_name' => 'web']);

                    continue;
                }

                // Duplicado: mueve las asignaciones al registro 'web' y elimina el de 'sanctum'
                $pivots = $key === 'roles'
                    ? [$tables['model_has_roles'], $tables['role_has_permissions']]
                    : [$tables['model_has_permissions'], $tables['role_has_permissions']];

                foreach ($pivots as $pivot) {
                    foreach (DB::table($pivot)->where($pivotKey, $row->id)->get() as $link) {
                        $link = (array) $link;
                        $link[$pivotKey] = $web->id;
                        DB::table($pivot)->insertOrIgnore($link);
                    }
                    DB::table($pivot)->where($pivotKey, $row->id)->delete();
                }

                DB::table($table)->where('id', $row->id)->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Sin reversa: 'sanctum' nunca fue un guard válido para estos roles
    }
};
