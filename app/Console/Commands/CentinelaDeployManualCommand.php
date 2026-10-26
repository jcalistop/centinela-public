<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class CentinelaDeployManualCommand extends Command
{
    protected $signature = 'centinela:deploy:manual
        {--email= : Actor email (unique)}
        {--user_id= : Actor user id (compat)}';

    protected $description = 'Ejecuta deploy manual (para uso interno/admin)';

    public function handle(DeployOrchestrator $deploy): int
    {
        $actor = $this->resolveActor();
        if (! $actor instanceof User) {
            return self::FAILURE;
        }

        $result = $deploy->setConsoleCommand($this)->runManual($actor);
        $deploy->emitConsoleOutcome($this, $result);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }

    private function resolveActor(): ?User
    {
        $emailOption = $this->option('email');
        $userIdOption = $this->option('user_id');

        $email = is_string($emailOption) ? trim($emailOption) : '';
        $userIdRaw = is_string($userIdOption) ? trim($userIdOption) : '';
        $hasEmail = $email !== '';
        $hasUserId = $userIdRaw !== '';

        if ($hasEmail && $hasUserId) {
            $this->error('Opciones mutuamente excluyentes: usa --email o --user_id, no ambos.');

            return null;
        }

        if ($hasEmail) {
            $actor = User::query()->where('email', $email)->first();
            if (! $actor instanceof User) {
                $this->error('Actor no encontrado para email: '.$email);

                return null;
            }

            return $actor;
        }

        if ($hasUserId) {
            if (! is_numeric($userIdRaw)) {
                $this->error('user_id inválido.');

                return null;
            }

            $actor = User::query()->find((int) $userIdRaw);
            if (! $actor instanceof User) {
                $this->error('Actor no encontrado para user_id: '.$userIdRaw);

                return null;
            }

            return $actor;
        }

        /** @var mixed $auth */
        $auth = Auth::user();
        if ($auth instanceof User) {
            return $auth;
        }

        $this->error('Actor requerido (--email, --user_id o auth).');

        return null;
    }
}
