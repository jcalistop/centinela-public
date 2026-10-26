$script:MonitorMailConfig = $null

function Get-SecretsDir {
    $scriptDir = Split-Path -Path $PSScriptRoot -Parent
    return Join-Path -Path $scriptDir -ChildPath 'secrets'
}

function Get-DefaultMonitorSmtpConfig {
    return @{
        SmtpServer   = 'smtp.ejemplo.gob.cl'
        Port         = 587
        UseSsl       = $true
        FromAddress  = 'centinela-monitor@ejemplo.gob.cl'
        FromName     = 'Monitor Centinela (PC externo)'
        ToAddresses  = @('destinatario@ejemplo.gob.cl')
        PasswordFile = 'smtpPassword.sec'
    }
}

function Initialize-MonitorMailer {
    param([object]$MonitorConfig)
    $script:MonitorMailConfig = $MonitorConfig
}

function Read-SmtpPassword {
    $passwordFile = 'smtpPassword.sec'
    if ($script:MonitorMailConfig -and $script:MonitorMailConfig.smtp -and $script:MonitorMailConfig.smtp.passwordFile) {
        $passwordFile = [string]$script:MonitorMailConfig.smtp.passwordFile
    }
    $secPath = Join-Path -Path (Get-SecretsDir) -ChildPath $passwordFile
    if (-not (Test-Path -Path $secPath)) {
        return $null
    }
    try {
        $secure = Get-Content -Path $secPath | ConvertTo-SecureString
        $bstr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
        return [System.Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
    } catch {
        return $null
    }
}

function Get-MonitorSmtpConfig {
    $defaults = Get-DefaultMonitorSmtpConfig
    if ($null -eq $script:MonitorMailConfig -or $null -eq $script:MonitorMailConfig.smtp) {
        return $defaults
    }

    $smtp = $script:MonitorMailConfig.smtp
    $toAddresses = $defaults.ToAddresses
    if ($smtp.to) {
        if ($smtp.to -is [array]) {
            $toAddresses = @($smtp.to | ForEach-Object { [string]$_ })
        } else {
            $toAddresses = @([string]$smtp.to)
        }
    }

    return @{
        SmtpServer   = if ($smtp.server) { [string]$smtp.server } else { $defaults.SmtpServer }
        Port         = if ($null -ne $smtp.port) { [int]$smtp.port } else { $defaults.Port }
        UseSsl       = if ($null -ne $smtp.useSsl) { [bool]$smtp.useSsl } else { $defaults.UseSsl }
        FromAddress  = if ($smtp.fromAddress) { [string]$smtp.fromAddress } else { $defaults.FromAddress }
        FromName     = if ($smtp.fromName) { [string]$smtp.fromName } else { $defaults.FromName }
        ToAddresses  = $toAddresses
        PasswordFile = if ($smtp.passwordFile) { [string]$smtp.passwordFile } else { $defaults.PasswordFile }
    }
}

function Send-MonitorEmail {
    param(
        [string]$Subject,
        [string]$BodyHtml,
        [string[]]$To = $null,
        [scriptblock]$OnLog = $null
    )
    $smtp = Get-MonitorSmtpConfig
    $password = Read-SmtpPassword
    if (-not $password) {
        $msg = 'Correo no enviado: no se pudo leer smtpPassword.sec (archivo ausente o corrupto).'
        if ($OnLog) { & $OnLog $msg } else { Write-Warning $msg }
        return $false
    }
    $recipients = if ($To) { $To } else { $smtp.ToAddresses }
    try {
        $secPass = ConvertTo-SecureString -String $password -AsPlainText -Force
        $cred = New-Object System.Management.Automation.PSCredential($smtp.FromAddress, $secPass)
        $mailParams = @{
            SmtpServer = $smtp.SmtpServer
            Port       = $smtp.Port
            UseSsl     = $smtp.UseSsl
            From       = "$($smtp.FromName) <$($smtp.FromAddress)>"
            To         = $recipients
            Subject    = $Subject
            Body       = $BodyHtml
            BodyAsHtml = $true
            Credential = $cred
            Encoding   = [System.Text.UTF8Encoding]::new($false)
        }
        Send-MailMessage @mailParams -ErrorAction Stop
        return $true
    } catch {
        $msg = "Correo no enviado: error SMTP: $_"
        if ($OnLog) { & $OnLog $msg } else { Write-Warning $msg }
        return $false
    }
}

function Get-ImmediateAlertSubject {
    return '[ALERTA] Centinela - App caida - Se requiere atencion inmediata'
}

function Get-ImmediateAlertBody {
    param(
        [string]$ErrorDetail,
        [string]$Timestamp,
        [int]$ConsecutiveFailures = 0,
        [int]$Threshold = 3
    )
    $consecutiveLine = ''
    if ($ConsecutiveFailures -gt 0 -and $Threshold -gt 0) {
        $consecutiveLine = "<p>Fallos consecutivos de health check: <strong>$ConsecutiveFailures</strong> (umbral: $Threshold).</p>"
    }
    return @"
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;color:#333}
.container{max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.header{background:#c0392b;color:#fff;padding:20px;text-align:center}
.header h1{margin:0;font-size:20px}
.body{padding:20px}
.box{border:1px solid #e74c3c;border-radius:6px;padding:15px;background:#fef9f2;margin:15px 0}
.box h3{margin:0 0 8px;color:#c0392b}
.detail{color:#666;font-size:13px;margin-top:15px}
.footer{background:#f8f8f8;padding:12px 20px;font-size:11px;color:#999;text-align:center;border-top:1px solid #eee}
</style></head><body>
<div class="container">
<div class="header"><h1>Centinela - Alerta Inmediata</h1></div>
<div class="body">
<p>El monitor externo ha detectado que la aplicacion <strong>no responde</strong> o esta en un estado anormal.</p>
$consecutiveLine
<div class="box">
<h3>Detalle del problema</h3>
<p>$ErrorDetail</p>
</div>
<div class="detail">
<p>Origen: Monitor externo (PC de monitoreo)</p>
<p>Deteccion: $Timestamp</p>
</div>
</div>
<div class="footer">Monitor Centinela - Sistema de monitoreo externo</div>
</div></body></html>
"@
}

function Get-DailyReportSubject {
    param([string]$Date)
    return "[Informe Diario] Centinela - Resumen de monitoreo - $Date"
}

function Get-WeeklyReportSubject {
    param([string]$WeekStart)
    return "[Informe Semanal] Centinela - Tendencias de monitoreo - Semana del $WeekStart"
}
