<?php

use App\Models\ServiceModuleToggle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Release v4.2+ — settings Voyager desde .env + columna habilitado y service_module_toggles.
 *
 * Reemplaza: 2026_05_15_000100_setup_env_settings, 2026_05_15_000200_schema_habilitado_toggles.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setupEnvSettings();
        $this->schemaHabilitadoAndModuleToggles();
    }

    public function down(): void
    {
        $this->rollbackSchemaHabilitadoAndModuleToggles();
        $this->rollbackEnvSettings();
    }

    private function setupEnvSettings(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'site.title',
                'site.description',
                'site.logo',
                'site.google_analytics_tracking_id',
                'admin.google_analytics_client_id',
                'module.guest_login_enabled',
                'system.guest_login_throttle',
            ])
            ->delete();

        $prefixed = [
            'modulos.module.chat_ia_enabled',
            'modulos.module.dashboard_non_admin_enabled',
            'modulos.module.deploy_automatic_enabled',
            'modulos.module.github_issues_enabled',
            'modulos.module.guest_login_enabled',
            'modulos.module.login_legal_documents_enabled',
            'modulos.module.soporte_enabled',
            'modulos.module.usage_tracking_enabled',
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
        ];

        DB::table('settings')->whereIn('key', $prefixed)->delete();

        $settings = $this->envSettingsDefinitions();

        $order = 1;
        foreach ($settings as &$setting) {
            if (! isset($setting['order'])) {
                $setting['order'] = $order;
            }

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

            $order++;
        }
    }

    private function schemaHabilitadoAndModuleToggles(): void
    {
        if (! Schema::hasColumn('cen_hie_dependencies', 'habilitado')) {
            Schema::table('cen_hie_dependencies', function (Blueprint $table): void {
                $table->boolean('habilitado')
                    ->default(false)
                    ->after('tipo_dependencia');
            });
        }

        DB::table('cen_hie_dependencies')
            ->where('tipo_dependencia', 'SERVICIO')
            ->update(['habilitado' => false]);

        if (! Schema::hasTable('service_module_toggles')) {
            Schema::create('service_module_toggles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cen_hie_dependency_id')
                    ->constrained('cen_hie_dependencies')
                    ->cascadeOnDelete();
                $table->string('module_key', 64);
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->unique(['cen_hie_dependency_id', 'module_key'], 'uq_smt_ss_module');
            });
        }

        $this->seedDefaultServiceModuleToggles();
    }

    private function rollbackEnvSettings(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'module.usage_tracking_enabled',
                'module.dashboard_non_admin_enabled',
                'module.soporte_enabled',
                'module.chat_ia_enabled',
                'module.user_manual_enabled',
                'module.login_legal_documents_enabled',
                'module.github_issues_enabled',
                'module.deploy_automatic_enabled',
                'module.whatsapp_enabled',
                'notifications.contact_mail_to',
                'notifications.backup_email',
                'notifications.scheduler_alert_email',
                'notifications.scheduler_report_emails',
                'notifications.security_alert_email',
                'notifications.whatsapp_alert_to',
                'system.dashboard_cache_ttl',
                'system.usage_tracking_throttle',
                'system.storage_temp_max_age',
                'system.backup_retention_days',
                'system.backup_max_age_hours',
                'system.audit_retention_months',
                'system.worker_stale_minutes',
                'system.worker_pending_warning_jobs',
                'system.worker_failed_jobs_warning_24h',
                'system.failed_login_threshold',
                'system.failed_login_lookback_hours',
                'system.failed_login_retention_days',
                'system.scheduler_heartbeat_threshold',
                'system.scheduler_alert_grace_failures',
                'system.github_releases_cache_seconds',
                'services.git_binary_path',
                'services.deploy_remote',
                'services.deploy_branch',
                'services.db_dump_path',
                'services.github_releases_repo',
            ])
            ->delete();
    }

    private function rollbackSchemaHabilitadoAndModuleToggles(): void
    {
        Schema::dropIfExists('service_module_toggles');

        if (Schema::hasColumn('cen_hie_dependencies', 'habilitado')) {
            Schema::table('cen_hie_dependencies', function (Blueprint $table): void {
                $table->dropColumn('habilitado');
            });
        }
    }

    private function seedDefaultServiceModuleToggles(): void
    {
        $now = now();
        $modules = ServiceModuleToggle::knownModules();

        DB::table('service_module_toggles')
            ->whereNotIn('module_key', $modules)
            ->delete();

        $ssList = DB::table('cen_hie_dependencies')
            ->where('tipo_dependencia', 'SERVICIO')
            ->get(['id']);

        foreach ($ssList as $ss) {
            foreach ($modules as $moduleKey) {
                $exists = DB::table('service_module_toggles')
                    ->where('cen_hie_dependency_id', $ss->id)
                    ->where('module_key', $moduleKey)
                    ->exists();

                if (! $exists) {
                    DB::table('service_module_toggles')->insert([
                        'cen_hie_dependency_id' => $ss->id,
                        'module_key' => $moduleKey,
                        'enabled' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function envSettingsDefinitions(): array
    {
        return [
            [
                'key' => 'module.usage_tracking_enabled',
                'display_name' => 'Tracking de uso',
                'value' => $this->envToOnOff('CENTINELA_USAGE_TRACKING_ENABLED', 'false'),
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Registra las visitas a las páginas del panel /admin para estadísticas de uso."}',
                'type' => 'radio_btn',
                'order' => 1,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.dashboard_non_admin_enabled',
                'display_name' => 'Dashboard resumen para usuarios no administradores',
                'value' => $this->envToOnOff('CENTINELA_DASHBOARD_NON_ADMIN_ENABLED', 'true'),
                'details' => '{"default":"On","options":{"On":"Activado","Off":"Desactivado"},"description":"Muestra un dashboard con indicadores resumidos para roles distintos a administrador."}',
                'type' => 'radio_btn',
                'order' => 2,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.soporte_enabled',
                'display_name' => 'Módulo de Soporte',
                'value' => $this->envToOnOff('CENTINELA_SUPPORT_ENABLED', 'false'),
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Habilita el módulo de tickets internos de soporte en el panel /admin."}',
                'type' => 'radio_btn',
                'order' => 3,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.chat_ia_enabled',
                'display_name' => 'Chat IA',
                'value' => $this->envToOnOff('CENTINELA_CHAT_IA_ENABLED', 'false'),
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Activa el asistente conversational con IA disponible como drawer global en el panel /admin."}',
                'type' => 'radio_btn',
                'order' => 4,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.user_manual_enabled',
                'display_name' => 'Manual de usuario contextual',
                'value' => $this->envToOnOff('CENTINELA_USER_MANUAL_ENABLED', 'false'),
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Agrega un enlace de Ayuda contextual en el panel /admin que abre el manual de usuario según la página visitada."}',
                'type' => 'radio_btn',
                'order' => 5,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.login_legal_documents_enabled',
                'display_name' => 'Documentos legales en login',
                'value' => $this->envToOnOff('CENTINELA_LOGIN_LEGAL_DOCUMENTS_ENABLED', 'true'),
                'details' => '{"default":"On","options":{"On":"Activado","Off":"Desactivado"},"description":"Muestra enlaces a términos de uso y política de privacidad en la pantalla de inicio de sesión."}',
                'type' => 'radio_btn',
                'order' => 6,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.github_issues_enabled',
                'display_name' => 'Promoción a issue GitHub',
                'value' => $this->envToOnOff('CENTINELA_GITHUB_ISSUES_ENABLED', 'false'),
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Agrega un botón en tickets de soporte para promoverlos a issues en el repositorio GitHub del proyecto."}',
                'type' => 'radio_btn',
                'order' => 8,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.deploy_automatic_enabled',
                'display_name' => 'Deploy automático (scheduler)',
                'value' => $this->envToOnOff('CENTINELA_DEPLOY_AUTOMATIC_ENABLED', 'false'),
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Habilita el despliegue automático desde el scheduler. Si falla un deploy, queda bloqueado hasta que un deploy manual sea exitoso."}',
                'type' => 'radio_btn',
                'order' => 9,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.whatsapp_enabled',
                'display_name' => 'Notificaciones WhatsApp',
                'value' => $this->envToOnOff('WHATSAPP_ENABLED', 'false'),
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Habilita el envío de notificaciones y alertas a través de WhatsApp."}',
                'type' => 'radio_btn',
                'order' => 13,
                'group' => 'Modulos',
            ],
            [
                'key' => 'notifications.contact_mail_to',
                'display_name' => 'Correo formulario de contacto',
                'value' => env('CENTINELA_CONTACT_MAIL_TO', 'centinela-ops@ejemplo.gob.cl'),
                'details' => '{"description":"Dirección de correo donde se reciben los mensajes enviados desde el formulario de contacto en /admin/acerca-de."}',
                'type' => 'text',
                'order' => 1,
                'group' => 'Notificaciones',
            ],
            [
                'key' => 'notifications.backup_email',
                'display_name' => 'Correo para notificaciones de respaldos',
                'value' => env('BACKUP_EMAIL_ADDRESS', 'admin@minsal.cl'),
                'details' => '{"description":"Dirección de correo que recibe notificaciones sobre el estado de los respaldos de base de datos."}',
                'type' => 'text',
                'order' => 2,
                'group' => 'Notificaciones',
            ],
            [
                'key' => 'notifications.scheduler_alert_email',
                'display_name' => 'Correo para alertas del scheduler',
                'value' => env('SCHEDULER_ALERT_EMAIL_TO', 'admin@minsal.cl'),
                'details' => '{"description":"Dirección de correo que recibe alertas cuando el scheduler de tareas programadas falls o se detiene."}',
                'type' => 'text',
                'order' => 3,
                'group' => 'Notificaciones',
            ],
            [
                'key' => 'notifications.scheduler_report_emails',
                'display_name' => 'Correos para reporte diario del scheduler',
                'value' => env('SCHEDULER_REPORT_EMAILS', 'admin@minsal.cl'),
                'details' => '{"description":"Dirección(es) de correo separadas por coma que reciben el reporte diario de ejecución del scheduler."}',
                'type' => 'text',
                'order' => 4,
                'group' => 'Notificaciones',
            ],
            [
                'key' => 'notifications.security_alert_email',
                'display_name' => 'Correo para alertas de seguridad',
                'value' => env('SECURITY_ALERT_EMAIL_TO', ''),
                'details' => '{"description":"Dirección de correo que recibe alertas de seguridad, como intentos de acceso fallidos masivos."}',
                'type' => 'text',
                'order' => 5,
                'group' => 'Notificaciones',
            ],
            [
                'key' => 'notifications.whatsapp_alert_to',
                'display_name' => 'Número WhatsApp para alertas',
                'value' => env('WHATSAPP_ALERT_TO', ''),
                'details' => '{"description":"Número de teléfono con código de país (ej: 569XXXXXXXX) que recibe alertas vía WhatsApp."}',
                'type' => 'text',
                'order' => 6,
                'group' => 'Notificaciones',
            ],
            [
                'key' => 'system.dashboard_cache_ttl',
                'display_name' => 'TTL caché del dashboard',
                'value' => (string) (int) env('CENTINELA_DASHBOARD_CACHE_TTL', 180),
                'details' => '{"min":30,"step":1,"description":"Tiempo en segundos que los datos del dashboard permanecen en caché antes de refrescarse. Mínimo recomendado: 30s."}',
                'type' => 'text',
                'order' => 1,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.usage_tracking_throttle',
                'display_name' => 'Throttle de tracking de uso',
                'value' => (string) (int) env('CENTINELA_USAGE_TRACKING_THROTTLE_SECONDS', 300),
                'details' => '{"min":10,"step":1,"description":"Intervalo mínimo en segundos entre registros de tracking de uso para una misma página. Evita sobrecarga de escritura."}',
                'type' => 'text',
                'order' => 2,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.storage_temp_max_age',
                'display_name' => 'Edad máxima archivos temporales',
                'value' => (string) max(1, (int) env('CENTINELA_STORAGE_TEMP_MAX_AGE_HOURS', 48)),
                'details' => '{"min":1,"step":1,"description":"Horas máximas que un archivo temporal puede permanecer en storage antes de ser eliminado por la limpieza programada."}',
                'type' => 'text',
                'order' => 3,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.backup_retention_days',
                'display_name' => 'Retención de respaldos',
                'value' => (string) (int) env('BACKUP_RETENTION_DAYS', 7),
                'details' => '{"min":1,"step":1,"description":"Días que se conservan los respaldos de base de datos antes de ser eliminados automáticamente."}',
                'type' => 'text',
                'order' => 4,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.backup_max_age_hours',
                'display_name' => 'Horas máximas sin respaldo para alerta',
                'value' => (string) (int) env('BACKUP_MAX_AGE_HOURS', 40),
                'details' => '{"min":1,"step":1,"description":"Si el respaldo más reciente supera esta edad en horas, se dispara una alerta notificando que no se han generado respaldos recientemente."}',
                'type' => 'text',
                'order' => 5,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.audit_retention_months',
                'display_name' => 'Retención de auditoría',
                'value' => (string) (int) env('AUDIT_RETENTION_MONTHS', 6),
                'details' => '{"min":1,"step":1,"description":"Meses que se conservan los registros de auditoría (logs de acciones de usuarios) antes de ser purgados."}',
                'type' => 'text',
                'order' => 6,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.worker_stale_minutes',
                'display_name' => 'Tiempo sin procesar jobs (worker caído)',
                'value' => (string) (int) env('WORKER_STALE_MINUTES', 30),
                'details' => '{"min":1,"step":1,"description":"Minutos sin que se procesen jobs en cola para considerar que el worker de colas está caído y disparar una alerta."}',
                'type' => 'text',
                'order' => 7,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.worker_pending_warning_jobs',
                'display_name' => 'Jobs pendientes antes de alertar',
                'value' => (string) (int) env('WORKER_PENDING_WARNING_JOBS', 5),
                'details' => '{"min":1,"step":1,"description":"Cantidad de jobs pendientes en la cola a partir de la cual se considera que el worker podría estar rezagado y se dispara una alerta."}',
                'type' => 'text',
                'order' => 8,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.worker_failed_jobs_warning_24h',
                'display_name' => 'Jobs fallidos en 24h antes de alertar',
                'value' => (string) (int) env('WORKER_FAILED_JOBS_WARNING_24H', 5),
                'details' => '{"min":1,"step":1,"description":"Cantidad de jobs fallidos en las últimas 24 horas que disparan una alerta de salud del worker."}',
                'type' => 'text',
                'order' => 9,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.failed_login_threshold',
                'display_name' => 'Intentos de login fallidos por IP (alerta)',
                'value' => (string) (int) env('SECURITY_FAILED_LOGIN_THRESHOLD_PER_IP', 10),
                'details' => '{"min":1,"step":1,"description":"Número de intentos fallidos de inicio de sesión desde una misma IP que disparan una alerta de seguridad."}',
                'type' => 'text',
                'order' => 10,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.failed_login_lookback_hours',
                'display_name' => 'Ventana de horas para intentos fallidos',
                'value' => (string) (int) env('SECURITY_FAILED_LOGIN_LOOKBACK_HOURS', 24),
                'details' => '{"min":1,"step":1,"description":"Ventana de tiempo en horas hacia atrás que se analiza para contar intentos fallidos de login por IP."}',
                'type' => 'text',
                'order' => 11,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.failed_login_retention_days',
                'display_name' => 'Retención de logs de login fallidos',
                'value' => (string) (int) env('SECURITY_LOG_RETENTION_DAYS', 30),
                'details' => '{"min":1,"step":1,"description":"Días que se conservan los registros de intentos de inicio de sesión fallidos antes de ser eliminados."}',
                'type' => 'text',
                'order' => 12,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.scheduler_heartbeat_threshold',
                'display_name' => 'Umbral para scheduler caído',
                'value' => (string) (int) env('SCHEDULER_HEARTBEAT_THRESHOLD_MINUTES', 150),
                'details' => '{"min":1,"step":1,"description":"Minutos sin heartbeat del scheduler para considerarlo caído y disparar una alerta."}',
                'type' => 'text',
                'order' => 13,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.scheduler_alert_grace_failures',
                'display_name' => 'Fallos consecutivos scheduler antes de alertar',
                'value' => (string) (int) env('SCHEDULER_ALERT_GRACE_FAILURES', 2),
                'details' => '{"min":1,"step":1,"description":"Número de ejecuciones fallidas consecutivas del scheduler antes de enviar una alerta."}',
                'type' => 'text',
                'order' => 14,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.github_releases_cache_seconds',
                'display_name' => 'TTL caché releases GitHub',
                'value' => (string) (int) env('GITHUB_RELEASES_HTTP_CACHE_SECONDS', 3600),
                'details' => '{"min":0,"step":1,"description":"Tiempo en segundos que se cachean las releases de GitHub en el widget del dashboard. 0 para deshabilitar la caché."}',
                'type' => 'text',
                'order' => 15,
                'group' => 'Sistema',
            ],
            [
                'key' => 'services.git_binary_path',
                'display_name' => 'Ruta al binario de Git',
                'value' => env('CENTINELA_GIT_BINARY', 'git'),
                'details' => '{"description":"Ruta absoluta o comando del binario de Git en el servidor. Por defecto usa git (debe estar en PATH)."}',
                'type' => 'text',
                'order' => 1,
                'group' => 'Servicios externos',
            ],
            [
                'key' => 'services.deploy_remote',
                'display_name' => 'Remote Git para despliegue',
                'value' => env('CENTINELA_DEPLOY_REMOTE', 'origin'),
                'details' => '{"description":"Nombre del remote de Git que se usará como destino para las operaciones de despliegue."}',
                'type' => 'text',
                'order' => 2,
                'group' => 'Servicios externos',
            ],
            [
                'key' => 'services.deploy_branch',
                'display_name' => 'Rama Git para despliegue',
                'value' => env('CENTINELA_DEPLOY_BRANCH', 'main'),
                'details' => '{"description":"Nombre de la rama de Git que se desplegará en las operaciones de deploy."}',
                'type' => 'text',
                'order' => 3,
                'group' => 'Servicios externos',
            ],
            [
                'key' => 'services.db_dump_path',
                'display_name' => 'Ruta al binario mysqldump',
                'value' => env('DB_DUMP_PATH', ''),
                'details' => '{"description":"Ruta absoluta al binario mysqldump para generar respaldos de base de datos. Vacío si está en PATH."}',
                'type' => 'text',
                'order' => 4,
                'group' => 'Servicios externos',
            ],
            [
                'key' => 'services.github_releases_repo',
                'display_name' => 'Repositorio GitHub (owner/repo)',
                'value' => env('GITHUB_RELEASES_REPOSITORY', 'jcalistop/minsal-centinela'),
                'details' => '{"description":"Repositorio en formato owner/repo (ej: jcalistop/minsal-centinela) usado para consultar releases desde el widget del dashboard."}',
                'type' => 'text',
                'order' => 5,
                'group' => 'Servicios externos',
            ],
        ];
    }

    private function envToOnOff(string $envKey, string $default): string
    {
        $value = env($envKey, $default);
        if (is_bool($value)) {
            return $value ? 'On' : 'Off';
        }

        $lower = strtolower((string) $value);

        return in_array($lower, ['true', '1', 'on'], true) ? 'On' : 'Off';
    }
};
