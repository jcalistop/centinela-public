<?php

namespace App\Services\Messaging;

use App\Enums\MessagingAudience;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class MessagingAudienceResolver
{
    /**
     * @param  list<int>|null  $userIds
     * @return Collection<int, User>
     */
    public function resolve(
        User $actor,
        MessagingAudience $audience,
        ?int $cenHieDependencyId = null,
        ?array $userIds = null,
    ): Collection {
        $this->assertActorCanReachAudience($actor, $audience, $cenHieDependencyId, $userIds);

        $query = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '');

        match ($audience) {
            MessagingAudience::AllUsers => null,
            MessagingAudience::HealthServiceAll => $query->where('cen_hie_dependency_id', $cenHieDependencyId),
            MessagingAudience::HealthServiceReferentes => $this->constrainReferentes($query, (int) $cenHieDependencyId),
            MessagingAudience::SpecificUsers => $query->whereIn('id', array_values(array_unique($userIds ?? []))),
        };

        if ($actor->hasRole('control-gestion') && ! $actor->hasRole('admin')) {
            $ssId = $actor->cen_hie_dependency_id;
            if ($ssId === null) {
                return collect();
            }
            $query->where('cen_hie_dependency_id', $ssId);
        }

        return $query
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @param  list<int>|null  $userIds
     */
    public function assertActorCanReachAudience(
        User $actor,
        MessagingAudience $audience,
        ?int $cenHieDependencyId = null,
        ?array $userIds = null,
    ): void {
        if ($actor->hasRole('admin')) {
            $this->assertAudiencePayload($audience, $cenHieDependencyId, $userIds);

            return;
        }

        if (! $actor->hasRole('control-gestion')) {
            throw ValidationException::withMessages([
                'audience' => 'No autorizado a enviar mensajería.',
            ]);
        }

        if ($audience === MessagingAudience::AllUsers) {
            throw ValidationException::withMessages([
                'audience' => 'Control de gestión no puede enviar a todos los usuarios registrados.',
            ]);
        }

        $actorSs = $actor->cen_hie_dependency_id;
        if ($actorSs === null) {
            throw ValidationException::withMessages([
                'audience' => 'Su cuenta no tiene Servicio de Salud asignado.',
            ]);
        }

        if (in_array($audience, [MessagingAudience::HealthServiceAll, MessagingAudience::HealthServiceReferentes], true)) {
            if ($cenHieDependencyId !== null && (int) $cenHieDependencyId !== (int) $actorSs) {
                throw ValidationException::withMessages([
                    'cen_hie_dependency_id' => 'Solo puede enviar a su propio Servicio de Salud.',
                ]);
            }
        }

        if ($audience === MessagingAudience::SpecificUsers) {
            $ids = array_values(array_unique($userIds ?? []));
            if ($ids === []) {
                throw ValidationException::withMessages([
                    'user_ids' => 'Seleccione al menos un usuario.',
                ]);
            }

            $outside = User::query()
                ->whereIn('id', $ids)
                ->where(function (Builder $q) use ($actorSs): void {
                    $q->whereNull('cen_hie_dependency_id')
                        ->orWhere('cen_hie_dependency_id', '!=', $actorSs);
                })
                ->exists();

            if ($outside) {
                throw ValidationException::withMessages([
                    'user_ids' => 'Solo puede seleccionar usuarios de su Servicio de Salud.',
                ]);
            }
        }

        $this->assertAudiencePayload(
            $audience,
            $cenHieDependencyId ?? $actorSs,
            $userIds,
        );
    }

    /**
     * @param  list<int>|null  $userIds
     */
    private function assertAudiencePayload(
        MessagingAudience $audience,
        ?int $cenHieDependencyId,
        ?array $userIds,
    ): void {
        if (in_array($audience, [MessagingAudience::HealthServiceAll, MessagingAudience::HealthServiceReferentes], true)) {
            if ($cenHieDependencyId === null) {
                throw ValidationException::withMessages([
                    'cen_hie_dependency_id' => 'Seleccione un Servicio de Salud.',
                ]);
            }

            $enabled = CenHieDependency::query()
                ->enabled()
                ->whereKey($cenHieDependencyId)
                ->exists();

            if (! $enabled) {
                throw ValidationException::withMessages([
                    'cen_hie_dependency_id' => 'Solo se permiten Servicios de Salud habilitados.',
                ]);
            }
        }

        if ($audience === MessagingAudience::SpecificUsers && ($userIds === null || $userIds === [])) {
            throw ValidationException::withMessages([
                'user_ids' => 'Seleccione al menos un usuario.',
            ]);
        }
    }

    /**
     * @param  Builder<User>  $query
     */
    private function constrainReferentes(Builder $query, int $cenHieDependencyId): void
    {
        $referenteRoleId = Role::query()->where('name', 'referente')->value('id');
        if ($referenteRoleId === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query
            ->where('role_id', $referenteRoleId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId);
    }
}
