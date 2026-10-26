<?php

namespace App\Providers;

use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use TCG\Voyager\Facades\Voyager;

class SettingsBridgeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * Itera el mapeo `config('centinela.settings_bridge')` y sobreescribe
     * config() con los valores de Voyager settings (BD) cuando existen.
     * Si una setting no existe en BD, se usa el default de env() definido
     * en config/centinela.php.
     *
     * En entorno testing (SQLite in-memory) la tabla settings puede no
     * existir aun al boot. En ese caso se omite el bridge silenciosamente
     * y se mantienen los defaults de config/centinela.php.
     */
    public function boot(): void
    {
        $bridge = config('centinela.settings_bridge', []);

        if ($bridge === []) {
            return;
        }

        foreach ($bridge as $settingKey => $configPath) {
            try {
                $value = Voyager::setting($settingKey);
            } catch (QueryException) {
                return;
            }

            if ($value === null) {
                continue;
            }

            $normalized = match (true) {
                $value === 'On' || $value === '1' || $value === 1 || $value === true => true,
                $value === 'Off' || $value === '0' || $value === 0 || $value === false => false,
                default => $value,
            };

            config([$configPath => $normalized]);
        }

        // Marcar que el bridge se ejecuto exitosamente
        config(['centinela.bridge_executed' => true]);
    }
}
