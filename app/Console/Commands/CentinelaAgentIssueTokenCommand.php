<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CentinelaAgentIssueTokenCommand extends Command
{
    protected $signature = 'centinela:agent:issue-token
                            {email : Email del usuario técnico agente}
                            {--ss= : cen_hie_dependency_id (SS fijo; obligatorio con --create)}
                            {--create : Crear usuario con rol agente si no existe}
                            {--name=agent : Nombre del personal access token}
                            {--ttl= : TTL en minutos (default: config centinela.agent_api.token_ttl_minutes)}';

    protected $description = 'Emite token Sanctum ability read para usuario técnico agente (SDD-248)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $create = (bool) $this->option('create');
        $ssOption = $this->option('ss');
        $ssId = $ssOption !== null && $ssOption !== '' ? (int) $ssOption : null;

        $role = Role::query()->firstOrCreate(
            ['name' => 'agente'],
            ['display_name' => 'Agente']
        );

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            if (! $create) {
                $this->error('Usuario no encontrado. Use --create con --ss=<id>.');

                return self::FAILURE;
            }

            if ($ssId === null || $ssId < 1) {
                $this->error('Al crear usuario técnico, --ss=<cen_hie_dependency_id> es obligatorio.');

                return self::FAILURE;
            }

            $user = new User;
            $user->forceFill([
                'name' => 'Agente '.$email,
                'email' => $email,
                'password' => Hash::make(Str::password(32)),
                'role_id' => $role->id,
                'cen_hie_dependency_id' => $ssId,
            ]);
            $user->save();
            $this->info("Usuario técnico creado (id={$user->id}, SS={$ssId}).");
        } else {
            if (! $user->hasRole('agente')) {
                $user->role_id = $role->id;
                $user->save();
            }

            if ($ssId !== null) {
                if ($user->cen_hie_dependency_id !== null && (int) $user->cen_hie_dependency_id !== $ssId) {
                    $this->error('El usuario ya tiene SS distinto; no se reasigna (SS fijo).');

                    return self::FAILURE;
                }
                if ($user->cen_hie_dependency_id === null) {
                    $user->cen_hie_dependency_id = $ssId;
                    $user->save();
                }
            }

            if ($user->cen_hie_dependency_id === null) {
                $this->error('El usuario agente no tiene SS fijo. Pase --ss=<id>.');

                return self::FAILURE;
            }
        }

        $ttlOption = $this->option('ttl');
        $ttlMinutes = $ttlOption !== null && $ttlOption !== ''
            ? (int) $ttlOption
            : (int) config('centinela.agent_api.token_ttl_minutes', 10080);

        if ($ttlMinutes < 1) {
            $this->error('TTL debe ser >= 1 minuto.');

            return self::FAILURE;
        }

        $tokenName = (string) $this->option('name');
        $newToken = $user->createToken($tokenName, ['read'], now()->addMinutes($ttlMinutes));

        $this->line('Token emitido (ability: read). Guarde el plain text; no se vuelve a mostrar.');
        $this->line('token_id='.$newToken->accessToken->id);
        $this->line('expires_at='.$newToken->accessToken->expires_at);
        $this->line('plain_text_token='.$newToken->plainTextToken);

        return self::SUCCESS;
    }
}
