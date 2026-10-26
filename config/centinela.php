<?php

use App\Livewire\Configuracion\SettingsManager;

return [
    /**
     * Destinatario de contacto: formulario /admin/acerca-de y mailto landing
     * «¿Quieres publicar un panel?» (SDD-289). Operativo vía setting BD
     * `notifications.contact_mail_to` (SettingsBridge).
     */
    'contact_mail_to' => env('CENTINELA_CONTACT_MAIL_TO', 'centinela-ops@ejemplo.gob.cl'),

    /**
     * Hosts allowlist para paneles `embed_externo` (SDD-290).
     * `*.dominio` coincide con el apex y subdominios. ArcGIS exige path público conocido.
     *
     * @var list<string>
     */
    'public_panel_embed' => [
        'allowed_hosts' => [
            'app.powerbi.com',
            'app.powerbi.us',
            'public.tableau.com',
            '*.online.tableau.com',
            'lookerstudio.google.com',
            'datastudio.google.com',
            'datawrapper.dwcdn.net',
            'www.datawrapper.de',
            'flo.uri.sh',
            'public.flourish.studio',
            '*.arcgis.com',
        ],
    ],

    /**
     * Límites de precarga ZIP de paneles públicos (SDD-291). No publica al unzip.
     */
    'public_panel_zip' => [
        'max_bytes' => (int) env('CENTINELA_PUBLIC_PANEL_ZIP_MAX_BYTES', 55 * 1024 * 1024),
        'max_entries' => (int) env('CENTINELA_PUBLIC_PANEL_ZIP_MAX_ENTRIES', 12),
        'max_uncompressed_bytes' => (int) env('CENTINELA_PUBLIC_PANEL_ZIP_MAX_UNCOMPRESSED', 100 * 1024 * 1024),
    ],

    /**
     * TTL (días) de URL firmada de preview de paneles no publicados (SDD-293).
     */
    'public_info_panel_preview_ttl_days' => (int) env('CENTINELA_PUBLIC_INFO_PANEL_PREVIEW_TTL_DAYS', 7),

    /**
     * Módulo Soporte (tickets internos, SDD-048). Desactiva menú y rutas si es false.
     *
     * @see `.env.example` CENTINELA_SUPPORT_ENABLED
     */
    'support' => [
        'enabled' => filter_var(env('CENTINELA_SUPPORT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'close_resolved_days' => (int) env('CENTINELA_SUPPORT_CLOSE_RESOLVED_DAYS', 5),
    ],

    /**
     * Promoción de ticket a issue GitHub (solo admin, one-shot).
     * Mismo repo y PAT que releases: `GITHUB_RELEASES_REPOSITORY`, `CENTINELA_GITHUB_TOKEN` (`config/services.php` github.*).
     *
     * @see `.env.example` CENTINELA_GITHUB_ISSUES_ENABLED, GITHUB_RELEASES_REPOSITORY
     */
    'github_issues' => [
        'enabled' => filter_var(env('CENTINELA_GITHUB_ISSUES_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'repo' => env('GITHUB_RELEASES_REPOSITORY', 'jcalistop/minsal-centinela'),
        'token' => env('CENTINELA_GITHUB_TOKEN') ?: env('RELEASES_TOKEN'),
    ],

    /**
     * Token Sanctum emitido por `POST /api/admin/token`.
     * `ttl_minutes`: si es un entero positivo, se fija `expires_at` en el token; si no, aplica solo `config/sanctum.php` (`expiration`).
     */
    'admin_api_token' => [
        'ttl_minutes' => filter_var(env('CENTINELA_ADMIN_API_TOKEN_TTL_MINUTES'), FILTER_VALIDATE_INT) ?: null,
    ],

    /**
     * Filament flags (ADR-039). Default false. Runtime hard-off SDD-308 (env ignorado).
     * Maestro SDD-284: `module.filament_enabled` / CENTINELA_FILAMENT_ENABLED.
     * Settings T1: `module.filament_settings_enabled` / CENTINELA_FILAMENT_SETTINGS_ENABLED.
     * Instrumentos T2a: `module.filament_instrumentos_evaluacion_enabled` / CENTINELA_FILAMENT_INSTRUMENTOS_EVALUACION_ENABLED.
     * Establecimientos T2b: `module.filament_establecimientos_enabled` / CENTINELA_FILAMENT_ESTABLECIMIENTOS_ENABLED.
     * Configurar tipo indicador T2c: `module.filament_configurar_tipo_indicador_enabled` / CENTINELA_FILAMENT_CONFIGURAR_TIPO_INDICADOR_ENABLED.
     * COMGES browse C1a: `centinela.filament.comges_browse_enabled` / CENTINELA_FILAMENT_COMGES_BROWSE_ENABLED (SDD-275).
     * Mensajería manual SDD-286: `module.filament_messaging_enabled` / CENTINELA_FILAMENT_MESSAGING_ENABLED.
     * No mapear esas keys en ConfigurationService::MAP_BD_TO_CONFIG.
     */
    'filament' => [
        'enabled' => filter_var(env('CENTINELA_FILAMENT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'settings_enabled' => filter_var(env('CENTINELA_FILAMENT_SETTINGS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'instrumentos_evaluacion_enabled' => filter_var(
            env('CENTINELA_FILAMENT_INSTRUMENTOS_EVALUACION_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'establecimientos_enabled' => filter_var(
            env('CENTINELA_FILAMENT_ESTABLECIMIENTOS_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'configurar_tipo_indicador_enabled' => filter_var(
            env('CENTINELA_FILAMENT_CONFIGURAR_TIPO_INDICADOR_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'comges_browse_enabled' => filter_var(
            env('CENTINELA_FILAMENT_COMGES_BROWSE_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'messaging_enabled' => filter_var(
            env('CENTINELA_FILAMENT_MESSAGING_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    /**
     * Mensajería manual por correo (SDD-286) — olas sobre cola database.
     */
    'messaging' => [
        'wave_size' => (int) (filter_var(env('CENTINELA_MESSAGING_WAVE_SIZE', 25), FILTER_VALIDATE_INT) ?: 25),
        'wave_delay_seconds' => (int) (filter_var(env('CENTINELA_MESSAGING_WAVE_DELAY_SECONDS', 300), FILTER_VALIDATE_INT) ?: 300),
    ],

    /**
     * P3a — superficie agentes (`api/v1`, `mcp`). Flag default false (ADR-041 / SDD-248).
     *
     * @see `.env.example` CENTINELA_AGENT_API_ENABLED, CENTINELA_AGENT_IP_ALLOWLIST, CENTINELA_AGENT_TOKEN_TTL_MINUTES
     */
    'agent_api' => [
        'enabled' => filter_var(env('CENTINELA_AGENT_API_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        /** Lista separada por comas: IPs o CIDR (ej. 10.0.0.0/8,192.168.1.10). Vacía = no restringe IP. */
        'ip_allowlist' => array_values(array_filter(array_map(
            static fn (string $v): string => trim($v),
            explode(',', (string) env('CENTINELA_AGENT_IP_ALLOWLIST', ''))
        ))),
        'token_ttl_minutes' => (int) (filter_var(env('CENTINELA_AGENT_TOKEN_TTL_MINUTES', 10080), FILTER_VALIDATE_INT) ?: 10080),
        'throttle_per_minute' => (int) (filter_var(env('CENTINELA_AGENT_THROTTLE_PER_MINUTE', 120), FILTER_VALIDATE_INT) ?: 120),
        'request_log_retention_days' => (int) (filter_var(env('CENTINELA_AGENT_REQUEST_LOG_RETENTION_DAYS', 90), FILTER_VALIDATE_INT) ?: 90),
    ],

    /**
     * Términos y condiciones (y política de privacidad) desde el login: pie con enlaces y acceso público a `/legal/*`.
     *
     * @see `.env.example` CENTINELA_LOGIN_LEGAL_DOCUMENTS_ENABLED
     */
    'login_legal_documents' => [
        'enabled' => filter_var(env('CENTINELA_LOGIN_LEGAL_DOCUMENTS_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    /**
     * Slugs de URL (`indicator_types.route`) permitidos al alta vía BREAD instrumentos-evaluación:
     * leyes Metas sanitarias (prefijo metas-…) y Alta Dirección Pública (`adp`).
     * Cualquier otro valor en BD (legacy) se conserva al editar validando con lista ampliada.
     */
    'metas_indicator_type_route_slugs' => [
        'metas-18834',
        'metas-19664',
        'metas-20707',
        'adp',
    ],

    /**
     * Clases de icono Voyager (fuente voyager-icons) mostradas en la galería del BREAD instrumentos-evaluación.
     * El campo sigue aceptando cualquier clase por texto; esto solo acelera la elección visual (convención voyager-*).
     */
    'instrument_voyager_icon_picker_classes' => [
        'voyager-book',
        'voyager-boat',
        'voyager-compass',
        'voyager-params',
        'voyager-lab',
        'voyager-dashboard',
        'voyager-data',
        'voyager-bread',
        'voyager-categories',
        'voyager-news',
        'voyager-person',
        'voyager-people',
        'voyager-shop',
        'voyager-tag',
        'voyager-trophy',
        'voyager-medal-rank',
        'voyager-thumb-tack',
        'voyager-lightbulb',
        'voyager-activity',
        'voyager-analytics',
        'voyager-bar-chart',
        'voyager-wrench',
        'voyager-tools',
        'voyager-file-text',
        'voyager-folder',
        'voyager-globe',
        'voyager-key',
        'voyager-lock',
        'voyager-star',
        'voyager-heart',
        'voyager-bell',
        'voyager-camera',
        'voyager-plane',
        'voyager-rocket',
        'voyager-anchor',
        'voyager-telescope',
        'voyager-study',
        'voyager-server',
        'voyager-settings',
        'voyager-list',
        'voyager-check',
        'voyager-warning',
        'voyager-info-circled',
    ],

    // Chat IA: variables `CENTINELA_CHAT_IA_*` documentadas en `.env.example` y ADR 2026-04-26 (feature flag).
    'chat_ia' => [
        'enabled' => filter_var(env('CENTINELA_CHAT_IA_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'comges_indicator_type_id' => 1, // Hardcodeado: se configura al deploy
    ],

    /**
     * Recálculo incremental de resultados Metas (SDD-109).
     *
     * Valores operativos vía settings (`module.metas_recalc_scope_enabled`, `system.metas_recalc_debounce_seconds`).
     */
    'metas' => [
        'recalculation_scope_enabled' => true,
        'recalculation_debounce_seconds' => 90,
        'calculation_queue_minutes_per_job' => 2,
    ],

    /**
     * Manual de usuario contextual (Voyager /admin): drawer, rutas `/admin/user-manual/*`.
     *
     * @see `.env.example` CENTINELA_USER_MANUAL_ENABLED
     */
    'user_manual' => [
        'enabled' => filter_var(env('CENTINELA_USER_MANUAL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    /**
     * Registro liviano de uso por área dentro de `/admin` (estadística).
     */
    'usage_tracking' => [
        'enabled' => filter_var(env('CENTINELA_USAGE_TRACKING_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
        'throttle_seconds' => (int) env('CENTINELA_USAGE_TRACKING_THROTTLE_SECONDS', 300),

        /**
         * Agrupa COMGES en el bucket `compromisos` (incl. BREAD Voyager relacionados).
         * Debe mantenerse alineado con `App\Support\ChatIa\PageContextResolver`.
         *
         * @var array<int, string>
         */
        'comges_path_prefixes' => [
            'admin/compromisos',
            'admin/indicadores-comges',
            'admin/cortes-comges',
            'admin/comges/',
        ],

        /**
         * @var array<int, string>
         */
        'comges_route_name_contains' => [
            'compromisos',
            'indicadores-comges',
            'cortes-comges',
            'sensibilidad-comges',
            'visualizacion-comges',
            'tablas-sensibilidad-comges',
            'configurar-indicador-comges',
            'acciones-comges',
            'evaluaciones-comges',
        ],
    ],

    /**
     * Resumen SDD-118 en `voyager.dashboard` (usuarios no admin): TTL de caché de lectura.
     *
     * @see `.env.example` CENTINELA_DASHBOARD_CACHE_TTL
     */
    'dashboard_non_admin' => [
        /**
         * Resumen de cumplimiento en /admin para usuarios no admin (SDD-118).
         *
         * @see `.env.example` CENTINELA_DASHBOARD_NON_ADMIN_ENABLED
         */
        'enabled' => filter_var(env('CENTINELA_DASHBOARD_NON_ADMIN_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'dashboard_summary' => [
        'cache_ttl_seconds' => max(30, (int) env('CENTINELA_DASHBOARD_CACHE_TTL', 180)),
    ],

    /**
     * Deploy interno (CI/CD híbrido intranet): orquestador + schedule.
     */
    'deploy' => [
        'automatic_enabled' => filter_var(env('CENTINELA_DEPLOY_AUTOMATIC_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
        'remote' => env('CENTINELA_DEPLOY_REMOTE', 'origin'),
        'branch' => env('CENTINELA_DEPLOY_BRANCH', 'main'),
        'git_binary' => env('CENTINELA_GIT_BINARY', 'git'),
        'run_composer_after_pull' => filter_var(
            env('CENTINELA_DEPLOY_RUN_COMPOSER', env('APP_ENV') === 'production'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'run_npm_build_after_pull' => filter_var(
            env('CENTINELA_DEPLOY_RUN_NPM_BUILD', env('APP_ENV') === 'production'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'run_livewire_publish_after_composer' => filter_var(
            env('CENTINELA_DEPLOY_RUN_LIVEWIRE_PUBLISH', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        /**
         * Rutas generadas en servidor: se restauran al HEAD local antes de git pull
         * para evitar bloqueo cuando upstream deja de versionarlas (v4.5.8+ Livewire).
         *
         * @var list<string>
         */
        'git_generated_paths_before_pull' => [
            'public/vendor/livewire/',
        ],
        'executions_retention_days' => max(1, (int) env('CENTINELA_DEPLOY_EXECUTIONS_RETENTION_DAYS', 90)),
        /**
         * TTL (s) para cerrar filas running/queued sin finished_at (SDD-234).
         * Alineado por defecto a RunManualDeployJob::$timeout (1800).
         */
        'orphan_ttl_seconds' => max(1, (int) env('CENTINELA_DEPLOY_ORPHAN_TTL_SECONDS', 1800)),
        /**
         * Path relativo a storage/ para transcripts scrubbed por etapa (SDD-232).
         */
        'artifacts_path' => env('CENTINELA_DEPLOY_ARTIFACTS_PATH', 'logs/deploy'),
        'git_status_via_http' => filter_var(env('CENTINELA_DEPLOY_GIT_STATUS_VIA_HTTP', false), FILTER_VALIDATE_BOOLEAN),
        /**
         * Gate CI opcional (SDD-100 / ADR-035): none | auto | all
         * none = solo observabilidad; auto = bloquea centinela:deploy:auto; all = auto + manual.
         */
        'require_ci_mode' => env('CENTINELA_DEPLOY_REQUIRE_CI_MODE', 'none'),
        'require_ci_match_sha' => filter_var(env('CENTINELA_DEPLOY_REQUIRE_CI_MATCH_SHA', false), FILTER_VALIDATE_BOOLEAN),
        'require_ci_block_unknown' => filter_var(env('CENTINELA_DEPLOY_REQUIRE_CI_BLOCK_UNKNOWN', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /**
     * Límites de redimensionado al subir medios en /admin/settings (VoyagerSettingMediaService).
     * Alineado con uso real en UI (sidebar, loader, favicon, franja #gradient_bg).
     *
     * @see SettingsManager::mediaFieldHint()
     */
    'settings_media' => [
        'default_quality' => 75,
        'profiles' => [
            'admin.icon_image' => [
                'mode' => 'fit',
                'width' => 128,
                'height' => 128,
                'quality' => 85,
                'upsize' => false,
            ],
            'admin.loader' => [
                'mode' => 'fit',
                'width' => 200,
                'height' => 200,
                'quality' => 85,
                'upsize' => false,
            ],
            'admin.favicon_image' => [
                'mode' => 'fit',
                'width' => 64,
                'height' => 64,
                'quality' => 90,
                'upsize' => false,
            ],
            'admin.bg_image' => [
                'mode' => 'resize',
                'width' => 1920,
                'height' => null,
                'quality' => 82,
                'upsize' => false,
            ],
        ],
    ],

    /**
     * Configuración de respaldos automáticos (backup).
     */
    'backup' => [
        'email_address' => env('BACKUP_EMAIL_ADDRESS', 'admin@minsal.cl'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 7),
        'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 24),
        /** Ventana para omitir db:backup programado si el deploy (u otro proceso) ya respaldó. */
        'schedule_dedupe_minutes' => max(1, (int) env('BACKUP_SCHEDULE_DEDUPE_MINUTES', 180)),
        'db_dump_path' => env('DB_DUMP_PATH', ''),
    ],

    /**
     * Configuración de auditoría.
     */
    'audit' => [
        'retention_months' => (int) env('AUDIT_RETENTION_MONTHS', 6),
    ],

    /**
     * Configuración de WhatsApp (Meta Cloud API).
     */
    'whatsapp' => [
        'enabled' => filter_var(env('WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'alert_to' => env('WHATSAPP_ALERT_TO', ''),
    ],

    /**
     * Configuración del scheduler y monitoreo.
     */
    'scheduler' => [
        'heartbeat_threshold_minutes' => (int) env('SCHEDULER_HEARTBEAT_THRESHOLD_MINUTES', 150),
        'alert_grace_failures' => (int) env('SCHEDULER_ALERT_GRACE_FAILURES', 2),
        'alert_email' => env('SCHEDULER_ALERT_EMAIL', ''),
        'report_emails' => env('SCHEDULER_REPORT_EMAILS', ''),
    ],

    /**
     * Flags de ejecución del schedule (p. ej. vía API del monitor externo).
     */
    'schedule' => [
        'triggered_via_http' => false,
    ],

    /**
     * Configuración del monitoreo del queue worker.
     */
    'worker' => [
        'stale_minutes' => (int) env('WORKER_STALE_MINUTES', 30),
        'pending_warning_jobs' => (int) env('WORKER_PENDING_WARNING_JOBS', 5),
        'failed_jobs_warning_24h' => (int) env('WORKER_FAILED_JOBS_WARNING_24H', 5),
        /** Horas a conservar en `failed_jobs` antes de `queue:prune-failed` (SDD-178). */
        'failed_jobs_retention_hours' => (int) env('WORKER_FAILED_JOBS_RETENTION_HOURS', 168),
    ],

    /**
     * Configuración de seguridad / monitoreo de logins fallidos.
     */
    'security' => [
        'failed_login_threshold' => (int) env('SECURITY_FAILED_LOGIN_THRESHOLD_PER_IP', 10),
        'failed_login_lookback_hours' => (int) env('SECURITY_FAILED_LOGIN_LOOKBACK_HOURS', 24),
        'failed_login_retention_days' => (int) env('SECURITY_LOG_RETENTION_DAYS', 30),
        'alert_email_to' => env('SECURITY_ALERT_EMAIL_TO', ''),
    ],

    /**
     * GitHub releases cache.
     */
    'github_releases' => [
        'cache_seconds' => (int) env('GITHUB_RELEASES_HTTP_CACHE_SECONDS', 3600),
    ],

    /**
     * Permite `migrate:fresh`, `migrate:refresh` y `db:wipe` contra drivers distintos de SQLite cuando el entorno
     * Laravel no es `testing` — SDD-053 / ADR-026.
     *
     * @see `.env.example` CENTINELA_ALLOW_DESTRUCTIVE_DATABASE_COMMANDS
     */
    'allow_destructive_database_commands' => filter_var(
        env('CENTINELA_ALLOW_DESTRUCTIVE_DATABASE_COMMANDS', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Archivos en `storage/temp` y `storage/app/temp` más antiguos que este umbral (horas)
     * pueden eliminarse con `php artisan centinela:clean-storage-temp` (programado en el scheduler).
     * Los dos paths se mantienen separados (exports vs store('temp')); solo se unifica el prune.
     *
     * @see `.env.example` CENTINELA_STORAGE_TEMP_MAX_AGE_HOURS
     */
    'storage_temp' => [
        'max_age_hours' => max(1, (int) env('CENTINELA_STORAGE_TEMP_MAX_AGE_HOURS', 48)),
    ],

    /**
     * Logs dedicados del scheduler (appendOutputTo). Rotación por tamaño + purga de rotados.
     *
     * @see `.env.example` CENTINELA_SCHEDULE_LOGS_RETENTION_DAYS, CENTINELA_SCHEDULE_LOGS_ROTATE_MAX_BYTES
     */
    'schedule_logs' => [
        'retention_days' => max(1, (int) env('CENTINELA_SCHEDULE_LOGS_RETENTION_DAYS', 14)),
        'rotate_max_bytes' => max(1, (int) env('CENTINELA_SCHEDULE_LOGS_ROTATE_MAX_BYTES', 5_242_880)),
    ],

    /**
     * Límites de subida de archivos (SDD-133; techo 50 MB para CSV de paneles públicos).
     * Ops: alinear `upload_max_filesize` / `client_max_body_size` en el servidor.
     *
     * @see `.env.example` CENTINELA_UPLOAD_MAX_KB
     */
    'upload' => [
        'max_kilobytes' => max(1, (int) env('CENTINELA_UPLOAD_MAX_KB', 51200)),
    ],

    /**
     * Catálogo nacional de establecimientos (datos.gob.cl CKAN). Valores operativos vía settings (ADR-032).
     */
    'national_establishments' => [
        'enabled' => false,
        'api_base' => 'https://datos.gob.cl/api/3/action',
        'resource_id' => '2c44d782-3365-44e3-aefb-2c8b8363a1bc',
        'page_size' => 500,
        'timeout_seconds' => 60,
        /** Meses de retención de cen_center_sync_runs / issues (comando cen:sync-runs:prune). */
        'sync_runs_retention_months' => (int) env('CEN_SYNC_RUNS_RETENTION_MONTHS', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings bridge: Voyager settings (BD) → config paths
    |--------------------------------------------------------------------------
    |
    | Mapeo entre claves de la tabla `settings` de Voyager y paths de
    | config('centinela.*'). SettingsBridgeServiceProvider sobreescribe
    | config() al boot con los valores de BD.
    |
    | Los defaults definidos via env() más arriba se usan solo cuando una
    | setting NO existe en BD.
    |
    */
    'settings_bridge' => [

        // === admin.* -> voyager.* ===
        'admin.voyager_compass_enabled' => 'voyager.compass_in_production',

        // === module.* -> centinela.* ===
        'module.soporte_enabled' => 'centinela.support.enabled',
        'module.chat_ia_enabled' => 'centinela.chat_ia.enabled',
        'module.user_manual_enabled' => 'centinela.user_manual.enabled',
        'module.dashboard_non_admin_enabled' => 'centinela.dashboard_non_admin.enabled',
        'module.deploy_automatic_enabled' => 'centinela.deploy.automatic_enabled',
        'module.deploy_require_ci_mode' => 'centinela.deploy.require_ci_mode',
        'module.deploy_require_ci_match_sha' => 'centinela.deploy.require_ci_match_sha',
        'module.deploy_require_ci_block_unknown' => 'centinela.deploy.require_ci_block_unknown',
        'module.github_issues_enabled' => 'centinela.github_issues.enabled',
        'module.login_legal_documents_enabled' => 'centinela.login_legal_documents.enabled',
        'module.whatsapp_enabled' => 'centinela.whatsapp.enabled',
        'module.usage_tracking_enabled' => 'centinela.usage_tracking.enabled',
        'module.metas_recalc_scope_enabled' => 'centinela.metas.recalculation_scope_enabled',

        // === system.* -> centinela.* ===
        'system.storage_temp_max_age' => 'centinela.storage_temp.max_age_hours',
        'system.usage_tracking_throttle' => 'centinela.usage_tracking.throttle_seconds',
        'system.dashboard_cache_ttl' => 'centinela.dashboard_summary.cache_ttl_seconds',
        'system.deploy_executions_retention_days' => 'centinela.deploy.executions_retention_days',
        'system.backup_retention_days' => 'centinela.backup.retention_days',
        'system.backup_max_age_hours' => 'centinela.backup.max_age_hours',
        'system.audit_retention_months' => 'centinela.audit.retention_months',
        'system.scheduler_heartbeat_threshold' => 'centinela.scheduler.heartbeat_threshold_minutes',
        'system.worker_stale_minutes' => 'centinela.worker.stale_minutes',
        'system.worker_pending_warning_jobs' => 'centinela.worker.pending_warning_jobs',
        'system.worker_failed_jobs_warning_24h' => 'centinela.worker.failed_jobs_warning_24h',
        'system.worker_failed_jobs_retention_hours' => 'centinela.worker.failed_jobs_retention_hours',
        'system.support_close_resolved_days' => 'centinela.support.close_resolved_days',
        'system.github_releases_cache_seconds' => 'centinela.github_releases.cache_seconds',
        'system.failed_login_threshold' => 'centinela.security.failed_login_threshold',
        'system.failed_login_lookback_hours' => 'centinela.security.failed_login_lookback_hours',
        'system.failed_login_retention_days' => 'centinela.security.failed_login_retention_days',
        'system.scheduler_alert_grace_failures' => 'centinela.scheduler.alert_grace_failures',
        'system.metas_recalc_debounce_seconds' => 'centinela.metas.recalculation_debounce_seconds',

        // === notifications.* -> centinela.* ===
        'notifications.backup_email' => 'centinela.backup.email_address',
        'notifications.contact_mail_to' => 'centinela.contact_mail_to',
        'notifications.scheduler_alert_email' => 'centinela.scheduler.alert_email',
        'notifications.scheduler_report_emails' => 'centinela.scheduler.report_emails',
        'notifications.security_alert_email' => 'centinela.security.alert_email_to',
        'notifications.whatsapp_alert_to' => 'centinela.whatsapp.alert_to',

        // === services.* -> centinela.* ===
        'services.deploy_branch' => 'centinela.deploy.branch',
        'services.deploy_remote' => 'centinela.deploy.remote',
        'services.deploy_run_composer' => 'centinela.deploy.run_composer_after_pull',
        'services.deploy_run_npm_build' => 'centinela.deploy.run_npm_build_after_pull',
        'services.git_binary_path' => 'centinela.deploy.git_binary',
        'services.github_releases_repo' => 'centinela.github_issues.repo',
        'services.db_dump_path' => 'centinela.backup.db_dump_path',
        'module.national_establishments_enabled' => 'centinela.national_establishments.enabled',
        'services.national_establishments_api_base' => 'centinela.national_establishments.api_base',
        'services.national_establishments_resource_id' => 'centinela.national_establishments.resource_id',
        'system.national_establishments_page_size' => 'centinela.national_establishments.page_size',
        'system.national_establishments_timeout' => 'centinela.national_establishments.timeout_seconds',
    ],
];
