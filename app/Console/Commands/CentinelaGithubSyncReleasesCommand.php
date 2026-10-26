<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Github\GithubReleasesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class CentinelaGithubSyncReleasesCommand extends Command
{
    protected $signature = 'centinela:github:sync-releases';

    protected $description = 'Sincroniza GitHub Releases a caché y snapshots en BD';

    public function handle(GithubReleasesService $service): int
    {
        if (! $this->actorIsAdmin()) {
            $this->error('Solo administradores pueden ejecutar este comando.');

            return self::FAILURE;
        }

        $result = $service->syncFromApi();
        if ($result === null) {
            $this->error('No se pudo obtener releases desde GitHub.');

            return self::FAILURE;
        }

        $count = count($result['releases']);
        $this->info("Sincronizados {$count} releases ({$result['synced_at']->toIso8601String()}).");

        return self::SUCCESS;
    }

    private function actorIsAdmin(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasRole('admin');
    }
}
