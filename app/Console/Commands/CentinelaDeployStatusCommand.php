<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Console\Command;

class CentinelaDeployStatusCommand extends Command
{
    protected $signature = 'centinela:deploy:status';

    protected $description = 'Muestra el estado operativo del deploy interno (rama, git, bloqueos, última ejecución)';

    public function handle(DeployOrchestrator $deploy): int
    {
        $status = $deploy->operationalStatus();
        $git = is_array($status['git_checkout'] ?? null) ? $status['git_checkout'] : [];

        $this->line('=== Centinela deploy — estado ===');
        $this->line('Auto deploy: '.($status['automatic_enabled'] ? 'ON' : 'OFF'));
        $this->line('Schedule bloqueado: '.($status['schedule_blocked'] ? 'SÍ' : 'no'));
        $this->line('Rama configurada: '.$status['deploy_branch']);

        if (($git['readable'] ?? false) === false) {
            $this->line('Checkout actual: (validación Git diferida en HTTP — ver worker CLI)');
        } elseif (($git['git_available'] ?? false) === false) {
            $this->line('Checkout actual: (git no disponible en este proceso)');
        } elseif (($git['detached'] ?? false) === true) {
            $this->line('Checkout actual: detached HEAD @ '.($git['head_sha_short'] ?? '?'));
        } else {
            $branchLabel = is_string($git['abbrev_ref'] ?? null) ? $git['abbrev_ref'] : '(desconocido)';
            $shaSuffix = is_string($git['head_sha_short'] ?? null) ? ' @ '.$git['head_sha_short'] : '';
            $this->line('Checkout actual: '.$branchLabel.$shaSuffix);
        }

        $this->line('Rama alineada: '.($status['branch_aligned'] ? 'sí' : 'NO'));

        if (is_string($status['branch_mismatch']) && $status['branch_mismatch'] !== '') {
            $this->warn($status['branch_mismatch']);
        }

        $this->line('HEAD: '.($status['local_sha'] ?? '—'));
        $this->line('Remoto ('.$status['deploy_branch'].'): '.($status['remote_sha'] ?? '—'));
        $this->line('Commits por traer del remoto: '.($status['commits_ahead_on_remote'] ?? '—'));
        $this->line('Último deploy exitoso (to_sha): '.($status['last_success_sha'] ?? '—'));
        $this->line('Migraciones pendientes: '.($status['has_pending_migrations'] ? 'sí' : 'no'));
        $this->line('Idempotente (skip): '.($status['would_skip'] ? 'sí' : 'no'));
        $this->line('Auto exitoso hoy: '.($status['auto_success_today'] ? 'sí' : 'no'));

        $this->newLine();
        $this->line('Diagnóstico:');
        foreach ($deploy->formatConsoleDiagnosticLines() as $line) {
            $this->line('  → '.$line);
        }

        if ($status['last_execution'] !== null) {
            $last = $status['last_execution'];
            $this->newLine();
            $this->line('Última ejecución #'.$last['id'].' ['.$last['mode'].'] '.$last['status']
                .' — '.($last['summary'] ?? ''));
            if ($last['finished_at'] !== null) {
                $this->line('  Finalizada: '.$last['finished_at']);
            }
        }

        return self::SUCCESS;
    }
}
