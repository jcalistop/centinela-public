<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Console\Command;

class CentinelaDeployAutoCommand extends Command
{
    protected $signature = 'centinela:deploy:auto';

    protected $description = 'Ejecuta deploy automático (si está habilitado y no bloqueado)';

    public function handle(DeployOrchestrator $deploy): int
    {
        $result = $deploy->setConsoleCommand($this)->runAuto();
        $deploy->emitConsoleOutcome($this, $result);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
