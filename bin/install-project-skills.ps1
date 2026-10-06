param(
    [Parameter(Mandatory = $true)][string]$ProjectPath,
    [ValidateSet('Codex', 'Claude', 'All')][string]$Target = 'All',
    [switch]$DryRun,
    [switch]$Json
)

$ErrorActionPreference = 'Stop'

function Assert-PlainPath([string]$Path) {
    $absolute = [IO.Path]::GetFullPath($Path)
    $cursor = $absolute
    while ($cursor) {
        if (Test-Path -LiteralPath $cursor) {
            $item = Get-Item -LiteralPath $cursor -Force
            if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
                throw "No se admiten enlaces o junctions en esta operación: $cursor"
            }
        }
        $parent = [IO.Path]::GetDirectoryName($cursor.TrimEnd('\', '/'))
        if (!$parent -or $parent -eq $cursor) { break }
        $cursor = $parent
    }
}

function Resolve-SkillFile([string]$Root, [string]$Relative) {
    if ($Relative -notmatch '^gframe-[a-z0-9-]+/.+' -or $Relative.Contains('\') -or $Relative.Contains(':')) {
        throw "Ruta de skill inválida: $Relative"
    }
    foreach ($part in $Relative.Split('/')) {
        if (!$part -or $part -in @('.', '..') -or $part -match '[<>"|?*]' -or $part -ne $part.TrimEnd(' ', '.')) {
            throw "Ruta de skill inválida: $Relative"
        }
    }
    $base = [IO.Path]::GetFullPath($Root).TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar
    $resolved = [IO.Path]::GetFullPath((Join-Path $Root $Relative))
    if (!$resolved.StartsWith($base, [StringComparison]::OrdinalIgnoreCase)) { throw 'La ruta sale del destino de skills.' }
    Assert-PlainPath $resolved
    return $resolved
}

function File-Hash([string]$Path) {
    if (!(Test-Path -LiteralPath $Path)) { return $null }
    if (!(Test-Path -LiteralPath $Path -PathType Leaf)) { throw "Se esperaba un archivo: $Path" }
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

$projectRoot = [IO.Path]::GetFullPath($ProjectPath).TrimEnd('\', '/')
Assert-PlainPath $projectRoot
if (!(Test-Path -LiteralPath $projectRoot -PathType Container)) { throw 'El proyecto no existe.' }
$composerPath = Join-Path $projectRoot 'composer.json'
Assert-PlainPath $composerPath
$composer = Get-Content -LiteralPath $composerPath -Raw -Encoding UTF8 | ConvertFrom-Json
$packageVersion = $null
$packageReference = $null
if ($composer.name -eq 'gorvet/gframe') {
    $packageRoot = $projectRoot
    $packageVersion = $composer.version
} else {
    $vendor = 'vendor'
    if ($composer.config.'vendor-dir') { $vendor = [string]$composer.config.'vendor-dir' }
    $vendorRoot = if ([IO.Path]::IsPathRooted($vendor)) { [IO.Path]::GetFullPath($vendor) } else { [IO.Path]::GetFullPath((Join-Path $projectRoot $vendor)) }
    $installedPath = Join-Path $vendorRoot 'composer/installed.json'
    Assert-PlainPath $installedPath
    if (!(Test-Path -LiteralPath $installedPath -PathType Leaf)) { throw 'No se encontró metadata Composer instalada. No se utilizará otra copia de GFrame.' }
    $installed = Get-Content -LiteralPath $installedPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $packages = if ($installed.PSObject.Properties.Name -contains 'packages') { @($installed.packages) } else { @($installed) }
    $matches = @($packages | Where-Object { $_.name -eq 'gorvet/gframe' })
    if ($matches.Count -ne 1) { throw 'La metadata debe identificar exactamente un paquete gorvet/gframe.' }
    $package = $matches[0]
    $packageRoot = if ($package.'install-path') {
        [IO.Path]::GetFullPath((Join-Path (Split-Path -Parent $installedPath) $package.'install-path'))
    } else { Join-Path $vendorRoot 'gorvet/gframe' }
    $packageVersion = $package.version
    $packageReference = if ($package.source.reference) { $package.source.reference } else { $package.dist.reference }
}
Assert-PlainPath $packageRoot
$packageComposerPath = Join-Path $packageRoot 'composer.json'
Assert-PlainPath $packageComposerPath
$packageComposer = Get-Content -LiteralPath $packageComposerPath -Raw -Encoding UTF8 | ConvertFrom-Json
if ($packageComposer.name -ne 'gorvet/gframe') { throw 'La ruta instalada no corresponde al paquete gorvet/gframe.' }
$sourceRoot = Join-Path $packageRoot 'skills'
Assert-PlainPath $sourceRoot
if (!(Test-Path -LiteralPath $sourceRoot -PathType Container)) { throw 'Esta versión del paquete no contiene skills.' }

$desired = @{}
$skillDirectories = @(Get-ChildItem -LiteralPath $sourceRoot -Directory -Force | Where-Object { $_.Name -match '^gframe-[a-z0-9-]+$' })
if ($skillDirectories.Count -eq 0) { throw 'No se encontraron skills canónicas en el paquete instalado.' }
foreach ($skill in $skillDirectories) {
    Assert-PlainPath $skill.FullName
    if (!(Test-Path -LiteralPath (Join-Path $skill.FullName 'SKILL.md') -PathType Leaf)) { throw "Skill incompleta: $($skill.Name)" }
    $pending = New-Object 'System.Collections.Generic.Stack[string]'
    $pending.Push($skill.FullName)
    while ($pending.Count -gt 0) {
        foreach ($item in Get-ChildItem -LiteralPath $pending.Pop() -Force) {
            Assert-PlainPath $item.FullName
            if ($item.PSIsContainer) { $pending.Push($item.FullName); continue }
            $relative = $item.FullName.Substring($sourceRoot.Length + 1).Replace('\', '/')
            $path = Resolve-SkillFile $sourceRoot $relative
            $desired[$relative] = @{ Path = $path; Hash = (File-Hash $path) }
        }
    }
}

$clients = if ($Target -eq 'All') { @('Codex', 'Claude') } else { @($Target) }
$reports = @()
foreach ($client in $clients) {
    $clientDirectory = if ($client -eq 'Codex') { '.agents' } else { '.claude' }
    $clientRoot = Join-Path $projectRoot $clientDirectory
    $destinationRoot = Join-Path $clientRoot 'skills'
    $statePath = Join-Path $clientRoot 'gframe-skills.json'
    Assert-PlainPath $statePath
    Assert-PlainPath $destinationRoot
    $sha = [Security.Cryptography.SHA256]::Create()
    try { $lockKey = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($destinationRoot.ToLowerInvariant()))).Replace('-', '') }
    finally { $sha.Dispose() }
    $mutex = New-Object System.Threading.Mutex($false, ('gframe-skills-' + $lockKey))
    $locked = $false
    try {
        try { $locked = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $locked = $true }
        if (!$locked) { throw 'Otra instalación de skills está utilizando este destino.' }
        $previous = @{}
        if (Test-Path -LiteralPath $statePath) {
            $state = Get-Content -LiteralPath $statePath -Raw -Encoding UTF8 | ConvertFrom-Json
            if ($state.schema -ne 1 -or $state.client -ne $client -or $state.files -isnot [pscustomobject]) { throw 'Registro de skills inválido.' }
            foreach ($entry in $state.files.PSObject.Properties) {
                $null = Resolve-SkillFile $destinationRoot $entry.Name
                if ($entry.Value -notmatch '^[a-f0-9]{64}$') { throw 'Hash inválido en el registro.' }
                $previous[$entry.Name] = [string]$entry.Value
            }
        }
        $nextFiles = @{}
        $actions = @()
        $unmanagedSkills = @{}
        foreach ($skill in $skillDirectories) {
            $registered = @($previous.Keys | Where-Object { $_.StartsWith($skill.Name + '/', [StringComparison]::OrdinalIgnoreCase) }).Count -gt 0
            $skillDestination = Join-Path $destinationRoot $skill.Name
            Assert-PlainPath $skillDestination
            if (!$registered -and (Test-Path -LiteralPath $skillDestination)) { $unmanagedSkills[$skill.Name] = $true }
        }
        foreach ($relative in @(@($desired.Keys) + @($previous.Keys) | Sort-Object -Unique)) {
            $destination = Resolve-SkillFile $destinationRoot $relative
            $currentHash = File-Hash $destination
            $owned = $previous.ContainsKey($relative)
            if ($desired.ContainsKey($relative)) {
                $incoming = $desired[$relative].Hash
                if ($unmanagedSkills.ContainsKey($relative.Split('/')[0])) { $action = 'conflict' }
                elseif ($null -eq $currentHash) { $action = 'create'; $nextFiles[$relative] = $incoming }
                elseif (!$owned) { $action = 'conflict' }
                elseif ($currentHash -eq $incoming) { $action = 'unchanged'; $nextFiles[$relative] = $incoming }
                elseif ($currentHash -eq $previous[$relative]) { $action = 'update'; $nextFiles[$relative] = $incoming }
                else { $action = 'conflict'; $nextFiles[$relative] = $previous[$relative] }
            } elseif ($null -eq $currentHash) { $action = 'forget' }
            elseif ($currentHash -eq $previous[$relative]) { $action = 'remove' }
            else { $action = 'conflict'; $nextFiles[$relative] = $previous[$relative] }
            $actions += [pscustomobject]@{ path = $relative; action = $action; current_hash = $currentHash }
        }
        $source = [ordered]@{ package = 'gorvet/gframe'; path = $packageRoot; version = $packageVersion; reference = $packageReference; evidence = 'composer-installed-metadata' }
        if ($composer.name -eq 'gorvet/gframe') { $source.evidence = 'standalone-package' }
        $nextState = [ordered]@{ schema = 1; client = $client; source = $source; files = [ordered]@{} }
        foreach ($key in @($nextFiles.Keys | Sort-Object)) { $nextState.files[$key] = $nextFiles[$key] }
        $stateJson = $nextState | ConvertTo-Json -Depth 8
        $stateChanged = !(Test-Path -LiteralPath $statePath) -or (Get-Content -LiteralPath $statePath -Raw -Encoding UTF8).Trim() -ne $stateJson.Trim()
        if (!$DryRun) {
            foreach ($item in $actions) {
                if ($item.action -notin @('create', 'update', 'remove')) { continue }
                $destination = Resolve-SkillFile $destinationRoot $item.path
                if ((File-Hash $destination) -ne $item.current_hash) { throw 'Un archivo cambió después de la vista previa interna. Repite la operación.' }
                if ($item.action -eq 'remove') {
                    # Solo un archivo registrado e intacto, con su ruta comprobada dentro del destino.
                    Remove-Item -LiteralPath $destination
                } else {
                    $sourceFile = Resolve-SkillFile $sourceRoot $item.path
                    if ((File-Hash $sourceFile) -ne $desired[$item.path].Hash) { throw 'La fuente cambió durante la operación.' }
                    $null = New-Item -ItemType Directory -Path (Split-Path -Parent $destination) -Force
                    Assert-PlainPath $destination
                    Copy-Item -LiteralPath $sourceFile -Destination $destination -Force
                }
            }
            if ($stateChanged) {
                Assert-PlainPath $statePath
                $null = New-Item -ItemType Directory -Path $clientRoot -Force
                [IO.File]::WriteAllText($statePath, $stateJson + [Environment]::NewLine, (New-Object Text.UTF8Encoding($false)))
            }
        }
        $reports += [pscustomobject]@{ client = $client; project = $projectRoot; source = $source; destination = $destinationRoot; dry_run = [bool]$DryRun; registry_changed = $stateChanged; files = @($actions) }
    } finally {
        if ($locked) { $mutex.ReleaseMutex() }
        $mutex.Dispose()
    }
}
if ($Json) { ConvertTo-Json -InputObject @($reports) -Depth 10 }
else {
    foreach ($report in $reports) {
        Write-Output ("{0}: {1}; fuente {2}; versión {3}; referencia {4}; vista previa: {5}" -f $report.client, $report.destination, $packageRoot, $packageVersion, $packageReference, $report.dry_run)
        foreach ($item in $report.files) { Write-Output ("{0}: {1}" -f $item.action, $item.path) }
        Write-Output ("Registro cambia: {0}. Los conflictos se conservan. No se modificaron skills globales." -f $report.registry_changed)
    }
}
