<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class CentinelaAgentRevokeTokenCommand extends Command
{
    protected $signature = 'centinela:agent:revoke-token
                            {--email= : Revocar tokens del usuario (con --all o --token-id)}
                            {--token-id= : ID de personal_access_tokens}
                            {--all : Revocar todos los tokens del email}';

    protected $description = 'Revoca token(s) Sanctum de usuario agente (SDD-248)';

    public function handle(): int
    {
        $tokenId = $this->option('token-id');
        $email = $this->option('email');
        $all = (bool) $this->option('all');

        if ($tokenId !== null && $tokenId !== '') {
            $token = PersonalAccessToken::query()->find((int) $tokenId);
            if (! $token instanceof PersonalAccessToken) {
                $this->error('Token no encontrado.');

                return self::FAILURE;
            }
            $token->delete();
            $this->info("Token id={$tokenId} revocado.");

            return self::SUCCESS;
        }

        if ($email === null || $email === '') {
            $this->error('Indique --token-id= o --email= con --all.');

            return self::FAILURE;
        }

        if (! $all) {
            $this->error('Con --email use --all o pase --token-id=.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', strtolower(trim((string) $email)))->first();
        if (! $user instanceof User) {
            $this->error('Usuario no encontrado.');

            return self::FAILURE;
        }

        $count = $user->tokens()->delete();
        $this->info("Revocados {$count} token(s) de {$user->email}.");

        return self::SUCCESS;
    }
}
