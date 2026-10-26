<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CentinelaDeployUnlockCommand extends Command
{
    protected $signature = 'centinela:deploy:unlock';

    protected $description = 'Desbloquea el deploy automático tras un fallo (limpia lock de schedule)';

    public function handle(): int
    {
        Cache::forget(DeployOrchestrator::SCHEDULE_LOCK_KEY);

        $this->info('Bloqueo de deploy automático liberado.');

        return self::SUCCESS;
    }
}
