<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Backup\BackupDestination\BackupDestination;
use ZipArchive;

/**
 * Prueba de restauración (semanal): abre el respaldo más reciente de cada destino y comprueba
 * que sea reciente, que esté cifrado (en producción), que la contraseña lo abra y que el volcado
 * de la base contenga las tablas de dietas. No restaura sobre la base en uso.
 * La restauración completa en un entorno de pruebas se documenta en docs/seguridad/cumplimiento.md.
 */
class VerifyBackup extends Command
{
    protected $signature = 'backups:verify';

    protected $description = 'Verifica que el último respaldo exista, esté cifrado y contenga la base de datos';

    public function handle(): int
    {
        $problems = [];
        $checked = [];

        foreach (config('backup.backup.destination.disks', []) as $disk) {
            $backup = BackupDestination::create($disk, config('backup.backup.name'))->newestBackup();

            if (! $backup) {
                $problems[] = "{$disk}: no hay respaldos.";

                continue;
            }

            $ageHours = (int) $backup->date()->diffInHours(now()); // Carbon 3 devuelve float
            if ($ageHours > config('security.backups.max_age_hours')) {
                $problems[] = "{$disk}: el último respaldo tiene {$ageHours} h.";
            }

            $result = $this->inspect($backup->stream());
            $checked[] = ['disk' => $disk, 'path' => $backup->path(), 'date' => $backup->date()->toDateTimeString()] + $result;
            array_push($problems, ...array_map(fn ($p) => "{$disk}: {$p}", $result['problems']));
        }

        AuditLogger::log('backup_verified', $problems ? 'Verificación de respaldo con problemas' : 'Respaldo verificado', null, ['backups' => $checked, 'problems' => $problems]);

        if ($problems) {
            Log::error('Verificación de respaldo fallida', $problems);
            foreach ($problems as $p) {
                $this->error($p);
            }

            return self::FAILURE;
        }

        $this->info('Respaldo verificado: '.collect($checked)->map(fn ($c) => "{$c['disk']} ({$c['date']})")->implode(', '));

        return self::SUCCESS;
    }

    /** Lee el volcado por partes buscando CREATE TABLE de las tablas de dietas; null si no se pudo leer */
    private function dumpHasDietTables(ZipArchive $zip, string $dump): ?bool
    {
        $stream = $zip->getStream($dump);
        if (! $stream) {
            return null;
        }

        $tail = '';
        $read = false;
        $found = [];
        while (! feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false || ($chunk === '' && ! $read)) {
                break;
            }
            $read = true;
            preg_match_all('/CREATE TABLE [`"]?(diets|diet_histories|diet_dispatches)[`"]?/', $tail.$chunk, $m);
            $found += array_flip($m[1]);
            if (count($found) === 3) {
                break;
            }
            $tail = substr($chunk, -64);
        }
        fclose($stream);

        return $read ? count($found) === 3 : null;
    }

    /** @param  resource  $stream */
    private function inspect($stream): array
    {
        $problems = [];
        $tmp = tempnam(sys_get_temp_dir(), 'nt-backup-');
        file_put_contents($tmp, $stream);

        try {
            $zip = new ZipArchive;
            if ($zip->open($tmp) !== true) {
                return ['encrypted' => null, 'dump' => null, 'problems' => ['el archivo no es un zip válido.']];
            }

            $dumps = [];
            $encrypted = $zip->numFiles > 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);
                $encrypted = $encrypted && ($stat['encryption_method'] ?? ZipArchive::EM_NONE) !== ZipArchive::EM_NONE;
                if (str_contains($stat['name'], 'db-dumps/')) {
                    $dumps[] = $stat['name'];
                }
            }

            if (! $encrypted && config('security.backups.require_encryption')) {
                $problems[] = 'el respaldo no está cifrado (define BACKUP_ARCHIVE_PASSWORD).';
            }

            if ($password = config('backup.backup.password')) {
                $zip->setPassword($password);
            }

            $dump = $dumps[0] ?? null;
            if (! $dump) {
                $problems[] = 'el respaldo no incluye el volcado de la base de datos.';
            } elseif (str_ends_with($dump, '.sql')) {
                $found = $this->dumpHasDietTables($zip, $dump);
                if ($found === null) {
                    $problems[] = 'no se pudo leer el volcado (¿contraseña incorrecta?).';
                } elseif (! $found) {
                    $problems[] = 'el volcado no contiene las tablas de dietas.';
                }
            }

            $zip->close();

            return ['encrypted' => $encrypted, 'dump' => $dump, 'problems' => $problems];
        } finally {
            @unlink($tmp);
        }
    }
}
