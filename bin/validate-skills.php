<?php

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'skills';
if ($argc !== 1) {
    if ($argc !== 3 || $argv[1] !== '--root') {
        fwrite(STDERR, "Uso: php bin/validate-skills.php [--root <carpeta-skills>]\n");
        exit(2);
    }
    $root = $argv[2];
}
$root = realpath($root);
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "La carpeta de skills no existe.\n");
    exit(1);
}
$rootPrefix = str_replace('\\', '/', $root) . '/';
$linkCount = 0;
$errors = [];
$skills = glob($root . DIRECTORY_SEPARATOR . 'gframe-*', GLOB_ONLYDIR) ?: [];

if ($skills === []) {
    $errors[] = 'No se encontraron skills de GFrame.';
}

foreach ($skills as $directory) {
    $folder = basename($directory);
    $skillFile = $directory . DIRECTORY_SEPARATOR . 'SKILL.md';
    $agentFile = $directory . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'openai.yaml';
    if (!is_file($skillFile)) {
        $errors[] = "{$folder}: falta SKILL.md.";
        continue;
    }
    if (!is_file($agentFile)) {
        $errors[] = "{$folder}: falta agents/openai.yaml.";
    }

    $content = (string)file_get_contents($skillFile);
    if (!preg_match('/\A---\R(?<frontmatter>.*?)\R---\R/s', $content, $match)) {
        $errors[] = "{$folder}: frontmatter inválido.";
        continue;
    }
    if (!preg_match('/^name:\s*([^\r\n]+)$/m', $match['frontmatter'], $nameMatch) || trim($nameMatch[1]) !== $folder) {
        $errors[] = "{$folder}: el nombre no coincide con el directorio.";
    }
    if (!preg_match('/^description:\s*\S.+$/m', $match['frontmatter'])) {
        $errors[] = "{$folder}: falta una descripción útil.";
    }
    if (!preg_match('/^[a-z0-9-]{1,63}$/', $folder)) {
        $errors[] = "{$folder}: nombre de directorio inválido.";
    }
    if (preg_match('/^\s*(?:[-*]\s*)?(?:TODO|TBD|PLACEHOLDER)\s*:/im', $content)) {
        $errors[] = "{$folder}: contiene marcadores pendientes.";
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['md', 'yaml', 'yml'], true)) {
            continue;
        }
        $text = (string)file_get_contents($file->getPathname());
        if (!preg_match('//u', $text)) {
            $errors[] = $folder . ': UTF-8 inválido en ' . $file->getFilename() . '.';
            continue;
        }
        if (strtolower($file->getExtension()) === 'md') {
            // Ignore fenced examples and inline code; inspect inline links/images and reference definitions.
            $markdown = preg_replace('/^ {0,3}(`{3,}|~{3,})[^\r\n]*\R.*?^ {0,3}\1[^\r\n]*$/ms', '', $text);
            $markdown = preg_replace('/(`+).*?\1/s', '', $markdown);
            preg_match_all('/\]\(\s*(?:<([^>]+)>|([^\s)]+))(?:\s+"[^"\\r\\n]*")?\s*\)/', $markdown, $inline, PREG_SET_ORDER);
            preg_match_all('/^ {0,3}\[[^\]\r\n]+\]:\s*(?:<([^>]+)>|(\S+))/m', $markdown, $references, PREG_SET_ORDER);
            foreach (array_merge($inline, $references) as $link) {
                $target = $link[1] !== '' ? $link[1] : $link[2];
                if (str_starts_with($target, '#') || preg_match('/^(?:https?:|mailto:)/i', $target)) {
                    continue;
                }
                if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) || str_starts_with($target, '//') || str_starts_with($target, '/') || str_contains($target, '\\')) {
                    $errors[] = "{$folder}/{$file->getFilename()}: enlace no portable {$target}.";
                    continue;
                }
                $relative = rawurldecode(explode('#', $target, 2)[0]);
                if (str_contains($relative, "\0")) {
                    $errors[] = "{$folder}/{$file->getFilename()}: enlace inválido.";
                    continue;
                }
                $resolved = realpath($file->getPath() . DIRECTORY_SEPARATOR . $relative);
                $normalized = $resolved === false ? '' : str_replace('\\', '/', $resolved);
                $contained = PHP_OS_FAMILY === 'Windows'
                    ? str_starts_with(strtolower($normalized), strtolower($rootPrefix))
                    : str_starts_with($normalized, $rootPrefix);
                if ($resolved === false || !is_file($resolved) || !$contained) {
                    $errors[] = "{$folder}/{$file->getFilename()}: referencia inexistente o fuera de las skills {$target}.";
                } else {
                    $linkCount++;
                }
            }
        }
        foreach (['core/routing/', 'core/render/', 'core/database/', 'config/Config.php'] as $obsolete) {
            if (str_contains($text, $obsolete)) {
                $errors[] = $folder . ': referencia obsoleta `' . $obsolete . '` en ' . $file->getFilename() . '.';
            }
        }
        if (preg_match('/Ã.|Â.|ï¿½/u', $text)) {
            $errors[] = $folder . ': posible texto mal codificado en ' . $file->getFilename() . '.';
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, array_values(array_unique($errors))) . PHP_EOL);
    exit(1);
}

echo 'Skills válidos: ' . count($skills) . PHP_EOL;
echo 'Enlaces locales comprobados: ' . $linkCount . PHP_EOL;
