<?php

namespace App\Providers;

use App\Listeners\EndPlatformAuthSession;
use App\Listeners\LogFailedLogin;
use App\Listeners\LogSkippedOverlappingScheduleTask;
use App\Listeners\PreventUnsafeDestructiveDatabaseCommands;
use App\Listeners\RejectCrlfInMailAddresses;
use App\Listeners\StartPlatformAuthSession;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSending;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        CommandStarting::class => [
            PreventUnsafeDestructiveDatabaseCommands::class,
        ],
        ScheduledTaskFinished::class => [
            LogSkippedOverlappingScheduleTask::class,
        ],
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Failed::class => [
            LogFailedLogin::class,
        ],
        Login::class => [
            StartPlatformAuthSession::class,
        ],
        Logout::class => [
            EndPlatformAuthSession::class,
        ],
        MessageSending::class => [
            RejectCrlfInMailAddresses::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
