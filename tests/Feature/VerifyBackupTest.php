<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Prueba semanal de restauración (backups:verify): reciente, cifrado y con el volcado de dietas.
 */
class VerifyBackupTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Clave-Respaldo-2026';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['backup.backup.destination.disks' => ['local'], 'backup.backup.name' => 'NutriTrack', 'security.backups.require_encryption' => true]);
    }

    private function backup(?string $password, string $sql = "CREATE TABLE `diets` (id int);\nCREATE TABLE `diet_histories` (id int);\nCREATE TABLE `diet_dispatches` (id int);\n"): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('db-dumps/mysql-nutrition.sql', $sql);
        if ($password) {
            $zip->setPassword($password);
            $zip->setEncryptionName('db-dumps/mysql-nutrition.sql', ZipArchive::EM_AES_256);
        }
        $zip->close();

        Storage::disk('local')->put('NutriTrack/'.now()->format('Y-m-d-H-i-s').'.zip', file_get_contents($tmp));
        unlink($tmp);
    }

    public function test_an_encrypted_recent_backup_with_the_diet_tables_passes(): void
    {
        config(['backup.backup.password' => self::PASSWORD]);
        $this->backup(self::PASSWORD);

        $this->artisan('backups:verify')->assertSuccessful();
        $this->assertSame([], AuditLog::where('event', 'backup_verified')->firstOrFail()->meta['problems']);
    }

    public function test_an_unencrypted_backup_fails(): void
    {
        $this->backup(null);

        $this->artisan('backups:verify')->expectsOutputToContain('no está cifrado')->assertFailed();
    }

    public function test_a_wrong_password_fails(): void
    {
        config(['backup.backup.password' => 'otra-clave']);
        $this->backup(self::PASSWORD);

        $this->artisan('backups:verify')->expectsOutputToContain('no se pudo leer')->assertFailed();
    }

    public function test_a_dump_without_diet_tables_fails(): void
    {
        config(['backup.backup.password' => self::PASSWORD]);
        $this->backup(self::PASSWORD, "CREATE TABLE `users` (id int);\n");

        $this->artisan('backups:verify')->expectsOutputToContain('no contiene las tablas de dietas')->assertFailed();
    }

    public function test_missing_backups_fail(): void
    {
        $this->artisan('backups:verify')->expectsOutputToContain('no hay respaldos')->assertFailed();
    }
}
