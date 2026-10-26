function Get-SecretsPath {
    $scriptDir = Split-Path -Path $PSScriptRoot -Parent
    return Join-Path -Path $scriptDir -ChildPath 'secrets\monitor.secrets.json'
}

function Import-MonitorSecrets {
    $path = Get-SecretsPath
    if (-not (Test-Path -Path $path)) {
        return @{ bearerToken = $null; cronToken = $null }
    }
    try {
        $content = Get-Content -Path $path -Raw -Encoding UTF8
        return ($content | ConvertFrom-Json)
    } catch {
        return @{ bearerToken = $null; cronToken = $null }
    }
}

function Invoke-HealthApi {
    param(
        [string]$BaseUrl,
        [int]$TimeoutSeconds = 30,
        [bool]$Compact = $true
    )
    $url = "$BaseUrl/api/health"
    if ($Compact) { $url = $url + '?compact=1' }
    try {
        $response = Invoke-WebRequest -Uri $url -Method GET -TimeoutSec $TimeoutSeconds -UseBasicParsing
        $statusCode = [int]$response.StatusCode
        $raw = $response.Content
        $parsed = $raw | ConvertFrom-Json
        return @{
            success      = $statusCode -eq 200
            statusCode   = $statusCode
            raw          = $raw
            health       = $parsed
            error        = $null
        }
    } catch {
        $errMsg = $_.Exception.Message
        if ($_.Exception.Response) {
            try {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $errBody = $reader.ReadToEnd()
                $reader.Close()
                $errMsg = "$errMsg | Body: $errBody"
            } catch {}
        }
        return @{
            success    = $false
            statusCode = 0
            raw        = $null
            health     = $null
            error      = $errMsg
        }
    }
}

function Invoke-ScheduleApiPost {
    param(
        [string]$BaseUrl,
        [string]$Endpoint,
        [string]$BearerToken,
        [string]$CronToken,
        [int]$TimeoutSeconds = 60,
        [hashtable]$Body = @{}
    )
    $url = "$BaseUrl$Endpoint"
    $headers = @{
        'X-Cron-Token' = $CronToken
        'Authorization' = "Bearer $BearerToken"
        'Content-Type' = 'application/json'
    }
    $jsonBody = ($Body | ConvertTo-Json)
    try {
        $response = Invoke-WebRequest -Uri $url -Method POST -Headers $headers -Body $jsonBody -TimeoutSec $TimeoutSeconds -UseBasicParsing
        $statusCode = [int]$response.StatusCode
        $raw = $response.Content
        $parsed = $raw | ConvertFrom-Json
        return @{
            success    = $statusCode -eq 200 -and $parsed.success -eq $true
            statusCode = $statusCode
            raw        = $raw
            parsed     = $parsed
            error      = $null
        }
    } catch {
        $errMsg = $_.Exception.Message
        if ($_.Exception.Response) {
            try {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $errBody = $reader.ReadToEnd()
                $reader.Close()
                $errMsg = "$errMsg | Body: $errBody"
            } catch {}
        }
        return @{
            success    = $false
            statusCode = 0
            raw        = $null
            parsed     = $null
            error      = $errMsg
        }
    }
}
