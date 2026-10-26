<?php

namespace App\Console\Commands;

use App\Models\AgentRequestLog;
use Illuminate\Console\Command;

class CentinelaAgentPruneRequestLogsCommand extends Command
{
    protected $signature = 'centinela:agent:prune-request-logs
                            {--days= : Días de retención (default: config centinela.agent_api.request_log_retention_days)}
                            {--dry-run : Solo contar filas a borrar}';

    protected $description = 'Purga agent_request_logs antiguos (SDD-248 / AG-08)';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = $daysOption !== null && $daysOption !== ''
            ? (int) $daysOption
            : (int) config('centinela.agent_api.request_log_retention_days', 90);

        if ($days < 1) {
            $this->error('days debe ser >= 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $query = AgentRequestLog::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$count} fila(s) anteriores a {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Eliminadas {$deleted} fila(s) de agent_request_logs.");

        return self::SUCCESS;
    }
}
