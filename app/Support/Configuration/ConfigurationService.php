<?php

namespace App\Support\Configuration;

use TCG\Voyager\Models\Setting;

/**
 * Servicio que unifica la lectura de configuracion del sistema.
 *
 * Jerarquia de resolucion:
 * 1. Tabla `settings` de Voyager (via cache en memoria)
 * 2. `config/*.php` (fallback)
 * 3. `.env` (ultimo respaldo via config())
 *
 * No interfiere con modelos de configuracion de modulos de negocio
 * (IndicatorTypeSetting, IndicatorTypeGlobalSetting, etc.).
 *
 * Mapa de keys BD -> config():
 *   module.soporte_enabled    -> centinela.support.enabled
 *   module.chat_ia_enabled    -> centinela.chat_ia.enabled
 *   module.user_manual_enabled -> centinela.user_manual.enabled
 *   ... etc (ver migracion 2026_05_15_000200_migrate_env_to_settings)
 */
class ConfigurationService
{
    /**
     * Mapa de keys de settings BD a rutas config('*').
     *
     * @var array<string, string>
     */
    private const MAP_BD_TO_CONFIG = [
        'module.usage_tracking_enabled' => 'centinela.usage_tracking.enabled',
        'module.dashboard_non_admin_enabled' => 'centinela.dashboard_non_admin.enabled',
        'module.soporte_enabled' => 'centinela.support.enabled',
        'module.chat_ia_enabled' => 'centinela.chat_ia.enabled',
        'module.user_manual_enabled' => 'centinela.user_manual.enabled',
        'module.login_legal_documents_enabled' => 'centinela.login_legal_documents.enabled',
        'module.github_issues_enabled' => 'centinela.github_issues.enabled',
        'module.deploy_automatic_enabled' => 'centinela.deploy.automatic_enabled',
        'module.whatsapp_enabled' => 'services.whatsapp.enabled',
        'module.metas_recalc_scope_enabled' => 'centinela.metas.recalculation_scope_enabled',
        'notifications.contact_mail_to' => 'centinela.contact_mail_to',
        'notifications.backup_email' => 'centinela.backup.email_address',
        'notifications.scheduler_alert_email' => 'centinela.scheduler.alert_email',
        'notifications.scheduler_report_emails' => 'centinela.scheduler.report_emails',
        'notifications.security_alert_email' => 'centinela.security.alert_email_to',
        'notifications.whatsapp_alert_to' => 'services.whatsapp.alert_to',
        'system.dashboard_cache_ttl' => 'centinela.dashboard_summary.cache_ttl_seconds',
        'system.deploy_executions_retention_days' => 'centinela.deploy.executions_retention_days',
        'system.usage_tracking_throttle' => 'centinela.usage_tracking.throttle_seconds',
        'system.storage_temp_max_age' => 'centinela.storage_temp.max_age_hours',
        'system.backup_retention_days' => 'centinela.backup.retention_days',
        'system.backup_max_age_hours' => 'centinela.backup.max_age_hours',
        'system.audit_retention_months' => 'centinela.audit.retention_months',
        'system.worker_stale_minutes' => 'centinela.worker.stale_minutes',
        'system.worker_pending_warning_jobs' => 'centinela.worker.pending_warning_jobs',
        'system.worker_failed_jobs_warning_24h' => 'centinela.worker.failed_jobs_warning_24h',
        'system.worker_failed_jobs_retention_hours' => 'centinela.worker.failed_jobs_retention_hours',
        'system.support_close_resolved_days' => 'centinela.support.close_resolved_days',
        'system.failed_login_threshold' => 'centinela.security.failed_login_threshold',
        'system.failed_login_lookback_hours' => 'centinela.security.failed_login_lookback_hours',
        'system.failed_login_retention_days' => 'centinela.security.failed_login_retention_days',
        'system.scheduler_heartbeat_threshold' => 'centinela.scheduler.heartbeat_threshold_minutes',
        'system.scheduler_alert_grace_failures' => 'centinela.scheduler.alert_grace_failures',
        'system.github_releases_cache_seconds' => 'services.github.releases_http_cache_seconds',
        'system.metas_recalc_debounce_seconds' => 'centinela.metas.recalculation_debounce_seconds',
        'services.git_binary_path' => 'centinela.deploy.git_binary',
        'services.deploy_remote' => 'centinela.deploy.remote',
        'services.deploy_branch' => 'centinela.deploy.branch',
        'services.deploy_run_composer' => 'centinela.deploy.run_composer_after_pull',
        'services.deploy_run_npm_build' => 'centinela.deploy.run_npm_build_after_pull',
        'services.db_dump_path' => 'centinela.backup.db_dump_path',
        'services.github_releases_repo' => 'services.github.releases_repository',
    ];

    /**
     * Cache en memoria de todas las settings de Voyager (key => value).
     *
     * @var array<string, string|null>|null
     */
    private ?array $settingsCache = null;

    /**
     * Obtiene el valor de una configuracion desde settings BD.
     *
     * Busca primero en la tabla settings de Voyager.
     * Si no existe, retorna null (quien llama debe hacer fallback a config()).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->loadSettings();

        if (array_key_exists($key, $this->settingsCache)) {
            return $this->settingsCache[$key] ?? $default;
        }

        return $default;
    }

    /**
     * Interpreta un setting Voyager tipo radio On/Off.
     */
    public function isRadioOn(string $key, bool $defaultWhenMissing = true): bool
    {
        $value = $this->get($key);

        if ($value === null || $value === '') {
            return $defaultWhenMissing;
        }

        return in_array($value, ['On', '1', 1, true], true);
    }

    /**
     * Persiste On/Off y recarga config en memoria (dispara SettingUpdated).
     */
    public function setRadioOn(string $key, bool $enabled): void
    {
        $setting = Setting::query()->where('key', $key)->first();
        if ($setting === null) {
            return;
        }

        $value = $enabled ? 'On' : 'Off';
        if ((string) $setting->value === $value) {
            return;
        }

        $setting->value = $value;
        $setting->save();
    }

    /**
     * Carga config() con valores desde settings BD para todas las rutas mapeadas.
     * Se invoca en AppServiceProvider::boot().
     */
    public function overrideConfig(): void
    {
        $this->loadSettings();

        foreach (self::MAP_BD_TO_CONFIG as $bdKey => $configPath) {
            if (array_key_exists($bdKey, $this->settingsCache)) {
                $value = $this->settingsCache[$bdKey];

                // Convertir 'On'/'Off' a booleano para config() que espera booleano
                if ($value === 'On' || $value === 'Off') {
                    config([$configPath => $value === 'On']);
                } elseif ($value !== null && $value !== '') {
                    config([$configPath => $value]);
                }
            }
        }
    }

    /**
     * Email de notificación para respaldos (settings BD, services o centinela config).
     */
    public function backupNotificationEmail(): ?string
    {
        $email = config('centinela.backup.email_address') ?: config('services.backup.email_address');

        if (! is_string($email)) {
            return null;
        }

        $email = trim($email);

        return $email !== '' ? $email : null;
    }

    /**
     * Invalida la cache en memoria.
     */
    public function invalidate(): void
    {
        $this->settingsCache = null;
    }

    /**
     * Carga todas las settings desde BD a cache en memoria.
     */
    private function loadSettings(): void
    {
        if ($this->settingsCache !== null) {
            return;
        }

        $this->settingsCache = [];

        try {
            $all = Setting::query()->get(['key', 'value']);

            foreach ($all as $setting) {
                $this->settingsCache[$setting->key] = $setting->value;
            }
        } catch (\Throwable) {
            $this->settingsCache = [];
        }
    }
}
