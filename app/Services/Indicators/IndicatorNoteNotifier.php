<?php

namespace App\Services\Indicators;

use App\Enums\Indicators\IndicatorModuleType;
use App\Mail\IndicatorNoteNotification;
use App\Models\ComIndicatorSetting;
use App\Models\IndicatorActivity;
use App\Models\MetIndicator;
use App\Models\MetIndicatorReferente;
use App\Models\User;
use App\Services\Notifications\IndicatorNoteMailDataFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class IndicatorNoteNotifier
{
    public function notifyReferentes(IndicatorActivity $activity): void
    {
        $recipients = $this->resolveReferentes($activity);

        $mailData = IndicatorNoteMailDataFactory::fromActivity($activity);

        foreach ($recipients as $user) {
            if (! $this->hasDeliverableEmail($user)) {
                continue;
            }

            try {
                Mail::to($user->email)->queue(new IndicatorNoteNotification(
                    user: $user,
                    activity: $activity,
                    mailData: $mailData,
                    appName: (string) config('app.name', 'Centinela'),
                ));
            } catch (Throwable $exception) {
                Log::warning('indicator_note_email_failed', [
                    'activity_id' => $activity->id,
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return iterable<int, User>
     */
    private function resolveReferentes(IndicatorActivity $activity): iterable
    {
        if ($activity->indicator_type === IndicatorModuleType::Metas) {
            $indicator = MetIndicator::query()->find($activity->indicator_id);
            if ($indicator === null) {
                return [];
            }

            return MetIndicatorReferente::query()
                ->where('met_indicator_id', $indicator->id)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter(fn ($user) => $user instanceof User)
                ->unique('id');
        }

        $setting = ComIndicatorSetting::query()
            ->where('com_indicator_id', $activity->indicator_id)
            ->when(
                is_array($activity->metadata) && isset($activity->metadata['cen_hie_dependency_id']),
                fn ($query) => $query->where('cen_hie_dependency_id', $activity->metadata['cen_hie_dependency_id']),
            )
            ->with('users')
            ->first();

        return $setting?->users ?? collect();
    }

    private function hasDeliverableEmail(User $user): bool
    {
        return filter_var($user->email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
