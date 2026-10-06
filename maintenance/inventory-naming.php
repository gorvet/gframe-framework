<?php
// Read-only source inventory. Writes evidence only under maintenance/, never a naming fix.
$root = str_replace('\\', '/', dirname(__DIR__));
$excluded = [];
foreach (glob($root . '/resources/modules/*', GLOB_ONLYDIR) as $dir) {
    $manifest = $dir . '/module.php';
    if (!is_file($manifest) || preg_match("/'type'\s*=>\s*'external-ui'/", file_get_contents($manifest))) {
        $excluded[] = substr($dir, strlen($root) + 1) . '/';
    }
}
$excluded = array_merge($excluded, ['resources/modules/markdown/src/', 'resources/modules/password-utils/src/', 'resources/modules/gframe-icons/public/demo-files/']);
$variables = []; $counts = ['php_files' => 0, 'js_files' => 0, 'html_files' => 0, 'html_ids' => 0]; $samples = []; $hashes = [];
foreach (['src', 'resources'] as $area) {
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $area, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        foreach ($excluded as $prefix) if (str_starts_with($relative, $prefix)) continue 2;
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'js', 'html'], true) || str_ends_with($relative, '.min.js')) continue;
        $text = file_get_contents($file->getPathname()); $hashes[$relative] = hash('sha256', $text);
        if ($ext === 'html') $counts['html_files']++;
        if ($ext === 'php') {
            $counts['php_files']++;
            foreach (token_get_all($text) as $token) {
                if (!is_array($token) || $token[0] !== T_VARIABLE) continue;
                $name = $token[1];
                $group = preg_match('/[a-z](?:Id|Ids)$/', $name) ? 'id_mixed_suffix' : (preg_match('/(?:ID|IDs)$/', $name) ? 'id_upper_suffix' : (str_contains($name, '_') && !in_array($name, ['$GLOBALS', '$_POST', '$_GET', '$_SESSION', '$_SERVER', '$_COOKIE', '$_FILES', '$_ENV', '$_REQUEST'], true) ? 'underscore' : null));
                if ($group) {
                    $variables[$group][$name]['occurrences'] = ($variables[$group][$name]['occurrences'] ?? 0) + 1;
                    $variables[$group][$name]['locations'][$relative . ':' . $token[2]] = true;
                }
            }
        }
        if ($ext === 'js') {
            $counts['js_files']++;
            preg_match_all('/\b(?:function\s+|(?:const|let|var)\s+)(\$?[A-Za-z_][A-Za-z0-9_]*(?:Id|Ids))\b/', $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[1] as [$name, $offset]) $samples['js_mixed_id'][] = ['name' => $name, 'location' => $relative . ':' . (substr_count(substr($text, 0, $offset), "\n") + 1)];
        }
        if (in_array($ext, ['php', 'html'], true)) {
            preg_match_all('/(?<![A-Za-z0-9_-])id\s*=\s*["\x27]([A-Za-z][A-Za-z0-9_-]*)["\x27]/', $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[1] as [$name, $offset]) {
                $counts['html_ids']++;
                $samples['literal_ids'][] = ['name' => $name, 'location' => $relative . ':' . (substr_count(substr($text, 0, $offset), "\n") + 1)];
            }
        }
    }
}
foreach ($variables as $groupName => &$group) {
    ksort($group);
    foreach ($group as &$entry) {
        if ($groupName === 'id_upper_suffix') {
            $entry['files_count'] = count(array_unique(array_map(static fn($location) => explode(':', $location)[0], array_keys($entry['locations']))));
            unset($entry['locations']);
        } else {
            $entry['locations'] = array_keys($entry['locations']);
        }
    }
    unset($entry);
}
unset($group);
ksort($variables); ksort($hashes); sort($excluded);
foreach ($samples as &$entries) usort($entries, static fn($a, $b) => strcmp($a['location'], $b['location']) ?: strcmp($a['name'], $b['name']));
unset($entries);
$out = ['generator_sha256' => hash_file('sha256', __FILE__), 'scope' => ['src', 'resources'], 'excluded_prefixes' => $excluded, 'counts' => $counts, 'php_variables' => $variables, 'lexical_samples_not_ast' => $samples, 'source_hashes' => $hashes];
if (file_put_contents($root . '/maintenance/nomenclatura-evidencia-20261006.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n") === false) {
    throw new RuntimeException('No se pudo guardar la evidencia de nomenclatura.');
}
echo json_encode(['counts' => $counts, 'php_names' => array_map('array_keys', $variables), 'js_candidates' => $samples['js_mixed_id'] ?? []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
