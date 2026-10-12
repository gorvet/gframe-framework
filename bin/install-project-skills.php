<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/GFrame/Install/ProjectSkillsInstaller.php';

$project = getcwd();
$target = 'All';
$dryRun = $json = false;
try {
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--project=')) $project = substr($argument, 10);
        elseif (str_starts_with($argument, '--target=')) $target = substr($argument, 9);
        elseif ($argument === '--dry-run') $dryRun = true;
        elseif ($argument === '--json') $json = true;
        else throw new RuntimeException('Uso: php bin/install-project-skills.php [--project=.] [--target=Codex|Claude|All] [--dry-run] [--json]');
    }
    $reports = (new GFrame\Install\ProjectSkillsInstaller())->install($project, $target, $dryRun);
    if ($json) echo json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    else foreach ($reports as $report) {
        $counts = array_count_values(array_column($report['files'], 'action'));
        echo $report['client'], ': ', $report['destination'], ' | ', json_encode($counts), PHP_EOL;
    }
    foreach ($reports as $report) {
        if (in_array('conflict', array_column($report['files'], 'action'), true)) {
            fwrite(STDERR, "Hay skills locales personalizadas sin sincronizar. Revisa --dry-run --json.\n");
            exit(1);
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
