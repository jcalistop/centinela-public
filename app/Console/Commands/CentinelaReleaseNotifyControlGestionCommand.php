<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\NotifyControlGestionDeployedRelease;
use App\Models\User;
use App\Services\Releases\ReleaseNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class CentinelaReleaseNotifyControlGestionCommand extends Command
{
    protected $signature = 'centinela:release:notify-control-gestion
        {--user_id= : Admin actor user id}
        {--force : Reenviar aunque ya se haya notificado la versión actual}';

    protected $description = 'Encola notificación oficial de versión desplegada a control-gestión';

    public function handle(ReleaseNotificationService $notification): int
    {
        $actor = $this->resolveAdminActor();
        if (! $actor instanceof User) {
            $this->error('Se requiere un usuario admin (--user_id o sesión).');

            return self::FAILURE;
        }

        $execution = $notification->startNotificationExecution($actor, isTest: false);
        NotifyControlGestionDeployedRelease::dispatch(
            $execution->id,
            isTest: false,
            forceResend: (bool) $this->option('force'),
        );

        $this->info('Notificación encolada (execution #'.$execution->id.').');

        return self::SUCCESS;
    }

    private function resolveAdminActor(): ?User
    {
        $userId = $this->option('user_id');
        if (is_string($userId) && $userId !== '' && is_numeric($userId)) {
            $user = User::query()->find((int) $userId);
            if ($user instanceof User && $user->hasRole('admin')) {
                return $user;
            }

            return null;
        }

        $auth = Auth::user();

        return ($auth instanceof User && $auth->hasRole('admin')) ? $auth : null;
    }
}
