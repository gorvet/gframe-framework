<?php

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'skills';
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

    preg_match_all('/\]\((references\/[^)]+)\)/', $content, $links);
    foreach ($links[1] ?? [] as $relativePath) {
        $resolved = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($resolved)) {
            $errors[] = "{$folder}: referencia inexistente {$relativePath}.";
        }
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['md', 'yaml', 'yml'], true)) {
            continue;
        }
        $text = (string)file_get_contents($file->getPathname());
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
