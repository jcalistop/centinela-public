<#
.SYNOPSIS
    Centinela External Monitor - Orquestador principal
.DESCRIPTION
    Ejecuta el ciclo de monitoreo: health check, acciones correctivas, alertas y reportes.
    Disenado para ejecutarse cada 5 minutos desde el Programador de Tareas de Windows.
.PARAMETER ConfigPath
    Ruta al archivo de configuracion monitor.config.json
.EXAMPLE
    powershell -File CentinelMonitoR.ps1 -ConfigPath config/monitor.config.json
#>
param(
    [string]$ConfigPath = ''
)

# Exit codes
$EXIT_OK = 0
$EXIT_ALERT = 2

# Resolve paths
$scriptDir = Split-Path -Path $PSCommandPath -Parent
if (-not $ConfigPath) {
    $ConfigPath = Join-Path -Path $scriptDir -ChildPath 'config\monitor.config.json'
}
if (-not (Test-Path -Path $ConfigPath)) {
    Write-Error "Archivo de configuracion no encontrado: $ConfigPath"
    exit $EXIT_ALERT
}

# Load config
$config = Get-Content -Path $ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json
$baseUrl = $config.baseUrl
$reporting = $config.reporting
$schedulerInterval = if ($config.schedulerRunIntervalMinutes) { [int]$config.schedulerRunIntervalMinutes } else { 60 }
$defaultTimeout = if ($config.httpTimeoutSeconds) { [int]$config.httpTimeoutSeconds } else { 30 }
$alertThreshold = if ($config.alertAfterConsecutiveFailures) { [int]$config.alertAfterConsecutiveFailures } else { 3 }
if ($alertThreshold -lt 1) { $alertThreshold = 1 }

# Per-endpoint timeouts with fallback to default
$cfgTimeouts = if ($config.timeouts) { $config.timeouts } else { @{} }
$timeouts = @{
    health        = if ($cfgTimeouts.health)        { [int]$cfgTimeouts.health }        else { $defaultTimeout }
    schedule_run  = if ($cfgTimeouts.schedule_run)  { [int]$cfgTimeouts.schedule_run }  else { $defaultTimeout }
    queue_process = if ($cfgTimeouts.queue_process) { [int]$cfgTimeouts.queue_process } else { $defaultTimeout }
    db_backup     = if ($cfgTimeouts.db_backup)     { [int]$cfgTimeouts.db_backup }     else { $defaultTimeout }
}

# Load libraries
$libDir = Join-Path -Path $scriptDir -ChildPath 'lib'
. (Join-Path -Path $libDir -ChildPath 'StateStore.ps1')
. (Join-Path -Path $libDir -ChildPath 'Mailer.ps1')
. (Join-Path -Path $libDir -ChildPath 'HealthApi.ps1')
. (Join-Path -Path $libDir -ChildPath 'ReportTemplates.ps1')

Initialize-MonitorMailer -MonitorConfig $config

# State file path
$configDir = Split-Path -Path $ConfigPath -Parent
$statePath = Get-StateStorePath -ConfigDir $configDir

# Log setup
$logDir = Join-Path -Path $configDir -ChildPath 'logs'
if (-not (Test-Path -Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }
$logFile = Join-Path -Path $logDir -ChildPath "monitor_$(Get-Date -Format 'yyyy-MM-dd').log"
$logRetentionDays = if ($config.logRetentionDays) { [int]$config.logRetentionDays } else { 31 }

function Write-MonitorLog {
    param([string]$Message)
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message"
    Add-Content -Path $script:logFile -Value $line -Encoding UTF8
    Write-Output $line
}

# SDD-196 — ventana del lote nocturno (cron_mail.bat 00:00 + dump). No competir con I/O ni cola releases.
function Test-IsNightBatchWindow {
    param([DateTime]$Now)
    return ($Now.Hour -eq 0 -and $Now.Minute -le 45)
}

function Test-ShouldSkipScheduleRunAtCronMailMidnight {
    param([DateTime]$Now)
    return ($Now.Hour -eq 0 -and $Now.Minute -eq 0)
}

function Test-IsBusinessHoursBackupRecoveryBlocked {
    param([DateTime]$Now)
    return ($Now.Hour -ge 8 -and $Now.Hour -lt 20)
}

function Clear-OldMonitorLogs {
    param([string]$LogDir, [int]$RetentionDays)
    $cutoff = (Get-Date).AddDays(-$RetentionDays)
    $oldLogs = Get-ChildItem -Path $LogDir -Filter 'monitor_*.log' | Where-Object { $_.LastWriteTime -lt $cutoff }
    $count = 0
    foreach ($log in $oldLogs) {
        Remove-Item -Path $log.FullName -Force
        $count++
    }
    if ($count -gt 0) { Write-MonitorLog "Limpieza: $count log(s) antiguo(s) eliminado(s) (retencion: $RetentionDays dias)" }
}

function Send-ScheduledMonitorReports {
    param(
        [object]$State,
        [DateTime]$Now,
        [object]$Reporting,
        [string]$LogDir,
        [int]$LogRetentionDays,
        [string]$BaseUrl,
        [hashtable]$Timeouts
    )
    $reportTime = if ($Reporting.dailyAt) { $Reporting.dailyAt } else { '07:00' }
    $todayStr = $Now.ToString('yyyy-MM-dd')
    $mailLog = { param($m) Write-MonitorLog $m }

    $needIntraSnapshot = (Test-ShouldSendDailyReport -State $State -ReportTime $reportTime) -or (Test-ShouldSendWeeklyReport -State $State -ReportTime $reportTime)
    $intraHealth = $null
    $intraHealthError = ''
    if ($needIntraSnapshot) {
        Write-MonitorLog 'Consultando GET /api/health completo para digest (INTRA/Kernel)...'
        $intraResult = Invoke-HealthApi -BaseUrl $BaseUrl -TimeoutSeconds $Timeouts.health -Compact $false
        if ($intraResult.success) {
            $intraHealth = $intraResult.health
        } else {
            $intraHealthError = if ($intraResult.error) { $intraResult.error } else { 'health no disponible' }
            Write-MonitorLog "Health completo INTRA falló: $intraHealthError"
        }
    }

    if (Test-ShouldSendDailyReport -State $State -ReportTime $reportTime) {
        Write-MonitorLog 'Generando reporte diario...'
        $yesterday = $Now.AddDays(-1).ToString('yyyy-MM-dd')
        $yesterdayLog = $State.dailyLogs | Where-Object { $_.date -eq $yesterday } | Select-Object -First 1
        if ($yesterdayLog) {
            $html = New-DailyReportHtml -Date $yesterday -Events $yesterdayLog.events -TotalCycles $yesterdayLog.cycles -IntraHealth $intraHealth -IntraHealthError $intraHealthError
            $subject = Get-DailyReportSubject -Date $yesterday
            $sent = Send-MonitorEmail -Subject $subject -BodyHtml $html -OnLog $mailLog
            if ($sent) {
                $State.lastReportSent.daily = "$todayStr`T$reportTime"
                Write-MonitorLog 'Reporte diario enviado'
                Clear-OldMonitorLogs -LogDir $LogDir -RetentionDays $LogRetentionDays
            }
        } else {
            Write-MonitorLog "Reporte diario omitido (sin eventos para $yesterday)"
        }
    }

    if (Test-ShouldSendWeeklyReport -State $State -ReportTime $reportTime) {
        Write-MonitorLog 'Generando reporte semanal...'
        $weekEnd = $Now.AddDays(-1).ToString('yyyy-MM-dd')
        $weekStart = $Now.AddDays(-7).ToString('yyyy-MM-dd')
        $weekLogs = $State.dailyLogs | Where-Object { $_.date -ge $weekStart -and $_.date -le $weekEnd }
        if ($weekLogs -and @($weekLogs).Count -gt 0) {
            $totalWeekCycles = ($weekLogs | ForEach-Object { $_.cycles }) | Measure-Object -Sum | Select-Object -ExpandProperty Sum
            $html = New-WeeklyReportHtml -WeekStart $weekStart -WeekEnd $weekEnd -DailyLogs $weekLogs -TotalCycles $totalWeekCycles -IntraHealth $intraHealth -IntraHealthError $intraHealthError
            $subject = Get-WeeklyReportSubject -WeekStart $weekStart
            $sent = Send-MonitorEmail -Subject $subject -BodyHtml $html -OnLog $mailLog
            if ($sent) {
                $State.lastReportSent.weekly = "$todayStr`T$reportTime"
                Write-MonitorLog 'Reporte semanal enviado'
                Clear-OldMonitorLogs -LogDir $LogDir -RetentionDays $LogRetentionDays
            }
        } else {
            Write-MonitorLog 'Reporte semanal omitido (sin datos de la semana)'
        }
    }
}

# Load state
$state = Read-StateStore -Path $statePath
$now = Get-Date
$timestamp = $now.ToString('yyyy-MM-dd HH:mm:ss')
$currentMinute = $now.Minute

Write-MonitorLog "Inicio ciclo de monitoreo - URL: $baseUrl"

# Step 1: GET /api/health
$useCompact = ($currentMinute -ne 0)
$modeLabel = if ($useCompact) { 'compacto' } else { 'completo' }
Write-MonitorLog "Consultando GET /api/health (modo $modeLabel)..."
$healthResult = Invoke-HealthApi -BaseUrl $baseUrl -TimeoutSeconds $timeouts.health -Compact $useCompact

if (-not $healthResult.success) {
    $state.consecutiveHealthFailures = [int]$state.consecutiveHealthFailures + 1
    Write-MonitorLog "Health check FALLIDO ($($state.consecutiveHealthFailures)/$alertThreshold consecutivos): $($healthResult.error)"
    Add-StateEvent -State $state -Type 'health_fail' -Success $false -Error $healthResult.error

    $sendAlert = $false
    if ($state.consecutiveHealthFailures -ge $alertThreshold) {
        $sendAlert = $true
        if ($state.lastImmediateAlert) {
            $lastAlert = [DateTime]::Parse($state.lastImmediateAlert)
            $hoursSince = ($now - $lastAlert).TotalHours
            if ($hoursSince -lt 2) { $sendAlert = $false }
        }
    }
    if ($sendAlert) {
        Write-MonitorLog "Enviando alerta inmediata..."
        $subject = Get-ImmediateAlertSubject
        $body = Get-ImmediateAlertBody -ErrorDetail "No se pudo obtener GET /api/health. Error: $($healthResult.error)" -Timestamp $timestamp -ConsecutiveFailures $state.consecutiveHealthFailures -Threshold $alertThreshold
        $mailLog = { param($m) Write-MonitorLog $m }
        $sent = Send-MonitorEmail -Subject $subject -BodyHtml $body -OnLog $mailLog
        if ($sent) {
            $state.lastImmediateAlert = $now.ToString('yyyy-MM-ddTHH:mm')
            Write-MonitorLog "Alerta inmediata enviada"
        }
    } elseif ($state.consecutiveHealthFailures -lt $alertThreshold) {
        Write-MonitorLog "Alerta omitida ($($state.consecutiveHealthFailures)/$alertThreshold fallos consecutivos)"
    } else {
        Write-MonitorLog "Alerta omitida (ultima alerta hace menos de 2h)"
    }

    Send-ScheduledMonitorReports -State $state -Now $now -Reporting $reporting -LogDir $logDir -LogRetentionDays $logRetentionDays -BaseUrl $baseUrl -Timeouts $timeouts
    Write-StateStore -Path $statePath -State $state
    exit $EXIT_ALERT
}

Write-MonitorLog "Health check OK"
$state.consecutiveHealthFailures = 0

$health = $healthResult.health
$schedulerStatus = $health.scheduler.status
$workerStatus = $health.worker.status
$pendingJobs = if ($health.worker.pending_jobs) { [int]$health.worker.pending_jobs } else { 0 }
$backupStatus = $health.backup.status
$securityLogins = if ($health.security.failed_logins_24h) { [int]$health.security.failed_logins_24h } else { 0 }

Write-MonitorLog "Estado: scheduler=$schedulerStatus worker=$workerStatus (jobs=$pendingJobs) backup=$backupStatus security=$securityLogins"

# Load secrets
$secrets = Import-MonitorSecrets
$bearerToken = $secrets.bearerToken
$cronToken = $secrets.cronToken

# Step 2: Scheduler recovery (no a las 00:00: dueño cron_mail.bat)
Write-MonitorLog "Evaluando scheduler..."
$minutesSince = $null
$shouldRunScheduler = ($currentMinute -eq 0)
if ($shouldRunScheduler -and $state.lastSchedulerRun) {
    $lastRunTime = [DateTime]::Parse($state.lastSchedulerRun)
    $minutesSince = ($now - $lastRunTime).TotalMinutes
    if ($minutesSince -lt $schedulerInterval) { $shouldRunScheduler = $false }
}

if (Test-ShouldSkipScheduleRunAtCronMailMidnight -Now $now) {
    Write-MonitorLog "schedule:run omitido (00:00: dueño cron_mail.bat / cola releases)"
} elseif ($shouldRunScheduler) {
    Write-MonitorLog "Ejecutando POST /api/schedule/run..."
    $schedResult = Invoke-ScheduleApiPost -BaseUrl $baseUrl -Endpoint '/api/schedule/run' -BearerToken $bearerToken -CronToken $cronToken -TimeoutSeconds $timeouts.schedule_run
    if ($schedResult.success) {
        Write-MonitorLog "schedule:run exitoso"
        $state.lastSchedulerRun = $now.ToString('yyyy-MM-ddTHH:mm')
        Add-StateEvent -State $state -Type 'scheduler_run' -Success $true
    } else {
        Write-MonitorLog "schedule:run FALLIDO: $($schedResult.error)"
        Add-StateEvent -State $state -Type 'scheduler_run' -Success $false -Error $schedResult.error
    }
} elseif ($null -ne $minutesSince) {
    Write-MonitorLog "schedule:run omitido (ultimo hace $minutesSince min, intervalo $schedulerInterval min)"
} else {
    Write-MonitorLog "schedule:run omitido (fuera de minuto :00)"
}

# Step 3: Drenar cola operativa (default) cada ciclo — nunca cola releases; no en 00:00-00:45
Write-MonitorLog "Drenando cola default via API..."
$inNightBatch = Test-IsNightBatchWindow -Now $now
if ($inNightBatch) {
    Write-MonitorLog "Cola default omitida (ventana lote 00:00-00:45; dueño cron_mail.bat)"
} else {
    $queueMax = if ($config.queueDrainMax) { [int]$config.queueDrainMax } else { 30 }
    $queueResult = Invoke-ScheduleApiPost -BaseUrl $baseUrl -Endpoint '/api/schedule/queue/process' -BearerToken $bearerToken -CronToken $cronToken -TimeoutSeconds $timeouts.queue_process -Body @{ max = $queueMax; queue = 'default' }
    if ($queueResult.success) {
        $processed = if ($queueResult.parsed.jobs_processed) { [int]$queueResult.parsed.jobs_processed } else { 0 }
        Write-MonitorLog "Cola default: $processed jobs procesados"
        if ($processed -gt 0) {
            Add-StateEvent -State $state -Type 'queue_drain' -Success $true -JobsProcessed $processed
        }
    } else {
        Write-MonitorLog "Drenaje cola default FALLIDO: $($queueResult.error)"
        Add-StateEvent -State $state -Type 'queue_drain' -Success $false -Error $queueResult.error
    }
}

# Step 4: Backup recovery (solo con health completo; en compacto backup.status = omitted)
Write-MonitorLog "Evaluando backup..."
if ($useCompact) {
    Write-MonitorLog "Backup no evaluado (health en modo compacto; revision real solo en minuto :00)"
} elseif ($inNightBatch) {
    Write-MonitorLog "Backup recovery omitido (ventana lote 00:00-00:45)"
} elseif (Test-IsBusinessHoursBackupRecoveryBlocked -Now $now) {
    Write-MonitorLog "Backup recovery omitido (horario laboral 08:00-20:00; no mysqldump diurno)"
} elseif ($backupStatus -ne 'ok') {
    Write-MonitorLog "Backup en estado '$backupStatus'. Ejecutando POST /api/schedule/backup/database..."
    $backupResult = Invoke-ScheduleApiPost -BaseUrl $baseUrl -Endpoint '/api/schedule/backup/database' -BearerToken $bearerToken -CronToken $cronToken -TimeoutSeconds $timeouts.db_backup
    if ($backupResult.success) {
        Write-MonitorLog "Backup ejecutado exitosamente"
        Add-StateEvent -State $state -Type 'backup_run' -Success $true -BackupMetadata @{ file = $backupResult.parsed.file; size_mb = $backupResult.parsed.size_mb; duration_ms = $backupResult.parsed.duration_ms }
    } else {
        Write-MonitorLog "Backup FALLIDO: $($backupResult.error)"
        Add-StateEvent -State $state -Type 'backup_run' -Success $false -Error $backupResult.error
    }
} else {
    Write-MonitorLog "Backup saludable"
}

# Step 5: Security warning
$securityThreshold = if ($config.securityWarningThreshold) { [int]$config.securityWarningThreshold } else { 10 }
if ($securityLogins -gt $securityThreshold) {
    Write-MonitorLog "Alerta de seguridad: $securityLogins intentos fallidos en 24h"
    Add-StateEvent -State $state -Type 'security_warning' -Success $false -Error "$securityLogins intentos fallidos en 24h"
}

# Step 6: Reportes programados
Send-ScheduledMonitorReports -State $state -Now $now -Reporting $reporting -LogDir $logDir -LogRetentionDays $logRetentionDays -BaseUrl $baseUrl -Timeouts $timeouts

# Persist state
Write-StateStore -Path $statePath -State $state

# Limpieza de logs antiguos (se ejecuta siempre, pero solo en el ultimo ciclo del dia se limpia algo)
Clear-OldMonitorLogs -LogDir $logDir -RetentionDays $logRetentionDays

Write-MonitorLog "Ciclo de monitoreo completado"
exit $EXIT_OK
