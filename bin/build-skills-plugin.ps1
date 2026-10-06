param(
    [Parameter(Mandatory = $true)][string]$Version,
    [Parameter(Mandatory = $true)][string]$OutputPath,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
# Build from this checkout; never load an application's PHP bootstrap.
$sourceRoot = Split-Path -Parent $PSScriptRoot
$skillsRoot = Join-Path $sourceRoot 'skills'
$expected = @(
    'gframe-auth-access', 'gframe-backend', 'gframe-background-jobs',
    'gframe-campaigns', 'gframe-core-architecture', 'gframe-framework-maintenance',
    'gframe-frontend-admin', 'gframe-frontend-public', 'gframe-integrations',
    'gframe-media-module', 'gframe-notifications-mail', 'gframe-orchestrator',
    'gframe-orm-models', 'gframe-ui-design-clean'
)
if ($Version -notmatch '^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(-[0-9A-Za-z-]+(\.[0-9A-Za-z-]+)*)?(\+[0-9A-Za-z-]+(\.[0-9A-Za-z-]+)*)?$') {
    throw 'Indique una versión SemVer explícita.'
}
if ($Version.Contains('-')) {
    foreach ($part in ($Version.Split('+')[0] -split '-', 2)[1].Split('.')) {
        if ($part -match '^0[0-9]+$') { throw 'Un identificador numérico de prerelease no admite ceros iniciales.' }
    }
}
function Assert-PlainPath([string]$Path) {
    $cursor = [IO.Path]::GetFullPath($Path)
    while ($cursor) {
        if (Test-Path -LiteralPath $cursor) {
            if (((Get-Item -LiteralPath $cursor -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
                throw "No se admiten enlaces o junctions: $cursor"
            }
        }
        $parent = [IO.Path]::GetDirectoryName($cursor.TrimEnd('\', '/'))
        if (!$parent -or $parent -eq $cursor) { break }
        $cursor = $parent
    }
}
Assert-PlainPath $skillsRoot
$output = [IO.Path]::GetFullPath($OutputPath).TrimEnd('\', '/')
Assert-PlainPath $output
if (Test-Path -LiteralPath $output) { throw 'El destino ya existe; use una carpeta nueva. No se sobrescriben artefactos.' }
$outputParent = Split-Path -Parent $output
if (!(Test-Path -LiteralPath $outputParent -PathType Container)) { throw 'La carpeta padre del destino debe existir.' }
$sourcePrefix = [IO.Path]::GetFullPath($sourceRoot).TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar
if ($output.StartsWith($sourcePrefix, [StringComparison]::OrdinalIgnoreCase)) { throw 'El artefacto debe generarse fuera del repositorio fuente.' }
$actual = @(Get-ChildItem -LiteralPath $skillsRoot -Force | Where-Object { $_.Name -like 'gframe-*' } | ForEach-Object { $_.Name } | Sort-Object)
if (@(Compare-Object $expected $actual).Count) { throw 'El catálogo difiere de las catorce skills previstas; revise el empaquetador.' }
$files = [ordered]@{}
foreach ($skill in $expected) {
    $folder = Join-Path $skillsRoot $skill
    Assert-PlainPath $folder
    $main = Join-Path $folder 'SKILL.md'
    $metadata = Join-Path $folder 'agents/openai.yaml'
    Assert-PlainPath $main
    Assert-PlainPath $metadata
    if (!(Test-Path -LiteralPath $metadata -PathType Leaf)) { throw "Falta metadata: $skill" }
    $text = Get-Content -LiteralPath $main -Raw -Encoding UTF8
    if ($text -notmatch ('(?m)^name:\s*' + [regex]::Escape($skill) + '\s*$') -or $text -notmatch '(?m)^description:\s*\S') {
        throw "Identidad/descripción inválida: $skill"
    }
    $pending = New-Object 'Collections.Generic.Stack[string]'
    $pending.Push($folder)
    while ($pending.Count) {
        foreach ($item in (Get-ChildItem -LiteralPath $pending.Pop() -Force | Sort-Object Name)) {
            Assert-PlainPath $item.FullName
            if ($item.PSIsContainer) { $pending.Push($item.FullName); continue }
            $relative = $item.FullName.Substring($skillsRoot.Length + 1).Replace('\', '/')
            $files[$relative] = (Get-FileHash -LiteralPath $item.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        }
    }
}
$linkCount = 0
foreach ($relative in @($files.Keys | Where-Object { $_.EndsWith('.md') })) {
    $path = Join-Path $skillsRoot $relative
    foreach ($match in [regex]::Matches((Get-Content -LiteralPath $path -Raw -Encoding UTF8), '\]\(([^)]+)\)')) {
        $link = $match.Groups[1].Value
        if ($link -match '^(https?://|#|mailto:)') { continue }
        $target = [IO.Path]::GetFullPath((Join-Path (Split-Path -Parent $path) ($link.Split('#')[0])))
        $prefix = $skillsRoot.TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar
        if (!$target.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase) -or !(Test-Path -LiteralPath $target -PathType Leaf)) {
            throw "Referencia no portable: $relative -> $link"
        }
        Assert-PlainPath $target
        $linkCount++
    }
}
$reference = $null
if (Get-Command git -ErrorAction SilentlyContinue) {
    $head = & git -C $sourceRoot rev-parse HEAD 2>$null
    if ($LASTEXITCODE -eq 0) { $reference = [string]$head }
}
$composer = Get-Content -LiteralPath (Join-Path $sourceRoot 'composer.json') -Raw -Encoding UTF8 | ConvertFrom-Json
if ($composer.name -ne 'gorvet/gframe') { throw 'La fuente no es el framework GFrame.' }
$manifest = [ordered]@{
    '$schema' = 'https://agent-plugins.org/schemas/1.0.0/plugin.schema.json'
    name = 'gframe-skills'
    version = $Version
    description = 'Skills de GFrame para desarrollo, mantenimiento y coordinación de sus contratos técnicos.'
}
$claude = [ordered]@{ name = $manifest.name; version = $Version; description = $manifest.description }
$evidence = [ordered]@{
    schema_version = 1
    distribution_version = $Version
    source = [ordered]@{ package = $composer.name; path = $sourceRoot; version = $composer.version; head_reference = $reference; content_evidence = 'sha256-files-including-uncommitted-changes' }
    skills = $expected
    files = $files
}
if (!$DryRun) {
    # Reserve a new directory exclusively. A failed build is left for inspection, never published.
    $null = New-Item -ItemType Directory -Path $output
    foreach ($relative in $files.Keys) {
        $source = Join-Path $skillsRoot $relative
        if ((Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash.ToLowerInvariant() -ne $files[$relative]) { throw 'La fuente cambió durante el empaquetado.' }
        $destination = Join-Path (Join-Path $output 'skills') $relative
        Assert-PlainPath $destination
        $null = New-Item -ItemType Directory -Path (Split-Path -Parent $destination) -Force
        Copy-Item -LiteralPath $source -Destination $destination
        if ((Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash.ToLowerInvariant() -ne $files[$relative]) { throw 'La copia no coincide con la fuente.' }
    }
    $null = New-Item -ItemType Directory -Path (Join-Path $output '.claude-plugin')
    $utf8 = New-Object Text.UTF8Encoding($false)
    [IO.File]::WriteAllText((Join-Path $output 'plugin.json'), ($manifest | ConvertTo-Json -Depth 8) + "`n", $utf8)
    [IO.File]::WriteAllText((Join-Path $output '.claude-plugin/plugin.json'), ($claude | ConvertTo-Json -Depth 8) + "`n", $utf8)
    [IO.File]::WriteAllText((Join-Path $output 'gframe-skills-source.json'), ($evidence | ConvertTo-Json -Depth 8) + "`n", $utf8)
}
[pscustomobject]@{ name = $manifest.name; version = $Version; output = $output; dry_run = [bool]$DryRun; skills = $expected.Count; files = $files.Count; local_links = $linkCount } | ConvertTo-Json
