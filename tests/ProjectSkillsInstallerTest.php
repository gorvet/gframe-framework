<?php

namespace GFrame\Tests;

use GFrame\Install\ProjectScaffolder;
use GFrame\Install\ProjectSkillsInstaller;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProjectSkillsInstallerTest extends TestCase
{
    private string $temporary;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/gframe-skills-php-' . bin2hex(random_bytes(8));
        mkdir($this->temporary);
    }

    protected function tearDown(): void
    {
        $resolved = realpath($this->temporary);
        $prefix = rtrim((string)realpath(sys_get_temp_dir()), '/\\') . DIRECTORY_SEPARATOR;
        if ($resolved === false || !str_starts_with($resolved, $prefix) || !preg_match('/^gframe-skills-php-[a-f0-9]{16}$/D', basename($resolved))) throw new RuntimeException('Ruta temporal inválida.');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($resolved, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($resolved);
    }

    private function write(string $relative, string $text): void
    {
        $path = $this->temporary . '/' . $relative;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        file_put_contents($path, $text);
    }

    private function fixture(string $name, string $version): string
    {
        $this->write($name . '/composer.json', '{"name":"test/project","config":{"vendor-dir":"libraries"}}');
        $this->write($name . '/libraries/composer/installed.json', json_encode(['packages' => [['name' => 'gorvet/gframe', 'version' => $version, 'install-path' => '../gorvet/gframe']]]));
        $this->write($name . '/libraries/gorvet/gframe/composer.json', '{"name":"gorvet/gframe"}');
        $this->write($name . '/libraries/gorvet/gframe/skills/gframe-test/SKILL.md', 'version-' . $version);
        $this->write($name . '/libraries/autoload.php', '<?php throw new RuntimeException("Must not boot");');
        return $this->temporary . '/' . $name;
    }

    public function testTwoProjectsUseTheirOwnVersionAndPreviewDoesNotWrite(): void
    {
        $first = $this->fixture('project with spaces', '1.0');
        $second = $this->fixture('second', '2.0');
        $installer = new ProjectSkillsInstaller();
        $preview = $installer->install($first, 'All', true);
        self::assertSame('create', $preview[0]['files'][0]['action']);
        self::assertDirectoryDoesNotExist($first . '/.agents');
        self::assertDirectoryDoesNotExist($first . '/.claude');
        $installer->install($first);
        $installer->install($second);
        foreach (['.agents', '.claude'] as $client) {
            self::assertSame('version-1.0', file_get_contents($first . '/' . $client . '/skills/gframe-test/SKILL.md'));
            self::assertSame('version-2.0', file_get_contents($second . '/' . $client . '/skills/gframe-test/SKILL.md'));
        }
        $repeat = $installer->install($first);
        self::assertFalse($repeat[0]['registry_changed']);
        self::assertSame('unchanged', $repeat[0]['files'][0]['action']);
    }

    public function testUpdatesIntactFilesAndPreservesCustomAndUnmanagedSkills(): void
    {
        $project = $this->fixture('app', '1.0');
        $installer = new ProjectSkillsInstaller();
        $installer->install($project, 'Codex');
        $this->write('app/libraries/gorvet/gframe/skills/gframe-test/SKILL.md', 'updated');
        self::assertSame('update', $installer->install($project, 'Codex')[0]['files'][0]['action']);
        $this->write('app/.agents/skills/gframe-test/SKILL.md', 'personalized');
        $this->write('app/libraries/gorvet/gframe/skills/gframe-test/SKILL.md', 'another-version');
        self::assertSame('conflict', $installer->install($project, 'Codex')[0]['files'][0]['action']);
        self::assertSame('personalized', file_get_contents($project . '/.agents/skills/gframe-test/SKILL.md'));
        $other = $this->fixture('unmanaged', '2.0');
        $this->write('unmanaged/.agents/skills/gframe-test/SKILL.md', 'unregistered');
        self::assertSame('conflict', $installer->install($other, 'Codex')[0]['files'][0]['action']);
        self::assertSame('conflict', $installer->install($other, 'Codex')[0]['files'][0]['action']);
    }

    public function testRemovesOnlyRegisteredIntactObsoleteFiles(): void
    {
        $project = $this->fixture('app', '1.0');
        $this->write('app/libraries/gorvet/gframe/skills/gframe-test/old.txt', 'old');
        $this->write('app/libraries/gorvet/gframe/skills/gframe-test/custom.txt', 'old');
        $installer = new ProjectSkillsInstaller();
        $installer->install($project, 'Codex');
        $this->write('app/.agents/skills/gframe-test/custom.txt', 'keep');
        unlink($project . '/libraries/gorvet/gframe/skills/gframe-test/old.txt');
        unlink($project . '/libraries/gorvet/gframe/skills/gframe-test/custom.txt');
        $report = $installer->install($project, 'Codex');
        self::assertContains('remove', array_column($report[0]['files'], 'action'));
        self::assertFileDoesNotExist($project . '/.agents/skills/gframe-test/old.txt');
        self::assertSame('keep', file_get_contents($project . '/.agents/skills/gframe-test/custom.txt'));
    }

    public function testTamperedRegistryCannotEscapeDestination(): void
    {
        $project = $this->fixture('app', '1.0');
        $installer = new ProjectSkillsInstaller();
        $installer->install($project, 'Codex');
        $this->write('app/outside.txt', 'keep');
        $state = json_decode(file_get_contents($project . '/.agents/gframe-skills.json'), true);
        $state['files']['gframe-test/../../../outside.txt'] = hash('sha256', 'keep');
        $this->write('app/.agents/gframe-skills.json', json_encode($state));
        try { $installer->install($project, 'Codex'); self::fail('Traversal accepted'); }
        catch (RuntimeException $exception) { self::assertStringContainsString('Ruta de skill', $exception->getMessage()); }
        self::assertSame('keep', file_get_contents($project . '/outside.txt'));
    }

    public function testFrameworkInstallsFromItsCanonicalSource(): void
    {
        $this->write('framework/composer.json', '{"name":"gorvet/gframe"}');
        $this->write('framework/skills/gframe-test/SKILL.md', 'canonical');
        $result = (new ProjectSkillsInstaller())->install($this->temporary . '/framework', 'Codex');
        self::assertSame('standalone-package', $result[0]['source']['evidence']);
        self::assertSame('canonical', file_get_contents($this->temporary . '/framework/.agents/skills/gframe-test/SKILL.md'));
    }

    public function testMissingMetadataDoesNotFallBackToAnotherFramework(): void
    {
        $project = $this->fixture('app', '1.0');
        unlink($project . '/libraries/composer/installed.json');
        try { @(new ProjectSkillsInstaller())->install($project); self::fail('Missing metadata accepted'); }
        catch (RuntimeException $exception) { self::assertDirectoryDoesNotExist($project . '/.agents'); }
    }

    public function testGeneratedProjectRegistersAutomaticAndManualLocalInstallation(): void
    {
        $project = $this->fixture('app', '1.0');
        ProjectScaffolder::frameworkDefault()->publish($project, true);
        $composer = json_decode(file_get_contents($project . '/composer.json'), true);
        self::assertSame('^4.3', $composer['require']['gorvet/gframe']);
        self::assertSame($composer['scripts']['post-autoload-dump'], $composer['scripts']['gframe:skills']);
        self::assertStringContainsString('install-project-skills.php --project=.', $composer['scripts']['post-autoload-dump']);
        self::assertStringContainsString('gframe-orchestrator', file_get_contents($project . '/AGENTS.md'));
        self::assertSame("@AGENTS.md\n", file_get_contents($project . '/CLAUDE.md'));
    }
}
