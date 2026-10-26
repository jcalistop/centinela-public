<?php

namespace App\Support\Voyager;

use App\Mail\UserCredentialsMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class VoyagerUserCredentialsMailer
{
    public static function send(User $user, string $plainPassword, bool $isUpdate = false): bool
    {
        try {
            Mail::to($user->email)->queue(new UserCredentialsMail(
                user: $user,
                plainPassword: $plainPassword,
                loginUrl: route('login'),
                appName: (string) config('app.name', 'Centinela'),
                isUpdate: $isUpdate,
            ));

            Log::info('user_credentials_email_queued', [
                'user_id' => $user->getKey(),
                'actor_id' => auth()->id(),
                'is_update' => $isUpdate,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('user_credentials_email_failed', [
                'user_id' => $user->getKey(),
                'actor_id' => auth()->id(),
                'is_update' => $isUpdate,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
