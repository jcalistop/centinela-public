<?php

namespace App\Console\Commands;

use App\Models\PlatformAuthSession;
use Illuminate\Console\Command;

class CloseExpiredPlatformAuthSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:close-expired-auth-sessions {--minutes= : Límite desde started_at en minutos (default SESSION_LIFETIME)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cierra sesiones de autenticación expiradas (estadística de uso)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = (int) ($this->option('minutes') ?: config('session.lifetime', 120));
        if ($minutes <= 0) {
            $minutes = 120;
        }

        $threshold = now()->subMinutes($minutes);

        // Cierra por antigüedad de started_at aunque usage tracking esté off:
        // las filas abiertas siguen afectando la métrica del dashboard admin (SDD-086/087).
        $closedExpired = PlatformAuthSession::query()
            ->whereNull('ended_at')
            ->where('started_at', '<', $threshold)
            ->update([
                'ended_at' => now(),
                'end_reason' => 'timeout',
            ]);

        // Huérfanas: más de una abierta por usuario → dejar solo la más reciente.
        $orphanUserIds = PlatformAuthSession::query()
            ->whereNull('ended_at')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('count(*) > 1')
            ->pluck('user_id');

        $closedOrphans = 0;
        foreach ($orphanUserIds as $userId) {
            $keepId = PlatformAuthSession::query()
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->value('id');

            if ($keepId === null) {
                continue;
            }

            $closedOrphans += PlatformAuthSession::query()
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->where('id', '!=', $keepId)
                ->update([
                    'ended_at' => now(),
                    'end_reason' => 'superseded',
                ]);
        }

        $this->line('Sesiones cerradas (timeout): '.$closedExpired);
        $this->line('Sesiones cerradas (superseded): '.$closedOrphans);

        return Command::SUCCESS;
    }
}
