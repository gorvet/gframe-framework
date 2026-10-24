param([string]$CodexCommand = 'codex')
$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
$testRoot = Join-Path ([IO.Path]::GetTempPath()) ('gframe-sync-test-' + [guid]::NewGuid().ToString('N'))
$fixture = Join-Path $testRoot 'framework with spaces'
$codexTestHome = Join-Path $testRoot 'codex home'
$checks = 0
function Check([bool]$Condition, [string]$Message) {
    if (!$Condition) { throw $Message }
    $script:checks++
}
function Sync-Plugin([switch]$Preview, [switch]$Hooks) {
    $arguments = @{ CodexHome = $codexTestHome; CodexCommand = $CodexCommand }
    if ($Preview) { $arguments.DryRun = $true }
    if ($Hooks) { $arguments.RegisterGitHooks = $true }
    return (& (Join-Path $fixture 'bin/sync-codex-plugin.ps1') @arguments | ConvertFrom-Json)
}
try {
    $null = New-Item -ItemType Directory -Path (Join-Path $fixture 'bin') -Force
    foreach ($file in @('plugin.json', 'composer.json', 'skills')) {
        Copy-Item -LiteralPath (Join-Path $repo $file) -Destination $fixture -Recurse
    }
    foreach ($file in @('build-skills-plugin.ps1', 'sync-codex-plugin.ps1')) {
        Copy-Item -LiteralPath (Join-Path $repo "bin/$file") -Destination (Join-Path $fixture 'bin')
    }
    $null = & git -C $fixture init -q
    $hook = Join-Path $fixture '.git/hooks/post-merge'
    $original = "#!/bin/sh`necho original > original-hook.txt`nexit 7`n"
    [IO.File]::WriteAllText($hook, $original, (New-Object Text.UTF8Encoding($false)))
    $preview = Sync-Plugin -Preview -Hooks
    Check ($preview.dry_run -and !(Test-Path $codexTestHome)) 'Preview wrote user configuration'
    Check ((Get-Content $hook -Raw) -eq $original) 'Preview changed a Git hook'
    $first = Sync-Plugin -Hooks
    Check $first.changed 'Initial install did not run'
    $statePath = Join-Path $codexTestHome 'local-marketplaces/gframe-local/gframe-sync.json'
    $state = Get-Content $statePath -Raw | ConvertFrom-Json
    Check (@(Get-ChildItem (Join-Path $state.result.installedPath 'skills') -Directory).Count -eq 14) 'Missing installed skills'
    Check ((Get-Content ($hook + '.gframe-original') -Raw) -eq $original) 'Original hook was changed'
    $before = (Get-FileHash $statePath).Hash
    $second = Sync-Plugin -Hooks
    Check (!$second.changed -and (Get-FileHash $statePath).Hash -eq $before) 'Unchanged source was reinstalled'
    $skill = Join-Path $fixture 'skills/gframe-backend/SKILL.md'
    [IO.File]::AppendAllText($skill, "`n<!-- synchronization test -->`n", (New-Object Text.UTF8Encoding($false)))
    $null = & git -C $fixture hook run post-merge -- 0 2>&1
    Check ($LASTEXITCODE -eq 7) 'Original hook exit status was lost'
    Check (Test-Path (Join-Path $fixture 'original-hook.txt')) 'Original hook was not executed'
    $updated = Get-Content $statePath -Raw | ConvertFrom-Json
    Check ($updated.version -ne $state.version) 'Git hook did not install changed source'
    Check ((Get-FileHash $skill).Hash -eq (Get-FileHash (Join-Path $updated.result.installedPath 'skills/gframe-backend/SKILL.md')).Hash) 'Installed skill differs from source'
    $before = (Get-FileHash $statePath).Hash
    [IO.File]::AppendAllText($skill, "`n[broken](../../../outside.md)`n", (New-Object Text.UTF8Encoding($false)))
    $rejected = $false
    try { $null = Sync-Plugin } catch { $rejected = $true }
    Check $rejected 'Broken references were installed'
    Check ((Get-FileHash $statePath).Hash -eq $before) 'Failed validation changed installed state'
    Write-Output "OK: $checks checks; isolated Codex install, dry run, unchanged source, Git hook, original preservation, hashes and failed build."
} finally {
    $resolved = [IO.Path]::GetFullPath($testRoot)
    $prefix = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar
    if (!$resolved.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase) -or (Split-Path -Leaf $resolved) -notlike 'gframe-sync-test-*') { throw 'Unsafe cleanup path' }
    Remove-Item -LiteralPath $resolved -Recurse -Force
}
