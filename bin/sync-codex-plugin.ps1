param(
    [string]$CodexHome = $(if ($env:CODEX_HOME) { $env:CODEX_HOME } else { Join-Path $env:USERPROFILE '.codex' }),
    [string]$CodexCommand = 'codex',
    [switch]$RegisterGitHooks,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$sourceRoot = Split-Path -Parent $PSScriptRoot
$marketplaceRoot = Join-Path ([IO.Path]::GetFullPath($CodexHome)) 'local-marketplaces/gframe-local'
$statePath = Join-Path $marketplaceRoot 'gframe-sync.json'
$utf8 = New-Object Text.UTF8Encoding($false)

function Write-JsonFile([string]$Path, $Value) {
    [IO.File]::WriteAllText($Path, ($Value | ConvertTo-Json -Depth 12) + "`n", $utf8)
}
function Invoke-Codex([string[]]$Arguments) {
    $command = Get-Command $CodexCommand -CommandType Application,ExternalScript -ErrorAction SilentlyContinue | Select-Object -First 1
    if (!$command -and $CodexCommand -eq 'codex' -and $env:LOCALAPPDATA) {
        # El directorio interno cambia con cada actualización de Codex Desktop.
        $desktopRoot = Join-Path $env:LOCALAPPDATA 'OpenAI/Codex/bin'
        if (Test-Path -LiteralPath $desktopRoot) {
            $candidate = Get-ChildItem -LiteralPath $desktopRoot -Directory | ForEach-Object {
                $candidatePath = Join-Path $_.FullName 'codex.exe'
                if (Test-Path -LiteralPath $candidatePath -PathType Leaf) { Get-Item -LiteralPath $candidatePath }
            } | Sort-Object LastWriteTimeUtc -Descending | Select-Object -First 1
            if ($candidate) { $command = Get-Command $candidate.FullName -CommandType Application -ErrorAction Stop }
        }
    }
    if (!$command) { throw "No se encontró el CLI de Codex: $CodexCommand. Instale Codex o indique -CodexCommand." }
    $result = & $command.Source @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Codex falló: $($Arguments -join ' ')" }
    return ($result -join "`n" | ConvertFrom-Json)
}
function Get-Fingerprint {
    $paths = @('plugin.json', 'bin/build-skills-plugin.ps1')
    $paths += @(Get-ChildItem -LiteralPath (Join-Path $sourceRoot 'skills') -Recurse -File -Force |
        ForEach-Object { $_.FullName.Substring($sourceRoot.Length + 1).Replace('\', '/') })
    $entries = foreach ($path in ($paths | Sort-Object)) {
        $item = Get-Item -LiteralPath (Join-Path $sourceRoot $path) -Force
        if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw "No se admiten enlaces: $path" }
        $path + ':' + (Get-FileHash -LiteralPath $item.FullName -Algorithm SHA256).Hash
    }
    $sha = [Security.Cryptography.SHA256]::Create()
    try { return ([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($entries -join "`n")))).Replace('-', '').ToLowerInvariant() }
    finally { $sha.Dispose() }
}
function Quote-Shell([string]$Value) {
    return "'" + $Value.Replace('\', '/').Replace("'", "'" + '"' + "'" + '"' + "'") + "'"
}
function Install-GitHooks {
    $hooksRoot = & git -C $sourceRoot rev-parse --path-format=absolute --git-path hooks
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo resolver la carpeta de hooks de Git.' }
    $null = New-Item -ItemType Directory -Path $hooksRoot -Force
    $marker = '# GFrame Skills sync: ' + $sourceRoot.Replace('\', '/')
    $engine = Quote-Shell (Get-Process -Id $PID).Path
    $script = Quote-Shell $PSCommandPath
    $homeArgument = Quote-Shell $env:CODEX_HOME
    # Conservar el nombre estable, no la ruta interna de una versión instalada.
    $codexArgument = Quote-Shell $CodexCommand
    foreach ($name in @('post-merge', 'post-checkout', 'post-rewrite')) {
        $hook = Join-Path $hooksRoot $name
        $backup = $hook + '.gframe-original'
        $owned = $false
        if (Test-Path -LiteralPath $hook) {
            $owned = @((Get-Content -LiteralPath $hook -Encoding UTF8) | Select-Object -First 2) -contains $marker
            if (!$owned) {
                if (Test-Path -LiteralPath $backup) { throw "Respaldo existente; revise antes de reemplazar: $backup" }
                Move-Item -LiteralPath $hook -Destination $backup
            }
        }
        $backupArgument = Quote-Shell $backup
        $body = @'
#!/bin/sh
{MARKER}
original_status=0
if [ -f {BACKUP} ]; then
    {BACKUP} "$@" || original_status=$?
fi
{ENGINE} -NoProfile -NonInteractive -ExecutionPolicy Bypass -File {SCRIPT} -CodexHome {HOME} -CodexCommand {CODEX} </dev/null || echo 'GFrame: no se pudo sincronizar el complemento; ejecute bin/sync-codex-plugin.ps1.' >&2
exit "$original_status"
'@
        $body = $body.Replace('{MARKER}', $marker).Replace('{BACKUP}', $backupArgument).Replace('{ENGINE}', $engine).Replace('{SCRIPT}', $script).Replace('{HOME}', $homeArgument).Replace('{CODEX}', $codexArgument)
        [IO.File]::WriteAllText($hook, $body.Replace("`r`n", "`n") + "`n", $utf8)
    }
}

$manifest = Get-Content -LiteralPath (Join-Path $sourceRoot 'plugin.json') -Raw -Encoding UTF8 | ConvertFrom-Json
if ($manifest.name -ne 'gframe-skills') { throw 'Identidad del complemento incorrecta.' }
$fingerprint = Get-Fingerprint
$version = ($manifest.version -split '\+')[0] + '+local.' + $fingerprint.Substring(0, 16)
if ($DryRun) {
    [pscustomobject]@{ source = $sourceRoot; marketplace = $marketplaceRoot; version = $version; register_git_hooks = [bool]$RegisterGitHooks; dry_run = $true } | ConvertTo-Json
    return
}

# Separate Git hooks and manual runs must never publish together.
$null = New-Item -ItemType Directory -Path $marketplaceRoot -Force
$lock = $null
$previousCodexHome = $env:CODEX_HOME
try {
    $lock = [IO.File]::Open((Join-Path $marketplaceRoot 'gframe-sync.lock'), 'OpenOrCreate', 'ReadWrite', 'None')
    $env:CODEX_HOME = [IO.Path]::GetFullPath($CodexHome)
    $state = $null
    if (Test-Path -LiteralPath $statePath) {
        $state = Get-Content -LiteralPath $statePath -Raw -Encoding UTF8 | ConvertFrom-Json
        if ($state.source -ne $sourceRoot) { throw 'Este marketplace ya pertenece a otro checkout de GFrame.' }
    }
    $changed = !$state -or $state.fingerprint -ne $fingerprint -or !(Test-Path -LiteralPath $state.result.installedPath)
    if ($changed) {
        $pluginsRoot = Join-Path $marketplaceRoot 'plugins'
        $null = New-Item -ItemType Directory -Path $pluginsRoot -Force
        # Unique snapshots also allow a failed build/install to be retried without overwriting it.
        $packagePath = Join-Path $pluginsRoot ('gframe-skills-' + [guid]::NewGuid().ToString('N'))
        $builder = Join-Path $PSScriptRoot 'build-skills-plugin.ps1'
        $null = & $builder -Version $version -OutputPath $packagePath -DryRun
        $null = & $builder -Version $version -OutputPath $packagePath
        if ((Get-Fingerprint) -ne $fingerprint) { throw 'La fuente cambió durante la copia; vuelva a ejecutar la sincronización.' }
        $catalogPath = Join-Path $marketplaceRoot '.agents/plugins'
        $null = New-Item -ItemType Directory -Path $catalogPath -Force
        $catalog = [ordered]@{
            name = 'gframe-local'
            interface = @{ displayName = 'GFrame Local' }
            plugins = @(@{
                name = 'gframe-skills'
                source = @{ source = 'local'; path = './plugins/' + (Split-Path -Leaf $packagePath) }
                policy = @{ installation = 'AVAILABLE'; authentication = 'ON_INSTALL' }
                category = 'Productivity'
            })
        }
        Write-JsonFile (Join-Path $catalogPath 'marketplace.json') $catalog
        $null = Invoke-Codex @('plugin', 'marketplace', 'add', $marketplaceRoot, '--json')
        $installed = Invoke-Codex @('plugin', 'add', 'gframe-skills@gframe-local', '--json')
        if ($installed.pluginId -ne 'gframe-skills@gframe-local' -or $installed.version -ne $version) { throw 'Codex devolvió otra identidad o versión.' }
        $evidence = Get-Content -LiteralPath (Join-Path $packagePath 'gframe-skills-source.json') -Raw -Encoding UTF8 | ConvertFrom-Json
        foreach ($entry in $evidence.files.PSObject.Properties) {
            $installedFile = Join-Path (Join-Path $installed.installedPath 'skills') $entry.Name
            if ((Get-FileHash -LiteralPath $installedFile -Algorithm SHA256).Hash.ToLowerInvariant() -ne $entry.Value) { throw "La instalación difiere de la fuente: $($entry.Name)" }
        }
        Write-JsonFile $statePath ([ordered]@{
            source = $sourceRoot; fingerprint = $fingerprint; version = $version
            package = $packagePath; installed_at = [DateTime]::UtcNow.ToString('o'); result = $installed
        })
    }
    if ($RegisterGitHooks) { Install-GitHooks }
    [pscustomobject]@{ changed = $changed; version = $version; marketplace = $marketplaceRoot; git_hooks_registered = [bool]$RegisterGitHooks } | ConvertTo-Json
} finally {
    $env:CODEX_HOME = $previousCodexHome
    if ($lock) { $lock.Dispose() }
}
