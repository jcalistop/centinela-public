<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Release v4.4.0 — deploy_executions, limpieza/normalización settings, pestaña Deploy y SS en producción.
 *
 * Reemplaza: 083000, 092200, 093500, 095500, 110500, 120000, 120100, 120200, 215829.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const PRODUCTION_SS_NOMBRE_LIKE = [
        '%Reloncav%',
        '%Araucan%Sur%',
        '%Arauco%',
    ];

    /**
     * @var array<string, array{order: int, display_name?: string}>
     */
    private const DEPLOY_SETTINGS_TAB = [
        'module.deploy_automatic_enabled' => [
            'order' => 1,
            'display_name' => 'Deploy automático (scheduler)',
        ],
        'services.deploy_branch' => [
            'order' => 2,
            'display_name' => 'Rama Git para despliegue',
        ],
        'services.deploy_remote' => [
            'order' => 3,
            'display_name' => 'Remote Git para despliegue',
        ],
        'services.git_binary_path' => [
            'order' => 4,
            'display_name' => 'Ruta al binario de Git',
        ],
        'services.deploy_run_composer' => [
            'order' => 5,
        ],
        'services.deploy_run_npm_build' => [
            'order' => 6,
        ],
        'system.deploy_executions_retention_days' => [
            'order' => 7,
            'display_name' => 'Retención historial deploy (días)',
        ],
        'services.github_releases_repo' => [
            'order' => 8,
            'display_name' => 'Repositorio GitHub (owner/repo)',
        ],
    ];

    public function up(): void
    {
        $this->createDeployExecutionsTable();
        $this->cleanupDeployLegacySettingsAndPermissions();
        $this->cleanupOrphanSettingsKeys();
        $this->normalizeSettingsModulesNotificationsSystem();
        $this->addDeployRuntimeSettings();
        $this->reorganizeDeploySettingsTab();
        $this->moveDbDumpPathToSistemaSetting();
        $this->enableProductionServiciosSalud();
    }

    public function down(): void
    {
        $this->disableProductionServiciosSalud();
        $this->rollbackDbDumpPathToServiciosExternos();
        $this->rollbackDeploySettingsTab();
        $this->removeDeployRuntimeSettings();
        $this->dropDeployExecutionsTable();
    }

    private function createDeployExecutionsTable(): void
    {
        if (Schema::hasTable('deploy_executions')) {
            return;
        }

        Schema::create('deploy_executions', function (Blueprint $table): void {
            $table->id();

            $table->string('mode', 20);
            $table->string('status', 30);

            $table->unsignedBigInteger('actor_user_id')->nullable();

            $table->string('from_sha', 40)->nullable();
            $table->string('to_sha', 40)->nullable();
            $table->string('branch', 120)->nullable();

            $table->string('backup_status', 30)->nullable();
            $table->string('migrate_status', 30)->nullable();
            $table->string('rollback_status', 30)->nullable();

            $table->text('summary')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'mode']);
            $table->index(['started_at']);
        });
    }

    private function dropDeployExecutionsTable(): void
    {
        Schema::dropIfExists('deploy_executions');
    }

    private function cleanupDeployLegacySettingsAndPermissions(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'module.deploy_widget_enabled',
                'module.deploy_enabled',
                'module.ops_enabled',
                'module.git_auto_pull_enabled',
                'services.git_auto_pull_branch',
                'services.deploy_fetch_cache_seconds',
                'services.deploy_commits_limit',
            ])
            ->delete();

        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['centinela_deploy', 'centinela_ops'])
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($permissionIds !== []) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }

    private function cleanupOrphanSettingsKeys(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'module.deploy_widget_enabled',
                'module.deploy_enabled',
                'module.ops_enabled',
                'module.git_auto_pull_enabled',
                'services.git_auto_pull_branch',
                'services.deploy_fetch_cache_seconds',
                'services.deploy_commits_limit',
                'module.guest_login_enabled',
                'system.guest_login_throttle',
                'site.title',
                'site.description',
                'site.logo',
                'site.google_analytics_tracking_id',
                'admin.google_analytics_client_id',
                'services.national_establishments_enabled',
                'modulos.module.chat_ia_enabled',
                'modulos.module.dashboard_non_admin_enabled',
                'modulos.module.deploy_automatic_enabled',
                'modulos.module.github_issues_enabled',
                'modulos.module.guest_login_enabled',
                'modulos.module.login_legal_documents_enabled',
                'modulos.module.soporte_enabled',
                'modulos.module.usage_tracking_enabled',
                'modulos.usage_tracking_enabled',
                'modulos.module.user_manual_enabled',
                'modulos.module.whatsapp_enabled',
                'notificaciones.notifications.backup_email',
                'notificaciones.notifications.contact_mail_to',
                'notificaciones.notifications.scheduler_alert_email',
                'notificaciones.notifications.scheduler_report_emails',
                'notificaciones.notifications.security_alert_email',
                'notificaciones.notifications.whatsapp_alert_to',
                'sistema.system.audit_retention_months',
                'sistema.system.backup_max_age_hours',
                'sistema.system.backup_retention_days',
                'sistema.system.dashboard_cache_ttl',
                'sistema.system.failed_login_lookback_hours',
                'sistema.system.failed_login_retention_days',
                'sistema.system.failed_login_threshold',
                'sistema.system.github_releases_cache_seconds',
                'sistema.system.guest_login_throttle',
                'sistema.system.scheduler_alert_grace_failures',
                'sistema.system.scheduler_heartbeat_threshold',
                'sistema.system.storage_temp_max_age',
                'sistema.system.usage_tracking_throttle',
                'sistema.system.worker_failed_jobs_warning_24h',
                'sistema.system.worker_pending_warning_jobs',
                'sistema.system.worker_stale_minutes',
                'servicios-externos.services.db_dump_path',
                'servicios-externos.services.deploy_branch',
                'servicios-externos.services.deploy_remote',
                'servicios-externos.services.git_binary_path',
                'servicios-externos.services.github_releases_repo',
            ])
            ->delete();
    }

    private function normalizeSettingsModulesNotificationsSystem(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $set = static function (string $key, string $value): void {
            DB::table('settings')->where('key', $key)->update(['value' => $value]);
        };

        $moduleKeys = [
            'module.usage_tracking_enabled' => 'On',
            'module.national_establishments_enabled' => 'On',
            'module.dashboard_non_admin_enabled' => 'Off',
            'module.soporte_enabled' => 'Off',
            'module.chat_ia_enabled' => 'Off',
            'module.user_manual_enabled' => 'Off',
            'module.login_legal_documents_enabled' => 'Off',
            'module.github_issues_enabled' => 'Off',
            'module.deploy_automatic_enabled' => 'Off',
            'module.whatsapp_enabled' => 'Off',
        ];

        foreach ($moduleKeys as $key => $value) {
            $set($key, $value);
        }

        $email = 'centinela-ops@ejemplo.gob.cl';
        foreach ([
            'notifications.contact_mail_to',
            'notifications.backup_email',
            'notifications.scheduler_alert_email',
            'notifications.scheduler_report_emails',
            'notifications.security_alert_email',
        ] as $key) {
            $set($key, $email);
        }

        $systemKeys = [
            'system.dashboard_cache_ttl' => '180',
            'system.usage_tracking_throttle' => '300',
            'system.storage_temp_max_age' => '48',
            'system.backup_retention_days' => '14',
            'system.backup_max_age_hours' => '28',
            'system.audit_retention_months' => '12',
            'system.worker_stale_minutes' => '15',
            'system.worker_pending_warning_jobs' => '3',
            'system.worker_failed_jobs_warning_24h' => '1',
            'system.failed_login_threshold' => '6',
            'system.failed_login_lookback_hours' => '12',
            'system.failed_login_retention_days' => '30',
            'system.scheduler_heartbeat_threshold' => '60',
            'system.scheduler_alert_grace_failures' => '1',
            'system.github_releases_cache_seconds' => '3600',
            'system.national_establishments_page_size' => '500',
            'system.national_establishments_timeout' => '90',
        ];

        foreach ($systemKeys as $key => $value) {
            $set($key, $value);
        }
    }

    private function addDeployRuntimeSettings(): void
    {
        $settings = [
            [
                'key' => 'services.deploy_run_composer',
                'display_name' => 'Ejecutar composer tras deploy',
                'value' => $this->envToOnOff('CENTINELA_DEPLOY_RUN_COMPOSER', env('APP_ENV') === 'production' ? 'true' : 'false'),
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Si está activado, el orquestador ejecuta composer install --no-dev tras git pull cuando hay cambio de código."}',
                'type' => 'radio_btn',
                'order' => 6,
                'group' => 'Servicios externos',
            ],
            [
                'key' => 'services.deploy_run_npm_build',
                'display_name' => 'Ejecutar npm build tras deploy',
                'value' => $this->envToOnOff('CENTINELA_DEPLOY_RUN_NPM_BUILD', env('APP_ENV') === 'production' ? 'true' : 'false'),
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Si está activado, el orquestador ejecuta npm ci && npm run build cuando el diff del deploy toca rutas de frontend."}',
                'type' => 'radio_btn',
                'order' => 7,
                'group' => 'Servicios externos',
            ],
            [
                'key' => 'system.deploy_executions_retention_days',
                'display_name' => 'Retención historial deploy (días)',
                'value' => (string) max(1, (int) env('CENTINELA_DEPLOY_EXECUTIONS_RETENTION_DAYS', 90)),
                'details' => '{"min":1,"step":1,"description":"Días de retención para deploy_executions. El scheduler purga semanalmente los registros más antiguos."}',
                'type' => 'text',
                'order' => 16,
                'group' => 'Sistema',
            ],
        ];

        foreach ($settings as $setting) {
            $exists = DB::table('settings')->where('key', $setting['key'])->exists();
            if ($exists) {
                DB::table('settings')
                    ->where('key', $setting['key'])
                    ->update([
                        'display_name' => $setting['display_name'],
                        'details' => $setting['details'],
                    ]);
            } else {
                DB::table('settings')->insert($setting);
            }
        }
    }

    private function removeDeployRuntimeSettings(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'services.deploy_run_composer',
                'services.deploy_run_npm_build',
                'system.deploy_executions_retention_days',
            ])
            ->delete();
    }

    private function reorganizeDeploySettingsTab(): void
    {
        foreach (self::DEPLOY_SETTINGS_TAB as $key => $meta) {
            $update = [
                'group' => 'Deploy',
                'order' => $meta['order'],
            ];

            if (isset($meta['display_name'])) {
                $update['display_name'] = $meta['display_name'];
            }

            DB::table('settings')
                ->where('key', $key)
                ->update($update);
        }
    }

    private function rollbackDeploySettingsTab(): void
    {
        $rollbackGroups = [
            'module.deploy_automatic_enabled' => ['group' => 'Modulos', 'order' => 9],
            'services.deploy_branch' => ['group' => 'Servicios externos', 'order' => 3],
            'services.deploy_remote' => ['group' => 'Servicios externos', 'order' => 2],
            'services.git_binary_path' => ['group' => 'Servicios externos', 'order' => 1],
            'services.deploy_run_composer' => ['group' => 'Servicios externos', 'order' => 6],
            'services.deploy_run_npm_build' => ['group' => 'Servicios externos', 'order' => 7],
            'system.deploy_executions_retention_days' => ['group' => 'Sistema', 'order' => 16],
            'services.github_releases_repo' => ['group' => 'Servicios externos', 'order' => 5],
        ];

        foreach ($rollbackGroups as $key => $meta) {
            DB::table('settings')
                ->where('key', $key)
                ->update($meta);
        }
    }

    private function moveDbDumpPathToSistemaSetting(): void
    {
        DB::table('settings')
            ->where('key', 'services.db_dump_path')
            ->update([
                'group' => 'Sistema',
                'order' => 5,
                'display_name' => 'Ruta al binario mysqldump',
            ]);
    }

    private function rollbackDbDumpPathToServiciosExternos(): void
    {
        DB::table('settings')
            ->where('key', 'services.db_dump_path')
            ->update([
                'group' => 'Servicios externos',
                'order' => 4,
            ]);
    }

    private function enableProductionServiciosSalud(): void
    {
        if (! Schema::hasTable('cen_hie_dependencies') || ! Schema::hasColumn('cen_hie_dependencies', 'habilitado')) {
            return;
        }

        $now = now();

        DB::table('cen_hie_dependencies')
            ->where('tipo_dependencia', 'SERVICIO')
            ->where(function ($query): void {
                foreach (self::PRODUCTION_SS_NOMBRE_LIKE as $pattern) {
                    $query->orWhere('nombre', 'like', $pattern);
                }
            })
            ->update([
                'habilitado' => true,
                'updated_at' => $now,
            ]);
    }

    private function disableProductionServiciosSalud(): void
    {
        if (! Schema::hasTable('cen_hie_dependencies') || ! Schema::hasColumn('cen_hie_dependencies', 'habilitado')) {
            return;
        }

        $now = now();

        DB::table('cen_hie_dependencies')
            ->where('tipo_dependencia', 'SERVICIO')
            ->where(function ($query): void {
                foreach (self::PRODUCTION_SS_NOMBRE_LIKE as $pattern) {
                    $query->orWhere('nombre', 'like', $pattern);
                }
            })
            ->update([
                'habilitado' => false,
                'updated_at' => $now,
            ]);
    }

    private function envToOnOff(string $envKey, string $default): string
    {
        $value = env($envKey, $default);
        if (is_bool($value)) {
            return $value ? 'On' : 'Off';
        }

        return in_array(strtolower((string) $value), ['true', '1', 'on'], true) ? 'On' : 'Off';
    }
};
