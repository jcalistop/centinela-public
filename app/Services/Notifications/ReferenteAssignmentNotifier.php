<?php

namespace App\Services\Notifications;

use App\Mail\ReferenteAssignedMail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ReferenteAssignmentNotifier
{
    /**
     * @param  iterable<int>  $userIds
     */
    public function notifyNewReferentes(iterable $userIds, ReferenteAssignmentMailData $mailData): bool
    {
        $ids = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return true;
        }

        $allQueued = true;

        foreach (User::query()->whereIn('id', $ids)->get() as $user) {
            if (! $this->hasDeliverableEmail($user)) {
                Log::warning('referente_assignment_email_skipped', [
                    'user_id' => $user->getKey(),
                    'module' => $mailData->moduleLabel,
                    'indicator_clave' => $mailData->indicatorClave,
                    'actor_id' => auth()->id(),
                ]);

                continue;
            }

            try {
                Mail::to($user->email)->queue(new ReferenteAssignedMail(
                    user: $user,
                    mailData: $mailData,
                    appName: (string) config('app.name', 'Centinela'),
                ));

                Log::info('referente_assignment_email_queued', [
                    'user_id' => $user->getKey(),
                    'module' => $mailData->moduleLabel,
                    'indicator_clave' => $mailData->indicatorClave,
                    'actor_id' => auth()->id(),
                ]);
            } catch (Throwable $exception) {
                $allQueued = false;

                Log::warning('referente_assignment_email_failed', [
                    'user_id' => $user->getKey(),
                    'module' => $mailData->moduleLabel,
                    'indicator_clave' => $mailData->indicatorClave,
                    'actor_id' => auth()->id(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $allQueued;
    }

    /**
     * @param  Collection<int, int>  $previousIds
     * @param  Collection<int, int>  $currentIds
     * @return Collection<int, int>
     */
    public static function diffNewIds(Collection $previousIds, Collection $currentIds): Collection
    {
        $normalize = static fn (Collection $ids): Collection => $ids
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        return $normalize($currentIds)->diff($normalize($previousIds))->values();
    }

    private function hasDeliverableEmail(User $user): bool
    {
        $email = trim((string) $user->email);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
