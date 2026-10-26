<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:cleanup {--months= : Número de meses a conservar (por defecto 6)} {--dry-run : Simular eliminación sin ejecutarla}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia registros de auditoría antiguos según el período de retención configurado';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Iniciando limpieza de registros de auditoría...');

            // Obtener configuración
            $monthsToKeep = (int) ($this->option('months') ?: config('centinela.audit.retention_months', 6));
            $dryRun = $this->option('dry-run');
            $tableName = config('audit.drivers.database.table', 'audits');

            if (! is_string($tableName) || ! preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
                $this->error('Nombre de tabla de auditoría inválido en la configuración.');

                return Command::FAILURE;
            }

            // Calcular fecha límite
            $cutoffDate = Carbon::now()->subMonths($monthsToKeep);

            $this->info('Configuración:');
            $this->line("  - Tabla: {$tableName}");
            $this->line("  - Período de retención: {$monthsToKeep} meses");
            $this->line('  - Fecha límite: '.$cutoffDate->format('Y-m-d H:i:s'));
            $this->line('  - Modo: '.($dryRun ? 'SIMULACIÓN' : 'EJECUCIÓN REAL'));
            $this->newLine();

            // Contar registros a eliminar
            $recordsToDelete = DB::table($tableName)
                ->where('created_at', '<', $cutoffDate)
                ->count();

            if ($recordsToDelete === 0) {
                $this->info('No hay registros de auditoría para eliminar.');

                // Registrar en logs para tracking
                Log::info('Limpieza de auditoría completada', [
                    'records_deleted' => 0,
                    'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
                    'retention_months' => $monthsToKeep,
                    'status' => 'No records to delete',
                ]);

                return Command::SUCCESS;
            }

            $this->warn("Se encontraron {$recordsToDelete} registro(s) de auditoría para eliminar.");

            // Obtener estadísticas antes de eliminar
            $stats = $this->getAuditStatistics($tableName, $cutoffDate);
            $this->displayStatistics($stats);

            if ($dryRun) {
                $this->info("\n[SIMULACIÓN] No se eliminaron registros (use sin --dry-run para ejecutar).");

                return Command::SUCCESS;
            }

            // Confirmar si es ejecución interactiva
            if ($this->input->isInteractive()) {
                if (! $this->confirm('¿Desea continuar con la eliminación?', true)) {
                    $this->warn('Operación cancelada por el usuario.');

                    return Command::SUCCESS;
                }
            }

            // Ejecutar eliminación en lotes para evitar problemas de memoria
            $batchSize = 1000;
            $deletedTotal = 0;

            $this->info("\nEliminando registros en lotes de {$batchSize}...");
            $progressBar = $this->output->createProgressBar($recordsToDelete);

            while (true) {
                $deleted = DB::table($tableName)
                    ->where('created_at', '<', $cutoffDate)
                    ->limit($batchSize)
                    ->delete();

                if ($deleted === 0) {
                    break;
                }

                $deletedTotal += $deleted;
                $progressBar->advance($deleted);

                // Pequeña pausa para no sobrecargar la base de datos
                usleep(100000); // 100ms
            }

            $progressBar->finish();
            $this->newLine(2);

            // Optimizar tabla después de la eliminación masiva
            $this->info('Optimizando tabla...');
            DB::statement("OPTIMIZE TABLE `{$tableName}`");

            // Registrar en logs
            Log::info('Limpieza de auditoría completada', [
                'records_deleted' => $deletedTotal,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
                'retention_months' => $monthsToKeep,
            ]);

            $this->info('✓ Limpieza completada exitosamente');
            $this->line("  - Registros eliminados: {$deletedTotal}");
            $this->line("  - Tabla optimizada: {$tableName}");

            // Mostrar estadísticas finales
            $this->newLine();
            $finalStats = $this->getFinalStatistics($tableName);
            $this->info('Estadísticas actuales de la tabla:');
            $this->line('  - Total de registros: '.number_format($finalStats['total']));
            $this->line('  - Espacio liberado estimado: '.$this->estimateSpaceFreed($deletedTotal));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error durante la limpieza de auditoría: '.$e->getMessage());
            Log::error('Error en limpieza de auditoría: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Obtener estadísticas de los registros a eliminar
     */
    private function getAuditStatistics(string $tableName, Carbon $cutoffDate): array
    {
        $stats = [
            'by_event' => DB::table($tableName)
                ->select('event', DB::raw('COUNT(*) as count'))
                ->where('created_at', '<', $cutoffDate)
                ->groupBy('event')
                ->get()
                ->pluck('count', 'event')
                ->toArray(),

            'by_model' => DB::table($tableName)
                ->select('auditable_type', DB::raw('COUNT(*) as count'))
                ->where('created_at', '<', $cutoffDate)
                ->groupBy('auditable_type')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->pluck('count', 'auditable_type')
                ->toArray(),

            'oldest_record' => DB::table($tableName)
                ->where('created_at', '<', $cutoffDate)
                ->min('created_at'),

            'newest_record' => DB::table($tableName)
                ->where('created_at', '<', $cutoffDate)
                ->max('created_at'),
        ];

        return $stats;
    }

    /**
     * Mostrar estadísticas en formato legible
     */
    private function displayStatistics(array $stats): void
    {
        $this->newLine();
        $this->info('Desglose por tipo de evento:');
        foreach ($stats['by_event'] as $event => $count) {
            $this->line("  - {$event}: ".number_format($count));
        }

        $this->newLine();
        $this->info('Top modelos con más registros a eliminar:');
        foreach ($stats['by_model'] as $model => $count) {
            $modelName = class_basename($model);
            $this->line("  - {$modelName}: ".number_format($count));
        }

        $this->newLine();
        $this->info('Rango de fechas:');
        $this->line('  - Registro más antiguo: '.$stats['oldest_record']);
        $this->line('  - Registro más reciente: '.$stats['newest_record']);
    }

    /**
     * Obtener estadísticas finales de la tabla
     */
    private function getFinalStatistics(string $tableName): array
    {
        return [
            'total' => DB::table($tableName)->count(),
            'oldest' => DB::table($tableName)->min('created_at'),
            'newest' => DB::table($tableName)->max('created_at'),
        ];
    }

    /**
     * Estimar espacio liberado
     */
    private function estimateSpaceFreed(int $recordsDeleted): string
    {
        // Estimación aproximada: cada registro de auditoría ocupa ~2KB en promedio
        $bytesFreed = $recordsDeleted * 2048;

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $bytesFreed;

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
