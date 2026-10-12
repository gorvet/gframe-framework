param(
    [ValidateSet('Codex', 'Claude', 'All')]
    [string]$Target = 'All',
    [string]$ProjectPath,
    [switch]$DryRun,
    [switch]$Json
)

$ErrorActionPreference = 'Stop'
if ($ProjectPath) {
    & (Join-Path $PSScriptRoot 'install-project-skills.ps1') -ProjectPath $ProjectPath -Target $Target -DryRun:$DryRun -Json:$Json
    return
}
throw 'La instalación global se retiró. Indica -ProjectPath o utiliza composer skills:install en el framework / composer gframe:skills en una aplicación.'
