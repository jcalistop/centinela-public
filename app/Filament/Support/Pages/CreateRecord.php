<?php

namespace App\Filament\Support\Pages;

use App\Filament\Support\Pages\Concerns\RedirectsToResourceViewPage;
use Filament\Resources\Pages\CreateRecord as FilamentCreateRecord;

/**
 * Default CRUD Centinela (SDD-301): sin «crear y crear otro»; tras guardar, vista read.
 */
class CreateRecord extends FilamentCreateRecord
{
    use RedirectsToResourceViewPage;

    protected static bool $canCreateAnother = false;
}
