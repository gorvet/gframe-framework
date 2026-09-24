param(
    [ValidateSet('Codex', 'Claude', 'All')]
    [string]$Target = 'All'
)

$ErrorActionPreference = 'Stop'
$frameworkRoot = Split-Path -Parent $PSScriptRoot
$sourceRoot = Join-Path $frameworkRoot 'skills'

if (-not (Test-Path -LiteralPath $sourceRoot -PathType Container)) {
    throw 'No se encontró el directorio de skills de GFrame.'
}

$destinations = @()
if ($Target -in @('Codex', 'All')) {
    $codexRoot = if ($env:CODEX_HOME) { $env:CODEX_HOME } else { Join-Path $HOME '.codex' }
    $destinations += @{ Name = 'Codex'; Path = Join-Path $codexRoot 'skills' }
}
if ($Target -in @('Claude', 'All')) {
    $destinations += @{ Name = 'Claude Code'; Path = Join-Path (Join-Path $HOME '.claude') 'skills' }
}

$skillDirectories = Get-ChildItem -LiteralPath $sourceRoot -Directory -Filter 'gframe-*'
foreach ($destination in $destinations) {
    New-Item -ItemType Directory -Path $destination.Path -Force | Out-Null
    foreach ($skill in $skillDirectories) {
        $targetPath = Join-Path $destination.Path $skill.Name
        New-Item -ItemType Directory -Path $targetPath -Force | Out-Null
        Copy-Item -Path (Join-Path $skill.FullName '*') -Destination $targetPath -Recurse -Force
    }
    Write-Output ("{0}: {1} skills actualizados en {2}" -f $destination.Name, $skillDirectories.Count, $destination.Path)
}
