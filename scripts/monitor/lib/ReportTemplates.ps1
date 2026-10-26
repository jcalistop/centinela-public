function Get-CssStyles {
    return @'
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;color:#333}
.container{max-width:650px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.header{background:#2c3e50;color:#fff;padding:20px;text-align:center}
.header h1{margin:0;font-size:20px}
.header .sub{font-size:13px;opacity:.8;margin-top:4px}
.body{padding:20px}
.section{margin-bottom:20px}
.section h2{font-size:16px;color:#2c3e50;border-bottom:2px solid #3498db;padding-bottom:6px;margin:0 0 12px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:#3498db;color:#fff;padding:8px 10px;text-align:left;font-weight:600}
td{padding:8px 10px;border-bottom:1px solid #eee}
tr:nth-child(even){background:#f9f9f9}
.ok{color:#27ae60;font-weight:600}
.warning{color:#e67e22;font-weight:600}
.down{color:#e74c3c;font-weight:600}
.unknown{color:#95a5a6}
.footer{background:#f8f8f8;padding:12px 20px;font-size:11px;color:#999;text-align:center;border-top:1px solid #eee}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;color:#fff}
.badge-ok{background:#27ae60}
.badge-fail{background:#e74c3c}
.badge-partial{background:#e67e22}
.summary-grid{display:flex;gap:10px;margin:10px 0}
.summary-card{flex:1;text-align:center;padding:12px;border-radius:6px;color:#fff}
.sc-cycles{background:#3498db}
.sc-ok{background:#27ae60}
.sc-warn{background:#e67e22}
.sc-err{background:#e74c3c}
.summary-card .num{font-size:24px;font-weight:700}
.summary-card .label{font-size:11px;opacity:.8;margin-top:2px}
'@
}

function Get-IntraHealthBanner {
    param(
        [object]$IntraHealth,
        [string]$IntraHealthError,
        [int]$EdgeFailCount
    )
    if ($IntraHealthError) {
        return @{ css = 'down'; label = 'INTRA no respondio (ver seccion Kernel)'; honestFail = $true }
    }
    if (-not $IntraHealth) {
        return @{ css = 'warning'; label = 'Sin snapshot INTRA'; honestFail = $true }
    }
    $sched = $IntraHealth.scheduler.status
    $backup = $IntraHealth.backup.status
    $worker = $IntraHealth.worker.status
    $bad = @('down', 'warning', 'missing')
    $kernelBad = ($bad -contains $sched) -or ($bad -contains $backup) -or ($worker -eq 'down')
    if ($EdgeFailCount -gt 0 -or $kernelBad) {
        return @{ css = 'down'; label = 'Requiere atencion'; honestFail = $true }
    }
    return @{ css = 'ok'; label = 'Saludable'; honestFail = $false }
}

function New-IntraHealthSectionHtml {
    param(
        [object]$IntraHealth,
        [string]$IntraHealthError
    )
    if ($IntraHealthError) {
        return "<p><span class=`"down`">Error al consultar GET /api/health (completo):</span> $IntraHealthError</p>"
    }
    if (-not $IntraHealth) {
        return '<p><span class="warning">Sin datos INTRA</span></p>'
    }
    $sched = $IntraHealth.scheduler
    $backup = $IntraHealth.backup
    $worker = $IntraHealth.worker
    $schedCss = if ($sched.status -eq 'ok') { 'ok' } elseif ($sched.status -eq 'warning') { 'warning' } else { 'down' }
    $bakCss = if ($backup.status -eq 'ok') { 'ok' } elseif ($backup.status -eq 'warning') { 'warning' } else { 'down' }
    $wrkCss = if ($worker.status -eq 'ok') { 'ok' } else { 'down' }
    return @"
<table>
<tr><th>Senal</th><th>Estado</th><th>Detalle</th></tr>
<tr><td>Scheduler (heartbeat)</td><td><span class="$schedCss">$($sched.status)</span></td><td>last_run=$($sched.last_run); minutes_ago=$($sched.minutes_ago); threshold=$($sched.threshold_minutes)</td></tr>
<tr><td>Backup</td><td><span class="$bakCss">$($backup.status)</span></td><td>$($backup.last_file) / $($backup.last_success)</td></tr>
<tr><td>Worker</td><td><span class="$wrkCss">$($worker.status)</span></td><td>pending=$($worker.pending_jobs); failed_24h=$($worker.failed_jobs_24h)</td></tr>
</table>
<p style="font-size:12px;color:#666">Fuente: GET /api/health (compact=0) al generar el digest. Lote Kernel nocturno: 00:00 (SDD-196). Correo diario INTRA descontinuado (SDD-243).</p>
"@
}

function New-DailyReportHtml {
    param(
        [string]$Date,
        [object[]]$Events,
        [int]$TotalCycles,
        [object]$IntraHealth = $null,
        [string]$IntraHealthError = ''
    )
    $css = Get-CssStyles

    # Contar eventos por tipo
    $healthFails = @($Events | Where-Object { $_.type -eq 'health_fail' })
    $schedulerRuns = @($Events | Where-Object { $_.type -eq 'scheduler_run' })
    $queueDrains = @($Events | Where-Object { $_.type -eq 'queue_drain' })
    $backupRuns = @($Events | Where-Object { $_.type -eq 'backup_run' })

    $schedulerOk = @($schedulerRuns | Where-Object { $_.success })
    $schedulerFail = @($schedulerRuns | Where-Object { -not $_.success })
    $queueOk = @($queueDrains | Where-Object { $_.success })
    $queueFail = @($queueDrains | Where-Object { -not $_.success })
    $backupOk = @($backupRuns | Where-Object { $_.success })
    $backupFail = @($backupRuns | Where-Object { -not $_.success })

    $edgeFails = $healthFails.Count + $schedulerFail.Count + $queueFail.Count + $backupFail.Count
    $banner = Get-IntraHealthBanner -IntraHealth $IntraHealth -IntraHealthError $IntraHealthError -EdgeFailCount $edgeFails
    $intraHtml = New-IntraHealthSectionHtml -IntraHealth $IntraHealth -IntraHealthError $IntraHealthError

    $totalJobs = ($queueDrains | Where-Object { $_.jobs_processed -gt 0 } | ForEach-Object { $_.jobs_processed }) | Measure-Object -Sum | Select-Object -ExpandProperty Sum

    $eventsHtml = ''
    if ($Events.Count -gt 0) {
        $rows = @()
        foreach ($evt in $Events) {
            $icon = switch ($evt.type) {
                'health_fail' { '<span class="down">&#9888;</span>' }
                'scheduler_run' { if ($evt.success) { '<span class="ok">&#10003;</span>' } else { '<span class="down">&#10007;</span>' } }
                'queue_drain' { if ($evt.success) { '<span class="ok">&#10003;</span>' } else { '<span class="down">&#10007;</span>' } }
                'backup_run' { if ($evt.success) { '<span class="ok">&#10003;</span>' } else { '<span class="down">&#10007;</span>' } }
                default { '' }
            }
            $label = switch ($evt.type) {
                'health_fail' { 'Health check fallido' }
                'scheduler_run' { 'Ejecucion schedule' }
                'queue_drain' { "Drenaje cola ($($evt.jobs_processed) jobs)" }
                'backup_run' {
                    if ($evt.backup_metadata) {
                        "Respaldo BD ($($evt.backup_metadata.file), $($evt.backup_metadata.size_mb)MB)"
                    } else {
                        'Respaldo BD'
                    }
                }
                default { $evt.type }
            }
            $status = if ($evt.success -eq $true) { '<span class="ok">Exitoso</span>' } elseif ($evt.success -eq $false) { '<span class="down">Fallido</span>' } else { '<span class="warning">Error</span>' }
            $detail = if ($evt.error) { $evt.error } else { '' }
            $rows += "<tr><td>$($evt.time)</td><td>$icon $label</td><td>$status</td><td>$detail</td></tr>"
        }
        $eventsHtml = $rows -join "`n"
    } else {
        $eventsHtml = '<tr><td colspan="4" style="text-align:center;color:#999;padding:15px">Sin eventos registrados</td></tr>'
    }

    $valleyNote = ''
    if ($healthFails.Count -eq 0 -and $schedulerFail.Count -eq 0) {
        $valleyNote = '<p>Borde: sin fallos de health/schedule en el dia agregado. Valle 00:00-00:45 se valida en log del monitor (omitir cola/backup).</p>'
    }

    return @"
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>$css</style></head><body>
<div class="container">
<div class="header"><h1>Informe Diario de Monitoreo</h1><div class="sub">$Date</div></div>
<div class="body">
<div class="section">
<h2>Estado general</h2>
<p>Estado: <span class="$($banner.css)">$($banner.label)</span></p>
$valleyNote
</div>
<div class="section">
<h2>Resumen del dia (borde / PC monitor)</h2>
<div class="summary-grid">
<div class="summary-card sc-cycles"><div class="num">$TotalCycles</div><div class="label">Ciclos ejecutados</div></div>
<div class="summary-card sc-ok"><div class="num">$($schedulerOk.Count + $queueOk.Count + $backupOk.Count)</div><div class="label">Acciones exitosas</div></div>
<div class="summary-card sc-err"><div class="num">$edgeFails</div><div class="label">Fallos</div></div>
</div>
</div>
<div class="section">
<h2>INTRA / Kernel</h2>
$intraHtml
</div>
<div class="section">
<h2>Detalle por subsistema (borde)</h2>
<table>
<tr><th>Subsistema</th><th>Total</th><th>Exitosos</th><th>Fallidos</th></tr>
<tr><td>Health check</td><td>$($healthFails.Count)</td><td>-</td><td><span class="down">$($healthFails.Count)</span></td></tr>
<tr><td>Schedule run</td><td>$($schedulerRuns.Count)</td><td><span class="ok">$($schedulerOk.Count)</span></td><td><span class="down">$($schedulerFail.Count)</span></td></tr>
<tr><td>Drenaje cola</td><td>$($queueDrains.Count)</td><td><span class="ok">$($queueOk.Count) ($totalJobs jobs)</span></td><td><span class="down">$($queueFail.Count)</span></td></tr>
<tr><td>Respaldo BD</td><td>$($backupRuns.Count)</td><td><span class="ok">$($backupOk.Count)</span></td><td><span class="down">$($backupFail.Count)</span></td></tr>
</table>
</div>
<div class="section">
<h2>Eventos del dia</h2>
<table><tr><th>Hora</th><th>Evento</th><th>Estado</th><th>Detalle</th></tr>
$eventsHtml
</table>
</div>
</div>
<div class="footer">Monitor Centinela - PC externo - Unico digest diario ops (SDD-243)</div>
</div></body></html>
"@
}

function New-WeeklyReportHtml {
    param(
        [string]$WeekStart,
        [string]$WeekEnd,
        [object[]]$DailyLogs,
        [int]$TotalCycles,
        [object]$IntraHealth = $null,
        [string]$IntraHealthError = ''
    )
    $css = Get-CssStyles
    $daysHtml = ''
    $grandTotalEvents = 0
    $grandFailures = 0
    $daysWithFails = 0
    $healthFailDays = 0

    foreach ($log in $DailyLogs) {
        $failCount = @($log.events | Where-Object { $_.success -eq $false -or $_.type -eq 'health_fail' }).Count
        $hf = @($log.events | Where-Object { $_.type -eq 'health_fail' }).Count
        $grandTotalEvents += $log.events.Count
        $grandFailures += $failCount
        if ($failCount -gt 0) { $daysWithFails++ }
        if ($hf -gt 0) { $healthFailDays++ }
        $daysHtml += "<tr><td>$($log.date)</td><td>$($log.cycles)</td><td>$($log.events.Count)</td><td><span class=""$(if ($failCount -gt 0) {'down'} else {'ok'})"">$failCount</span></td></tr>"
    }

    $pctHealth = if ($TotalCycles -gt 0) { [Math]::Round(100 - ($grandFailures / $TotalCycles) * 100) } else { 100 }
    $banner = Get-IntraHealthBanner -IntraHealth $IntraHealth -IntraHealthError $IntraHealthError -EdgeFailCount $grandFailures
    $healthLabel = $banner.css
    $healthText = $banner.label
    $intraHtml = New-IntraHealthSectionHtml -IntraHealth $IntraHealth -IntraHealthError $IntraHealthError

    return @"
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>$css</style></head><body>
<div class="container">
<div class="header"><h1>Informe Semanal de Monitoreo</h1><div class="sub">$WeekStart - $WeekEnd</div></div>
<div class="body">
<div class="section">
<h2>Resumen semanal</h2>
<div class="summary-grid">
<div class="summary-card sc-cycles"><div class="num">$TotalCycles</div><div class="label">Ciclos totales</div></div>
<div class="summary-card sc-ok"><div class="num">$pctHealth%</div><div class="label">Disponibilidad (borde)</div></div>
<div class="summary-card sc-err"><div class="num">$grandFailures</div><div class="label">Fallos totales</div></div>
</div>
<p>Estado general: <span class="$healthLabel">$healthText</span></p>
<p>Dias con fallos: <strong>$daysWithFails</strong> / dias con health FAIL: <strong>$healthFailDays</strong>. Valle nocturno 00:00-00:45: revisar logs diarios del monitor (omitir cola/backup).</p>
</div>
<div class="section">
<h2>INTRA / Kernel (snapshot al enviar)</h2>
$intraHtml
</div>
<div class="section">
<h2>Desglose diario</h2>
<table><tr><th>Dia</th><th>Ciclos</th><th>Eventos</th><th>Fallos</th></tr>
$daysHtml
</table>
</div>
</div>
<div class="footer">Monitor Centinela - PC externo - Semanal reforzado (SDD-243)</div>
</div></body></html>
"@
}
