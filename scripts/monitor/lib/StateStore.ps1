function Get-StateStorePath {
    param([string]$ConfigDir)
    return Join-Path -Path $ConfigDir -ChildPath 'monitor_state.json'
}

function Convert-PsObjectToHashtable {
    param([object]$InputObject)
    if ($null -eq $InputObject) { return $null }
    if ($InputObject -is [array]) {
        $result = @()
        foreach ($item in $InputObject) {
            $result += Convert-PsObjectToHashtable -InputObject $item
        }
        return ,$result
    }
    if ($InputObject -is [System.Management.Automation.PSCustomObject]) {
        $hash = @{}
        foreach ($prop in $InputObject.PSObject.Properties) {
            $hash[$prop.Name] = Convert-PsObjectToHashtable -InputObject $prop.Value
        }
        return $hash
    }
    return $InputObject
}

function Normalize-DailyLogs {
    param([object]$DailyLogs)
    if ($null -eq $DailyLogs) {
        return @()
    }
    if ($DailyLogs -is [array]) {
        $normalized = @()
        foreach ($item in $DailyLogs) {
            $normalized += Convert-PsObjectToHashtable -InputObject $item
        }
        return $normalized
    }
    if ($DailyLogs -is [System.Management.Automation.PSCustomObject] -or $DailyLogs -is [hashtable]) {
        return ,@(Convert-PsObjectToHashtable -InputObject $DailyLogs)
    }
    return @()
}

function Read-StateStore {
    param([string]$Path)
    $empty = @{
        dailyLogs = @()
        lastReportSent = @{ daily = $null; weekly = $null }
        lastSchedulerRun = $null
        lastImmediateAlert = $null
        consecutiveHealthFailures = 0
    }
    if (-not (Test-Path -Path $Path)) {
        return $empty
    }
    try {
        $content = Get-Content -Path $Path -Raw -Encoding UTF8
        $parsed = $content | ConvertFrom-Json
        $state = Convert-PsObjectToHashtable -InputObject $parsed
        $state.dailyLogs = Normalize-DailyLogs -DailyLogs $state.dailyLogs
        if (-not $state.lastReportSent) {
            $state.lastReportSent = @{ daily = $null; weekly = $null }
        }
        if ($state -is [hashtable]) {
            if (-not $state.ContainsKey('lastImmediateAlert')) { $state.lastImmediateAlert = $null }
            if (-not $state.ContainsKey('consecutiveHealthFailures')) { $state.consecutiveHealthFailures = 0 }
        }
        return $state
    } catch {
        return $empty
    }
}

function Write-StateStore {
    param(
        [string]$Path,
        [object]$State
    )
    $json = $State | ConvertTo-Json -Depth 10
    $dir = Split-Path -Path $Path -Parent
    if (-not (Test-Path -Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
    $json | Out-File -FilePath $Path -Encoding UTF8 -Force
}

function Add-StateEvent {
    param(
        [object]$State,
        [string]$Type,
        [bool]$Success,
        [int]$JobsProcessed = 0,
        [string]$Error = '',
        [hashtable]$BackupMetadata = $null
    )
    $State.dailyLogs = Normalize-DailyLogs -DailyLogs $State.dailyLogs

    $today = (Get-Date).ToString('yyyy-MM-dd')
    $time = (Get-Date).ToString('HH:mm')

    # Buscar o crear la entrada del dia
    $log = $null
    for ($i = 0; $i -lt $State.dailyLogs.Count; $i++) {
        if ($State.dailyLogs[$i].date -eq $today) {
            $log = $State.dailyLogs[$i]
            break
        }
    }
    if (-not $log) {
        $log = @{ date = $today; cycles = 0; events = @() }
        $State.dailyLogs += $log
    }
    $log.cycles += 1
    $evt = @{ time = $time; type = $Type; success = $Success }
    if ($JobsProcessed -gt 0) { $evt.jobs_processed = $JobsProcessed }
    if ($Error) { $evt.error = $Error }
    if ($BackupMetadata) { $evt.backup_metadata = $BackupMetadata }
    $log.events += $evt

    $sorted = $State.dailyLogs | Sort-Object -Property @{Expression = 'date'; Descending = $true}
    $State.dailyLogs = $sorted
    if ($State.dailyLogs.Count -gt 31) {
        $State.dailyLogs = $State.dailyLogs[0..30]
    }
}

function Test-ShouldSendDailyReport {
    param(
        [object]$State,
        [string]$ReportTime
    )
    $now = Get-Date
    $todayStr = $now.ToString('yyyy-MM-dd')
    $reportExpected = "$todayStr`T$ReportTime"

    $nowMinutes = $now.Hour * 60 + $now.Minute
    $parts = $ReportTime -split ':'
    $reportMinutes = [int]$parts[0] * 60 + [int]$parts[1]

    $diff = [Math]::Abs($nowMinutes - $reportMinutes)
    if ($diff -gt 5) { return $false }

    if ($State.lastReportSent.daily -eq $reportExpected) { return $false }

    $yesterday = $now.AddDays(-1).ToString('yyyy-MM-dd')
    $log = $State.dailyLogs | Where-Object { $_.date -eq $yesterday } | Select-Object -First 1
    if (-not $log) { return $false }
    $events = $log.events
    if (-not $events -or @($events).Count -eq 0) { return $false }

    return $true
}

function Test-ShouldSendWeeklyReport {
    param(
        [object]$State,
        [string]$ReportTime
    )
    $now = Get-Date
    if ([int]$now.DayOfWeek -ne 1) { return $false }

    $todayStr = $now.ToString('yyyy-MM-dd')
    $reportExpected = "$todayStr`T$ReportTime"

    $nowMinutes = $now.Hour * 60 + $now.Minute
    $parts = $ReportTime -split ':'
    $reportMinutes = [int]$parts[0] * 60 + [int]$parts[1]

    $diff = [Math]::Abs($nowMinutes - $reportMinutes)
    if ($diff -gt 5) { return $false }

    if ($State.lastReportSent.weekly -eq $reportExpected) { return $false }

    return $true
}
