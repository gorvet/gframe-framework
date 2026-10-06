$ErrorActionPreference = 'Stop'
$installer = Join-Path (Split-Path -Parent $PSScriptRoot) 'bin/install-skills.ps1'
$testRoot = Join-Path ([IO.Path]::GetTempPath()) ('gframe-project-skills-' + [guid]::NewGuid().ToString())
$null = New-Item -ItemType Directory -Path $testRoot
$checks = 0
$globalBefore = [Environment]::GetEnvironmentVariable('CODEX_HOME')

function Check([bool]$Condition, [string]$Message) {
    if (!$Condition) { throw $Message }
    $script:checks++
}
function Write-Fixture([string]$Path, [string]$Text) {
    $null = New-Item -ItemType Directory -Path (Split-Path -Parent $Path) -Force
    [IO.File]::WriteAllText($Path, $Text, (New-Object Text.UTF8Encoding($false)))
}
function Fixture([string]$Name, [string]$Version) {
    $project = Join-Path $testRoot $Name
    Write-Fixture (Join-Path $project 'composer.json') '{"name":"test/project","config":{"vendor-dir":"packages"}}'
    Write-Fixture (Join-Path $project 'packages/composer/installed.json') ('{"packages":[{"name":"gorvet/gframe","version":"' + $Version + '","source":{"reference":"ref-' + $Name + '"},"install-path":"../gorvet/gframe"}]}')
    Write-Fixture (Join-Path $project 'packages/gorvet/gframe/composer.json') '{"name":"gorvet/gframe"}'
    Write-Fixture (Join-Path $project 'packages/gorvet/gframe/skills/gframe-test/SKILL.md') ('source-' + $Version)
    Write-Fixture (Join-Path $project 'packages/gorvet/gframe/skills/gframe-test/agents/openai.yaml') '{}'
    Write-Fixture (Join-Path $project 'packages/autoload.php') '<?php throw new RuntimeException("Autoload must not execute");'
    return $project
}
function Install([string]$Project, [string]$Client = 'Codex', [switch]$Preview, [switch]$Fail) {
    $arguments = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $installer, '-ProjectPath', $Project, '-Target', $Client, '-Json')
    if ($Preview) { $arguments += '-DryRun' }
    $savedPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $output = & powershell.exe @arguments 2>&1
        $status = $LASTEXITCODE
    } finally { $ErrorActionPreference = $savedPreference }
    if ($Fail) { Check ($status -ne 0) 'Expected rejection'; return }
    if ($status -ne 0) { throw ($output | Out-String) }
    return @((($output | Out-String) | ConvertFrom-Json))
}
function Hash([string]$Path) { return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash }

try {
    $first = Fixture 'first' '1.0.0'
    $second = Fixture 'second' '2.0.0'
    $env:CODEX_HOME = Join-Path $testRoot 'global-not-written'
    $preview = Install $first -Preview
    Check ($preview[0].files.Count -eq 2) 'Preview count'
    Check (!(Test-Path -LiteralPath (Join-Path $first '.agents'))) 'Preview must not create directories'
    $null = Install $first 'All'
    $null = Install $second
    $firstFile = Join-Path $first '.agents/skills/gframe-test/SKILL.md'
    $secondFile = Join-Path $second '.agents/skills/gframe-test/SKILL.md'
    Check ((Get-Content -LiteralPath $firstFile -Raw) -eq 'source-1.0.0') 'First version'
    Check ((Get-Content -LiteralPath $secondFile -Raw) -eq 'source-2.0.0') 'Second version'
    Check ((Get-Content -LiteralPath (Join-Path $first '.claude/skills/gframe-test/SKILL.md') -Raw) -eq 'source-1.0.0') 'Claude destination'
    Check (!(Test-Path -LiteralPath $env:CODEX_HOME)) 'Project install must not touch global'
    $statePath = Join-Path $first '.agents/gframe-skills.json'
    $stateHash = Hash $statePath
    $again = Install $first
    Check (@($again[0].files | Where-Object { $_.action -ne 'unchanged' }).Count -eq 0) 'Repeat must have no file writes'
    Check (!( $again[0].registry_changed )) 'Repeat must not rewrite registry'
    Check ((Hash $statePath) -eq $stateHash) 'Registry repeat hash'
    Write-Fixture (Join-Path $first '.agents/skills/personal/SKILL.md') 'personal'
    Write-Fixture $firstFile 'custom'
    $source = Join-Path $first 'packages/gorvet/gframe/skills/gframe-test'
    Write-Fixture (Join-Path $source 'SKILL.md') 'new-source'
    Write-Fixture (Join-Path $source 'agents/openai.yaml') '{"new":true}'
    $changed = Install $first
    Check (@($changed[0].files | Where-Object { $_.path -eq 'gframe-test/SKILL.md' -and $_.action -eq 'conflict' }).Count -eq 1) 'Modified file conflict'
    Check ((Get-Content -LiteralPath $firstFile -Raw) -eq 'custom') 'Modified file retained'
    Check ((Get-Content -LiteralPath (Join-Path $first '.agents/skills/gframe-test/agents/openai.yaml') -Raw) -eq '{"new":true}') 'Intact file updated'
    Check ((Get-Content -LiteralPath (Join-Path $first '.agents/skills/personal/SKILL.md') -Raw) -eq 'personal') 'Foreign skill retained'
    Write-Fixture (Join-Path $source 'old.txt') 'original'
    Write-Fixture (Join-Path $source 'custom-old.txt') 'original'
    $null = Install $first
    Write-Fixture (Join-Path $first '.agents/skills/gframe-test/custom-old.txt') 'local'
    # Delete only known fixture files, inside the verified temporary root.
    foreach ($name in @('old.txt', 'custom-old.txt')) { Remove-Item -LiteralPath (Join-Path $source $name) }
    $obsolete = Install $first -Preview
    Check (@($obsolete[0].files | Where-Object { $_.action -eq 'remove' }).Count -eq 1) 'Preview obsolete intact'
    Check (Test-Path -LiteralPath (Join-Path $first '.agents/skills/gframe-test/old.txt')) 'Preview must not remove'
    $null = Install $first
    Check (!(Test-Path -LiteralPath (Join-Path $first '.agents/skills/gframe-test/old.txt'))) 'Obsolete intact removed'
    Check ((Get-Content -LiteralPath (Join-Path $first '.agents/skills/gframe-test/custom-old.txt') -Raw) -eq 'local') 'Obsolete custom retained'
    $unknown = Fixture 'unknown' '1.0.0'
    Write-Fixture (Join-Path $unknown '.agents/skills/gframe-test/SKILL.md') 'unregistered'
    $unknownResult = Install $unknown
    Check (@($unknownResult[0].files | Where-Object { $_.action -ne 'conflict' }).Count -eq 0) 'Unregistered folder is not adopted or filled'
    Check (!(Test-Path -LiteralPath (Join-Path $unknown '.agents/skills/gframe-test/agents'))) 'Unregistered folder intact'
    $unknownAgain = Install $unknown
    Check (@($unknownAgain[0].files | Where-Object { $_.action -ne 'conflict' }).Count -eq 0) 'Empty registry must not adopt conflicts on repeat'
    # Tampered state must not allow a deletion outside the skill destination.
    $outside = Join-Path $first 'outside.txt'
    Write-Fixture $outside 'keep'
    $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    $state.files | Add-Member -NotePropertyName 'gframe-test/../../../outside.txt' -NotePropertyValue ((Hash $outside).ToLowerInvariant())
    Write-Fixture $statePath ($state | ConvertTo-Json -Depth 8)
    Install $first -Fail
    Check ((Get-Content -LiteralPath $outside -Raw) -eq 'keep') 'Outside file retained'
    $missing = Fixture 'missing' '1.0.0'
    Remove-Item -LiteralPath (Join-Path $missing 'packages/composer/installed.json')
    Install $missing -Fail
    Check (!(Test-Path -LiteralPath (Join-Path $missing '.agents'))) 'Missing metadata must not fall back or write'
    $wrong = Fixture 'wrong-package' '1.0.0'
    Write-Fixture (Join-Path $wrong 'packages/gorvet/gframe/composer.json') '{"name":"other/package"}'
    Install $wrong -Fail
    Check (!(Test-Path -LiteralPath (Join-Path $wrong '.agents'))) 'Wrong package metadata must not write'
    $junctionProject = Fixture 'junction' '1.0.0'
    $junctionOutside = Join-Path $testRoot 'junction-outside'
    $null = New-Item -ItemType Directory -Path $junctionOutside
    $junction = Join-Path $junctionProject '.agents'
    $null = New-Item -ItemType Junction -Path $junction -Value $junctionOutside
    Install $junctionProject -Fail
    Check (@(Get-ChildItem -LiteralPath $junctionOutside -Force).Count -eq 0) 'Junction target must not be written'
    # Remove just the verified fixture junction, without recursive traversal.
    Remove-Item -LiteralPath $junction -Force
    $complete = Fixture 'complete' 'test-checkout'
    $canonical = Join-Path (Split-Path -Parent $PSScriptRoot) 'skills'
    $completeSource = Join-Path $complete 'packages/gorvet/gframe/skills'
    foreach ($folder in Get-ChildItem -LiteralPath $canonical -Directory -Filter 'gframe-*') {
        Copy-Item -LiteralPath $folder.FullName -Destination $completeSource -Recurse
    }
    $null = Install $complete 'All'
    $canonicalCount = 0
    foreach ($file in Get-ChildItem -LiteralPath $canonical -File -Recurse) {
        $relative = $file.FullName.Substring($canonical.Length + 1)
        Check ((Hash $file.FullName) -eq (Hash (Join-Path $complete ".agents/skills/$relative"))) 'Canonical Codex file bytes'
        Check ((Hash $file.FullName) -eq (Hash (Join-Path $complete ".claude/skills/$relative"))) 'Canonical Claude file bytes'
        $canonicalCount++
    }
    Check ($canonicalCount -gt 0) 'Canonical files verified'
    # Backward-compatible global Codex mode is confined to the temporary CODEX_HOME.
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $installer -Target Codex | Out-Null
    Check ($LASTEXITCODE -eq 0) 'Legacy Codex mode'
    Check (Test-Path -LiteralPath (Join-Path $env:CODEX_HOME 'skills/gframe-orchestrator/SKILL.md')) 'Legacy temporary destination'
    Write-Output ("OK: {0} checks; two versions, preview, both clients, repeat, conflicts, obsolete files, path rejection and missing metadata." -f $checks)
} finally {
    [Environment]::SetEnvironmentVariable('CODEX_HOME', $globalBefore)
    $resolved = [IO.Path]::GetFullPath($testRoot)
    $temporaryRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar
    if (!$resolved.StartsWith($temporaryRoot, [StringComparison]::OrdinalIgnoreCase) -or (Split-Path -Leaf $resolved) -notlike 'gframe-project-skills-*') { throw 'Unsafe fixture cleanup path' }
    Remove-Item -LiteralPath $resolved -Recurse -Force
}
