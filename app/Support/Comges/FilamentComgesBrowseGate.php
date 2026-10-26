<?php

namespace App\Support\Comges;

/**
 * Flag dual-run browse COMGES en Filament (SDD-275 / C1a-01).
 * Off por defecto: solo env/config `centinela.filament.comges_browse_enabled`.
 */
final class FilamentComgesBrowseGate
{
    public static function enabled(): bool
    {
        return filter_var(config('centinela.filament.comges_browse_enabled'), FILTER_VALIDATE_BOOLEAN);
    }
}
