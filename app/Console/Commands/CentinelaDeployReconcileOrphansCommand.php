<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Deploy\DeployOrphanReconciler;
use Illuminate\Console\Command;

class CentinelaDeployReconcileOrphansCommand extends Command
{
    protected $signature = 'centinela:deploy:reconcile-orphans';

    protected $description = 'Cierra deploy_executions running/queued huérfanos según TTL (orphan_ttl_seconds)';

    public function handle(DeployOrphanReconciler $reconciler): int
    {
        $ttl = max(1, (int) config('centinela.deploy.orphan_ttl_seconds', 1800));
        $closed = $reconciler->reconcile();

        $this->info("Reconcile orphans (TTL {$ttl}s): cerradas {$closed} ejecución(es).");

        return self::SUCCESS;
    }
}
