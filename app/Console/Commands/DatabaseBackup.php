<?php

namespace App\Console\Commands;

use App\Support\Configuration\ConfigurationService;
use App\Support\Database\DatabaseBackupSkipGuard;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup
                            {--email= : Email address to send the backup}
                            {--skip-if-recent : Omitir si ya existe un respaldo reciente (schedule)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un respaldo de la base de datos y lo envía por correo electrónico';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('skip-if-recent')) {
            $guard = app(DatabaseBackupSkipGuard::class);
            if ($guard->shouldSkipScheduledBackup()) {
                $minutes = $guard->minutesSinceLatestBackupFile();
                $message = 'Respaldo programado omitido: ya existe un respaldo reciente'
                    .($minutes !== null ? " (hace {$minutes} min)." : '.');

                $this->info($message);
                Log::info($message);

                return Command::SUCCESS;
            }
        }

        try {
            $this->info('Iniciando respaldo de la base de datos...');

            // Obtener configuración de la base de datos
            $dbConnection = config('database.default');
            $dbConfig = config("database.connections.{$dbConnection}");

            $dbHost = $dbConfig['host'];
            $dbPort = $dbConfig['port'];
            $dbName = $dbConfig['database'];
            // Permitir uso de credenciales dedicadas para respaldo desde .env
            $dedicatedUser = config('services.backup.db_username');
            $dedicatedPassword = config('services.backup.db_password');
            $dbUser = $dedicatedUser ?: $dbConfig['username'];
            $dbPassword = $dedicatedPassword ?: $dbConfig['password'];

            if ($dedicatedUser) {
                $this->info('Usando usuario dedicado para respaldo de base de datos.');
            }

            // Obtener ruta del binario mysqldump
            $mysqldumpPath = 'mysqldump';
            if ($customPath = config('centinela.backup.db_dump_path') ?: config('services.backup.dump_path')) {
                $mysqldumpPath = rtrim((string) $customPath, '\\/').DIRECTORY_SEPARATOR.'mysqldump';
                $this->info("Usando mysqldump desde: {$customPath}");
            }

            // Crear nombre del archivo con fecha y hora
            $timestamp = Carbon::now()->format('Y-m-d_His');
            $fileName = "backup_{$dbName}_{$timestamp}.sql";
            $backupPath = storage_path("app/backups/{$fileName}");
            $errorPath = storage_path("app/backups/{$fileName}.err");

            // Crear directorio de backups si no existe
            if (! is_dir(storage_path('app/backups'))) {
                mkdir(storage_path('app/backups'), 0755, true);
            }

            // Ejecutar mysqldump con salida de errores separada
            // Nota: Las redirecciones > y 2> deben estar fuera de escapeshellarg en Windows
            $command = sprintf(
                '%s --user=%s --password=%s --host=%s --port=%s --single-transaction %s > "%s" 2> "%s"',
                escapeshellarg($mysqldumpPath),
                escapeshellarg($dbUser),
                escapeshellarg($dbPassword),
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbName),
                $backupPath,
                $errorPath
            );

            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                $errorOutput = file_exists($errorPath) ? file_get_contents($errorPath) : 'Sin detalles de error';
                @unlink($errorPath);
                throw new \Exception('Error al ejecutar mysqldump: '.$errorOutput);
            }

            // Limpiar archivo de errores si todo salió bien
            @unlink($errorPath);

            // Verificar que el archivo se creó correctamente
            if (! file_exists($backupPath) || filesize($backupPath) === 0) {
                throw new \Exception('El archivo de respaldo no se creó correctamente o está vacío');
            }

            $fileSize = $this->formatBytes(filesize($backupPath));
            $this->info("Respaldo creado exitosamente: {$fileName} ({$fileSize})");

            // Comprimir el archivo
            $zipFileName = str_replace('.sql', '.zip', $fileName);
            $zipPath = storage_path("app/backups/{$zipFileName}");

            $zip = new \ZipArchive;
            if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
                $zip->addFile($backupPath, $fileName);
                $zip->close();

                // Eliminar el archivo SQL sin comprimir
                unlink($backupPath);

                $zipSize = $this->formatBytes(filesize($zipPath));
                $this->info("Archivo comprimido: {$zipFileName} ({$zipSize})");

                $backupPath = $zipPath;
                $fileName = $zipFileName;
            } else {
                $this->warn('No se pudo comprimir el archivo, se enviará sin comprimir');
            }

            // Detectar ambiente
            $environment = config('app.env');
            $isProduction = $environment === 'production';

            $this->info("Ambiente detectado: {$environment}".($isProduction ? ' (PRODUCCIÓN)' : ' (desarrollo)'));

            // Enviar por correo electrónico
            $optionEmail = $this->option('email');
            $emailAddress = (is_string($optionEmail) && trim($optionEmail) !== '')
                ? trim($optionEmail)
                : app(ConfigurationService::class)->backupNotificationEmail();

            if ($emailAddress) {
                $this->info("Enviando respaldo por correo a: {$emailAddress}");

                Mail::send('emails.backup.database', [
                    'isProduction' => $isProduction,
                    'environment' => $environment,
                    'timestamp' => Carbon::now()->format('d/m/Y H:i:s'),
                    'database' => $dbName,
                    'fileName' => $fileName,
                    'fileSize' => $this->formatBytes(filesize($backupPath)),
                    'hostname' => gethostname(),
                    'retentionDays' => (int) config('centinela.backup.retention_days', 7),
                ], function ($message) use ($emailAddress, $backupPath, $dbName, $isProduction, $environment) {
                    $envLabel = $isProduction ? 'PRODUCCIÓN' : strtoupper($environment);
                    $message->to($emailAddress)
                        ->subject("Respaldo DB [{$envLabel}] - {$dbName} - ".Carbon::now()->format('d/m/Y'))
                        ->attach($backupPath);
                });

                $this->info('Correo enviado exitosamente');
            } else {
                $this->warn('No se especificó dirección de correo. Use --email o configure BACKUP_EMAIL_ADDRESS en .env');
            }

            // Limpiar respaldos antiguos (mantener solo los últimos 7 días)
            $this->cleanOldBackups();

            Log::info("Respaldo de base de datos completado: {$fileName}", [
                'environment' => $environment,
                'is_production' => $isProduction,
                'database' => $dbName,
                'file_size' => filesize($backupPath),
            ]);
            $this->info('¡Proceso de respaldo completado exitosamente!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error al crear el respaldo: '.$e->getMessage());
            Log::error('Error en respaldo de base de datos: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Limpiar respaldos antiguos
     */
    private function cleanOldBackups(): void
    {
        $backupPath = storage_path('app/backups');
        $files = glob($backupPath.'/backup_*');

        $daysToKeep = (int) config('centinela.backup.retention_days', 7);
        $cutoffTime = Carbon::now()->subDays($daysToKeep)->timestamp;

        $deletedCount = 0;
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                unlink($file);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->info("Se eliminaron {$deletedCount} respaldo(s) antiguo(s)");
        }
    }

    /**
     * Formatear bytes a formato legible
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
