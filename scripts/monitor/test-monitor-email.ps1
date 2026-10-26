<#
.SYNOPSIS
    Prueba envío SMTP del monitor Centinela (lee config/monitor.config.json).
.EXAMPLE
    powershell -NoProfile -File test-monitor-email.ps1
    powershell -NoProfile -File test-monitor-email.ps1 -ConfigPath config\monitor.config.json
#>
param(
    [string]$ConfigPath = ''
)

$ErrorActionPreference = 'Stop'
$scriptDir = Split-Path -Path $PSCommandPath -Parent
if (-not $ConfigPath) {
    $ConfigPath = Join-Path -Path $scriptDir -ChildPath 'config\monitor.config.json'
}
if (-not (Test-Path -Path $ConfigPath)) {
    Write-Host "FALLO: no existe $ConfigPath" -ForegroundColor Red
    exit 2
}

$config = Get-Content -Path $ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json
. (Join-Path $scriptDir 'lib\Mailer.ps1')
Initialize-MonitorMailer -MonitorConfig $config

$smtp = Get-MonitorSmtpConfig
Write-Host '=== Prueba SMTP Monitor Centinela ===' -ForegroundColor Cyan
Write-Host "Config: $ConfigPath"
Write-Host "Servidor: $($smtp.SmtpServer):$($smtp.Port) (SSL=$($smtp.UseSsl))"
Write-Host "Desde: $($smtp.FromName) <$($smtp.FromAddress)>"
Write-Host "Hacia: $($smtp.ToAddresses -join ', ')"
Write-Host "Password file: secrets\$($smtp.PasswordFile)"
Write-Host ''

$secPath = Join-Path $scriptDir "secrets\$($smtp.PasswordFile)"
if (-not (Test-Path $secPath)) {
    Write-Host "FALLO: no existe $secPath" -ForegroundColor Red
    exit 2
}
Write-Host "OK: $($smtp.PasswordFile) encontrado" -ForegroundColor Green

try {
    $null = Get-Content -Path $secPath | ConvertTo-SecureString
    Write-Host 'OK: contraseña SMTP desencriptada' -ForegroundColor Green
} catch {
    Write-Host "FALLO: no se pudo leer $($smtp.PasswordFile): $_" -ForegroundColor Red
    exit 2
}

$subject = "[TEST] Centinela Monitor - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
$body = @"
<!DOCTYPE html><html><body>
<p>Correo de prueba del monitor externo Centinela.</p>
<p>Si recibes esto, SMTP está configurado correctamente.</p>
</body></html>
"@

Write-Host 'Enviando correo...' -ForegroundColor Yellow
$sent = Send-MonitorEmail -Subject $subject -BodyHtml $body -OnLog {
    param($m)
    Write-Host $m -ForegroundColor Red
}

if ($sent) {
    Write-Host 'RESULTADO: correo enviado correctamente.' -ForegroundColor Green
    exit 0
}

Write-Host 'RESULTADO: no se pudo enviar el correo (ver mensaje arriba).' -ForegroundColor Red
exit 1
