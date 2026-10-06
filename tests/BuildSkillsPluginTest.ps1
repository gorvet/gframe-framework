$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
$builder = Join-Path $repo 'bin/build-skills-plugin.ps1'
$testRoot = Join-Path ([IO.Path]::GetTempPath()) ('gframe-plugin-test-' + [guid]::NewGuid().ToString())
$null = New-Item -ItemType Directory -Path $testRoot
$checks = 0
function Check([bool]$Condition, [string]$Message) {
    if (!$Condition) { throw $Message }
    $script:checks++
}
function Build([string]$Output, [string]$Version = '0.0.0-test', [switch]$Preview, [switch]$Fail, [string]$Script = $builder) {
    $arguments = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $Script, '-Version', $Version, '-OutputPath', $Output)
    if ($Preview) { $arguments += '-DryRun' }
    $saved = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $result = & powershell.exe @arguments 2>&1
        $status = $LASTEXITCODE
    } finally { $ErrorActionPreference = $saved }
    if ($Fail) { Check ($status -ne 0) 'Expected build rejection'; return }
    if ($status -ne 0) { throw ($result -join "`n") }
    return ($result -join "`n" | ConvertFrom-Json)
}
try {
    $first = Join-Path $testRoot 'first'
    $plan = Build $first -Preview
    Check ($plan.dry_run -and !(Test-Path -LiteralPath $first)) 'Preview wrote files'
    $built = Build $first
    Check ($built.skills -eq 14 -and $built.files -gt 0 -and $built.local_links -gt 0) 'Incomplete catalog'
    $portable = Get-Content (Join-Path $first 'plugin.json') -Raw -Encoding UTF8 | ConvertFrom-Json
    $claude = Get-Content (Join-Path $first '.claude-plugin/plugin.json') -Raw -Encoding UTF8 | ConvertFrom-Json
    Check ($portable.name -eq 'gframe-skills' -and $portable.version -eq '0.0.0-test') 'Wrong portable identity'
    Check ($portable.name -eq $claude.name -and $portable.version -eq $claude.version) 'Client identities differ'
    $evidence = Get-Content (Join-Path $first 'gframe-skills-source.json') -Raw -Encoding UTF8 | ConvertFrom-Json
    foreach ($property in $evidence.files.PSObject.Properties) {
        $original = Join-Path (Join-Path $repo 'skills') $property.Name
        $copy = Join-Path (Join-Path $first 'skills') $property.Name
        Check ((Get-FileHash -LiteralPath $original).Hash -eq (Get-FileHash -LiteralPath $copy).Hash) ('Copy changed: ' + $property.Name)
        Check ((Get-FileHash -LiteralPath $copy).Hash.ToLowerInvariant() -eq $property.Value) 'Incorrect recorded hash'
    }
    Check (@(Get-ChildItem -LiteralPath $first -File -Recurse).Count -eq ($built.files + 3)) 'Unexpected packaged files'
    $before = (Get-FileHash (Join-Path $first 'plugin.json')).Hash
    Build $first -Fail
    Check ((Get-FileHash (Join-Path $first 'plugin.json')).Hash -eq $before) 'Existing artifact overwritten'
    $second = Join-Path $testRoot 'second'
    $null = Build $second
    foreach ($file in (Get-ChildItem -LiteralPath $first -File -Recurse)) {
        $relative = $file.FullName.Substring($first.Length + 1)
        Check ((Get-FileHash -LiteralPath $file.FullName).Hash -eq (Get-FileHash -LiteralPath (Join-Path $second $relative)).Hash) 'Build is not reproducible for the same source'
    }
    foreach ($version in @('../bad', '1.0', '01.0.0', '1.0.0-01')) { Build (Join-Path $testRoot 'invalid') $version -Fail }
    Check (!(Test-Path (Join-Path $testRoot 'invalid'))) 'Invalid version wrote files'
    Build (Join-Path $repo 'plugin-test-output') -Fail
    Check (!(Test-Path (Join-Path $repo 'plugin-test-output'))) 'Wrote into source repository'
    $fixture = Join-Path $testRoot 'fixture'
    $null = New-Item -ItemType Directory -Path (Join-Path $fixture 'bin') -Force
    Copy-Item -LiteralPath $builder -Destination (Join-Path $fixture 'bin/build-skills-plugin.ps1')
    Copy-Item -LiteralPath (Join-Path $repo 'skills') -Destination $fixture -Recurse
    Copy-Item -LiteralPath (Join-Path $repo 'composer.json') -Destination $fixture
    $fixtureScript = Join-Path $fixture 'bin/build-skills-plugin.ps1'
    Add-Content -LiteralPath (Join-Path $fixture 'skills/gframe-backend/SKILL.md') -Value '[invalid](../../../outside.md)' -Encoding UTF8
    Build (Join-Path $testRoot 'bad-link') -Script $fixtureScript -Fail
    Check (!(Test-Path (Join-Path $testRoot 'bad-link'))) 'Invalid reference wrote files'
    $junction = Join-Path $testRoot 'junction'
    $null = New-Item -ItemType Junction -Path $junction -Target $first
    try { Build (Join-Path $junction 'nested') -Fail } finally { [IO.Directory]::Delete($junction) }
    Check (!(Test-Path (Join-Path $first 'nested'))) 'Junction allowed writes'
    Write-Output "OK: $checks checks; 14 skills, original hashes, portable links, both manifests, reproducibility and rejection cases."
} finally {
    $resolved = [IO.Path]::GetFullPath($testRoot)
    $tempPrefix = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar
    if (!$resolved.StartsWith($tempPrefix, [StringComparison]::OrdinalIgnoreCase) -or (Split-Path -Leaf $resolved) -notlike 'gframe-plugin-test-*') { throw 'Unsafe test cleanup path' }
    Remove-Item -LiteralPath $resolved -Recurse -Force
}
