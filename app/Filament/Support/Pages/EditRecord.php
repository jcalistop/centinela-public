<?php

namespace App\Filament\Support\Pages;

use App\Filament\Support\Pages\Concerns\RedirectsToResourceViewPage;
use Filament\Resources\Pages\EditRecord as FilamentEditRecord;

/**
 * Default CRUD Centinela (SDD-301): tras guardar, vista read.
 */
class EditRecord extends FilamentEditRecord
{
    use RedirectsToResourceViewPage;
}
