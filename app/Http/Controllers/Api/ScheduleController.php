<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ScheduleController extends Controller
{
    /**
     * Ejecutar schedule:run (solo admins autenticados por token)
     */
    public function runSchedule(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado: solo administradores',
            ], 403);
        }
        try {
            Log::info('API: Ejecutando schedule:run por admin', ['user_id' => $user->id]);
            config(['centinela.schedule.triggered_via_http' => true]);
            try {
                $exitCode = Artisan::call('schedule:run');
            } finally {
                config(['centinela.schedule.triggered_via_http' => false]);
            }
            $output = Artisan::output();
            Log::info('API: schedule:run ejecutado', [
                'exit_code' => $exitCode,
                'output' => $output,
            ]);

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'schedule:run ejecutado correctamente' : 'Error al ejecutar schedule:run',
                'exit_code' => $exitCode,
                'output' => $output,
            ], $exitCode === 0 ? 200 : 500);
        } catch (\Exception $e) {
            Log::error('API: Error al ejecutar schedule:run', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar schedule:run',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesar la cola de trabajos pendientes
     */
    public function processQueue(Request $request): JsonResponse
    {
        $queue = (string) $request->input('queue', 'default');
        if ($queue !== 'default') {
            return response()->json([
                'success' => false,
                'message' => 'Solo la cola default está permitida vía API.',
            ], 422);
        }

        try {
            Log::info('API: Iniciando procesamiento de cola de trabajos', ['queue' => $queue]);

            $maxJobs = min((int) ($request->input('max', 10)), 50);
            $jobsProcessed = 0;
            $outputs = [];
            $exitCode = 0;

            for ($i = 0; $i < $maxJobs; $i++) {
                $code = Artisan::call('queue:work', [
                    '--once' => true,
                    '--sleep' => 1,
                    '--queue' => $queue,
                ]);
                $output = Artisan::output();
                $outputs[] = $output;

                if ($code !== 0) {
                    $exitCode = $code;
                    break;
                }

                // Si el output está vacío o indica "sin jobs", salir del bucle
                // queue:work --once con 0 jobs produce exit code 0 y output vacío + sleep.
                // Con —sleep=1 cada iteración sin job tarda ~1s.
                if (
                    empty(trim($output))
                    || str_contains($output, 'No queueable jobs')
                    || str_contains($output, 'Nothing to process')
                ) {
                    break;
                }

                $jobsProcessed++;
                usleep(200000); // 0.2 segundos entre trabajos
            }

            Log::info('API: Cola de trabajos procesada', [
                'exit_code' => $exitCode,
                'jobs_processed' => $jobsProcessed,
            ]);

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? "Se procesaron $jobsProcessed trabajos de la cola" : 'Error al procesar cola',
                'exit_code' => $exitCode,
                'jobs_processed' => $jobsProcessed,
                'output' => $outputs,
            ], $exitCode === 0 ? 200 : 500);
        } catch (\Exception $e) {
            Log::error('API: Error al procesar cola de trabajos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar cola de trabajos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ejecutar respaldo de base de datos
     */
    public function runDatabaseBackup(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            Log::info('API: Iniciando respaldo de base de datos');

            $email = config('centinela.backup.email_address') ?: config('services.backup.email_address');

            $exitCode = Artisan::call('db:backup', [
                '--email' => $email,
            ]);
            $output = Artisan::output();
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            Log::info('API: Respaldo de base de datos completado', [
                'exit_code' => $exitCode,
                'duration_ms' => $durationMs,
            ]);

            // Detectar archivo generado mas reciente
            $file = null;
            $sizeMb = null;
            $files = glob(storage_path('app/backups/backup_*')) ?: [];
            if (! empty($files)) {
                usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
                $latest = $files[0];
                $file = basename($latest);
                $sizeMb = round(filesize($latest) / (1024 * 1024), 2);
            }

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'Respaldo completado correctamente' : 'Error al crear respaldo',
                'exit_code' => $exitCode,
                'output' => $output,
                'duration_ms' => $durationMs,
                'file' => $file,
                'size_mb' => $sizeMb,
            ], $exitCode === 0 ? 200 : 500);
        } catch (\Exception $e) {
            Log::error('API: Error al crear respaldo de base de datos', [
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear respaldo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Estado general del sistema de tareas programadas
     */
    public function status(): JsonResponse
    {
        try {
            $jobsTable = Schema::hasTable('jobs');
            $failedJobsTable = Schema::hasTable('failed_jobs');

            $status = [
                'queue_connection' => config('queue.default'),
                'queue_jobs_pending' => $jobsTable ? DB::table('jobs')->count() : null,
                'queue_failed_jobs' => $failedJobsTable ? DB::table('failed_jobs')->count() : null,
                'backup_email' => (config('centinela.backup.email_address') ?: config('services.backup.email_address')) ? 'configurado' : 'no configurado',
                'audit_enabled' => config('audit.enabled', true),
                'server_time' => now()->toDateTimeString(),
            ];

            return response()->json([
                'success' => true,
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificar estado de salud del scheduler
     *
     * @deprecated 4.1.2 Este endpoint fue eliminado de routes/api.php.
     *             La funcionalidad esta cubierta por GET /api/health (schedulerStatus).
     *             Se mantiene el metodo para evitar errores si alguien llama
     *             ScheduleController::checkHealth() directamente en tests.
     */
    public function checkHealth(): JsonResponse
    {
        try {
            Log::info('API: Verificando estado de salud del scheduler');

            $exitCode = Artisan::call('schedule:check-health', ['--no-interaction' => true]);
            $output = Artisan::output();

            Log::info('API: Verificación de salud completada', [
                'exit_code' => $exitCode,
                'output' => $output,
            ]);

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'Verificación completada' : 'Error en verificación de salud',
                'exit_code' => $exitCode,
                'output' => trim($output),
                'timestamp' => now()->toDateTimeString(),
            ], $exitCode === 0 ? 200 : 500);
        } catch (\Exception $e) {
            Log::error('API: Error al verificar salud del scheduler', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar salud del scheduler',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificar estado de salud del worker de colas.
     *
     * @deprecated 4.1.2 Este endpoint fue eliminado de routes/api.php.
     *             La funcionalidad esta cubierta por GET /api/health (workerStatus).
     *             Se mantiene el metodo para evitar errores si alguien llama
     *             ScheduleController::workerHealth() directamente en tests.
     */
    public function workerHealth(): JsonResponse
    {
        try {
            Log::info('API: Verificando estado de salud del worker');

            $exitCode = Artisan::call('worker:check-health', ['--no-interaction' => true]);
            $output = Artisan::output();

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'Worker saludable o en advertencia' : 'Worker no saludable',
                'exit_code' => $exitCode,
                'output' => trim($output),
                'timestamp' => now()->toDateTimeString(),
            ], $exitCode === 0 ? 200 : 500);
        } catch (\Exception $e) {
            Log::error('API: Error al verificar salud del worker', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar salud del worker',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
