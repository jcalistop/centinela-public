<#
.SYNOPSIS
    Exporta un árbol limpio para mirror público (SDD-245 / SDD-310).
.DESCRIPTION
    Copia archivos trackeados de HEAD, aplica exclusiones PUBLIC_MIRROR_EXCLUDE
    (instancia SDD, sdd-kit, .gitmodules, rules sdd-*.mdc, sdd-validate.yml),
    reemplazos de placeholders y escanea con gitleaks.
    No hace push ni crea el remoto (SDD-310 Fase B: gh repo create tras el tag).
.PARAMETER OutputDir
    Directorio de salida (se recrea). Default: .public-mirror-export en la raíz del repo.
.PARAMETER SkipGitleaks
    No ejecutar gitleaks aunque exista el binario.
.PARAMETER GitleaksPath
    Ruta al ejecutable gitleaks. Default: .gitleaks-bin/gitleaks.exe
#>
[CmdletBinding()]
param(
    [string] $OutputDir = '',
    [switch] $SkipGitleaks,
    [string] $GitleaksPath = ''
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $repoRoot

function Test-PublicMirrorExcluded {
    param([Parameter(Mandatory)][string] $RelativePath)
    $n = $RelativePath -replace '\\', '/'
    if ($n -match '(?i)(^|/)\.github/docs/sdd(/|$)') { return $true }
    if ($n -match '(?i)(^|/)\.github/docs/sdd-kit(/|$)') { return $true }
    if ($n -match '(?i)^\.gitmodules$') { return $true }
    if ($n -match '(?i)^\.cursor/rules/sdd-.*\.mdc$') { return $true }
    if ($n -match '(?i)^\.github/workflows/sdd-validate\.yml$') { return $true }
    return $false
}

if (-not $OutputDir) {
    $OutputDir = Join-Path $repoRoot '.public-mirror-export'
}
if (-not $GitleaksPath) {
    $GitleaksPath = Join-Path $repoRoot '.gitleaks-bin\gitleaks.exe'
}

Write-Host "Repo: $repoRoot"
Write-Host "Output: $OutputDir"

if (Test-Path $OutputDir) {
    Remove-Item -Recurse -Force $OutputDir
}
New-Item -ItemType Directory -Path $OutputDir | Out-Null

$files = git -C $repoRoot ls-files
$count = 0
foreach ($rel in $files) {
    if (Test-PublicMirrorExcluded -RelativePath $rel) {
        continue
    }
    $src = Join-Path $repoRoot $rel
    if (-not (Test-Path -LiteralPath $src)) {
        continue
    }
    $dest = Join-Path $OutputDir $rel
    $destDir = Split-Path -Parent $dest
    if (-not (Test-Path $destDir)) {
        New-Item -ItemType Directory -Force -Path $destDir | Out-Null
    }
    Copy-Item -LiteralPath $src -Destination $dest -Force
    $count++
}
Write-Host "Copied $count tracked files (exclusions applied)."

$replacements = @{
    'https://ejemplo.intranet.local/centinela' = 'https://ejemplo.intranet.local/centinela'
    'smtp.ejemplo.gob.cl'                    = 'smtp.ejemplo.gob.cl'
    'centinela-ops@ejemplo.gob.cl'                = 'centinela-ops@ejemplo.gob.cl'
    'centinela-monitor@ejemplo.gob.cl'       = 'centinela-monitor@ejemplo.gob.cl'
}

$extOk = @('.md', '.json', '.php', '.ps1', '.yml', '.yaml', '.env', '.xml', '.txt', '.blade.php', '.example')
Get-ChildItem -Path $OutputDir -Recurse -File | ForEach-Object {
    $name = $_.Name
    $full = $_.FullName
    $doReplace = $false
    foreach ($ext in $extOk) {
        if ($name.EndsWith($ext) -or $full -match '\.blade\.php$') {
            $doReplace = $true
            break
        }
    }
    if (-not $doReplace) {
        return
    }
    try {
        $raw = [System.IO.File]::ReadAllText($full)
    } catch {
        return
    }
    $new = $raw
    foreach ($k in $replacements.Keys) {
        $new = $new.Replace($k, $replacements[$k])
    }
    if ($new -ne $raw) {
        [System.IO.File]::WriteAllText($full, $new)
    }
}
Write-Host 'Placeholder replacements applied.'

$assertMissing = @(
    @{ Path = (Join-Path $OutputDir '.github\docs\sdd'); Label = '.github/docs/sdd' }
    @{ Path = (Join-Path $OutputDir '.github\docs\sdd-kit'); Label = '.github/docs/sdd-kit' }
    @{ Path = (Join-Path $OutputDir '.gitmodules'); Label = '.gitmodules' }
    @{ Path = (Join-Path $OutputDir '.github\workflows\sdd-validate.yml'); Label = '.github/workflows/sdd-validate.yml' }
)
foreach ($item in $assertMissing) {
    if (Test-Path -LiteralPath $item.Path) {
        throw "Exclude fail: $($item.Label) still present at $($item.Path)"
    }
}

$rulesDir = Join-Path $OutputDir '.cursor\rules'
if (Test-Path -LiteralPath $rulesDir) {
    $sddRules = @(Get-ChildItem -LiteralPath $rulesDir -Filter 'sdd-*.mdc' -File -ErrorAction SilentlyContinue)
    if ($sddRules.Count -gt 0) {
        $names = ($sddRules | ForEach-Object { $_.Name }) -join ', '
        throw "Exclude fail: .cursor/rules/sdd-*.mdc still present: $names"
    }
}

$configToml = Join-Path $repoRoot '.gitleaks.toml'
if (-not $SkipGitleaks -and (Test-Path $GitleaksPath) -and (Test-Path $configToml)) {
    Copy-Item $configToml (Join-Path $OutputDir '.gitleaks.toml') -Force
    Write-Host "Running gitleaks on $OutputDir ..."
    & $GitleaksPath detect --source $OutputDir --no-git --redact --config (Join-Path $OutputDir '.gitleaks.toml')
    if ($LASTEXITCODE -ne 0) {
        throw "gitleaks failed with exit $LASTEXITCODE - do not publish this export"
    }
    Write-Host 'gitleaks: OK (no leaks).'
} elseif (-not $SkipGitleaks) {
    Write-Warning 'gitleaks binary or config missing; skip scan. Install locally or run in CI on the public repo.'
}

$originSha = (git -C $repoRoot rev-parse HEAD).Trim()
$originTag = '(HEAD not an exact tag)'
$tagsAtHead = @(git -C $repoRoot tag --points-at HEAD)
if ($tagsAtHead.Count -gt 0) {
    $originTag = ($tagsAtHead -join ', ')
}
$exportedAt = Get-Date -Format 'yyyy-MM-ddTHH:mm:ssK'
$metaLines = @(
    '# Public mirror export metadata (SDD-310)',
    '',
    '* source_repo: private canonical',
    "* source_commit: $originSha",
    "* source_tag: $originTag",
    "* exported_at: $exportedAt",
    '* exclusions: .github/docs/sdd/**, .github/docs/sdd-kit/**, .gitmodules, .cursor/rules/sdd-*.mdc, .github/workflows/sdd-validate.yml',
    '* note: SDD-310 Fase B - git init + gh repo create jcalistop/centinela-public; do not push private history.'
)
Set-Content -Path (Join-Path $OutputDir 'MIRROR_EXPORT_META.md') -Value $metaLines -Encoding UTF8
Write-Host "Done. Next (Fase B, after mother tag v5.8.0): git init + gh repo create jcalistop/centinela-public --public in $OutputDir"
