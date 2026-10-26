<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessEmailQueue extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'queue:process-emails';

    /**
     * La descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Procesa un trabajo pendiente de la cola default (queue:work --once)';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle()
    {
        try {
            $this->info('Iniciando procesamiento de colas de correo...');

            // Verificar que la conexión de queue esté configurada
            $connection = config('queue.default');
            if (! $connection) {
                $this->error('QUEUE_CONNECTION no está configurada en .env');

                return Command::FAILURE;
            }

            // Utiliza el worker nativo de Laravel para procesar la cola
            $this->info("Ejecutando el worker de Laravel (conexión: {$connection})...");
            $exitCode = Artisan::call('queue:work', [
                '--once' => true,
                '--queue' => 'default',
                '--tries' => 3,
                '--timeout' => 60,
            ]);

            if ($exitCode === 0) {
                $this->info('Los correos electrónicos en cola han sido procesados correctamente.');
            } else {
                $this->error("Se produjo un error al procesar la cola. Código de salida: {$exitCode}");
            }

            return $exitCode;
        } catch (\Exception $e) {
            $this->error('Error fatal: '.$e->getMessage());
            \Log::error('Error en queue:process-emails: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
