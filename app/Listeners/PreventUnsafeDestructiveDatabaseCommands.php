<?php

namespace App\Listeners;

use App\Support\Console\DestructiveMigrationSafety;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

class PreventUnsafeDestructiveDatabaseCommands
{
    public function handle(CommandStarting $event): void
    {
        if (! DestructiveMigrationSafety::isDestructiveCommandName($event->command)) {
            return;
        }

        /** @var Application $application */
        $application = app(Application::class);

        $databaseOption = $event->input->hasOption('database')
            ? $event->input->getOption('database')
            : null;

        $driver = DestructiveMigrationSafety::resolveConnectionDriver($application, $databaseOption);
        $denyReason = DestructiveMigrationSafety::denyReason($application, $driver);

        if ($denyReason !== null) {
            throw new RuntimeException($denyReason);
        }
    }
}
