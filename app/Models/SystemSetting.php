<?php

namespace App\Models;

use App\Support\Configuration\ConfigurationService;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistencia de la tabla `settings` sin modelo Voyager (SDD-261 / T1).
 * Misma tabla y contrato de claves que ADR-032; UI Filament no usa TCG\Voyager\Models\Setting.
 */
class SystemSetting extends Model
{
    protected $table = 'settings';

    protected $guarded = [];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::saved(function (): void {
            $configuration = app(ConfigurationService::class);
            $configuration->invalidate();
            $configuration->overrideConfig();
        });
    }
}
