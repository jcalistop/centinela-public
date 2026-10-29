<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'github' => [
        /**
         * PAT único: misma variable que crear issues soporte (`config('centinela.github_issues.token')`).
         * Compatibilidad: si CENTINELA_GITHUB_TOKEN está vacío, se usa RELEASES_TOKEN (deprecado).
         */
        'releases_token' => env('CENTINELA_GITHUB_TOKEN') ?: env('RELEASES_TOKEN'),
        /** Mismo `owner/repo` para API releases y promoción Soporte→issue (`config('centinela.github_issues.repo')`). */
        'releases_repository' => env('GITHUB_RELEASES_REPOSITORY', 'jcalistop/centinela-public'),
        'releases_http_cache_seconds' => (int) env('GITHUB_RELEASES_HTTP_CACHE_SECONDS', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API (Meta)
    |--------------------------------------------------------------------------
    |
    | Configuración para integración con WhatsApp Business Cloud API.
    | Obtener credenciales desde: https://developers.facebook.com/
    |
    */

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'api_token' => env('WHATSAPP_CLOUD_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'alert_to' => env('WHATSAPP_ALERT_TO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuración para el sistema de monitoreo del scheduler de Laravel.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Scheduler / worker (alias deprecados — SDD-178)
    |--------------------------------------------------------------------------
    |
    | Lectores de health/report usan config('centinela.scheduler|worker.*').
    |
    */

    'scheduler' => [
        'alert_email_to' => env('SCHEDULER_ALERT_EMAIL_TO', 'admin@minsal.cl'),
        'report_emails' => env('SCHEDULER_REPORT_EMAILS', 'admin@minsal.cl'),
        'heartbeat_threshold_minutes' => env('SCHEDULER_HEARTBEAT_THRESHOLD_MINUTES', 150),
        'alert_grace_failures' => env('SCHEDULER_ALERT_GRACE_FAILURES', 2),
    ],

    'worker' => [
        'stale_minutes' => env('WORKER_STALE_MINUTES', 30),
        'pending_warning_jobs' => env('WORKER_PENDING_WARNING_JOBS', 5),
        'failed_jobs_warning_24h' => env('WORKER_FAILED_JOBS_WARNING_24H', 5),
        'failed_jobs_retention_hours' => env('WORKER_FAILED_JOBS_RETENTION_HOURS', 168),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup / audit / security (alias deprecados — SDD-177)
    |--------------------------------------------------------------------------
    |
    | Los lectores de aplicación usan config('centinela.*'). Estas claves
    | permanecen por compatibilidad de env / código legacy; defaults alineados.
    |
    */

    'backup' => [
        'email_address' => env('BACKUP_EMAIL_ADDRESS'),
        'retention_days' => env('BACKUP_RETENTION_DAYS', 7),
        'db_username' => env('BACKUP_DB_USERNAME'),
        'db_password' => env('BACKUP_DB_PASSWORD'),
        'dump_path' => env('DB_DUMP_PATH'),
        'max_age_hours' => env('BACKUP_MAX_AGE_HOURS', 24),
    ],

    'audit' => [
        'retention_months' => env('AUDIT_RETENTION_MONTHS', 6),
    ],

    'security' => [
        'failed_login_threshold_per_ip' => env('SECURITY_FAILED_LOGIN_THRESHOLD_PER_IP', 10),
        'failed_login_lookback_hours' => env('SECURITY_FAILED_LOGIN_LOOKBACK_HOURS', 24),
        'alert_email_to' => env('SECURITY_ALERT_EMAIL_TO'),
        'log_retention_days' => env('SECURITY_LOG_RETENTION_DAYS', 30),
    ],

];
