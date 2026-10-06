<?php
// Parse declarations only; never execute framework/module source files.
$root = dirname(__DIR__);
$composer = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$vendor = $composer['config']['vendor-dir'] ?? 'vendor';
require $root . '/' . $vendor . '/autoload.php';
if (!class_exists(\PhpParser\ParserFactory::class)) throw new RuntimeException('Instale las dependencias de desarrollo del checkout para este inventario.');
$parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
$excluded = [];
foreach (glob($root . '/resources/modules/*', GLOB_ONLYDIR) as $directory) {
    $manifest = $directory . '/module.php';
    if (!is_file($manifest) || preg_match("/'type'\s*=>\s*'external-ui'/", file_get_contents($manifest))) {
        $excluded[] = str_replace('\\', '/', substr($directory, strlen($root) + 1)) . '/';
    }
}
$evidence = ['scope' => ['src', 'resources'], 'excluded_prefixes' => $excluded, 'source_hashes' => [], 'types' => [], 'callables' => []];
foreach ($evidence['scope'] as $area) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $area, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        foreach ($excluded as $prefix) if (str_starts_with($relative, $prefix)) continue 2;
        $source = file_get_contents($file->getPathname());
        $evidence['source_hashes'][$relative] = hash('sha256', $source);
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver());
        $nodes = $traverser->traverse($parser->parse($source) ?? []);
        $finder = new \PhpParser\NodeFinder();
        foreach ($finder->findInstanceOf($nodes, \PhpParser\Node\Stmt\ClassLike::class) as $type) {
            if ($type->name === null) continue; // Anonymous classes are not stable named extension points.
            $name = (string)($type->namespacedName ?? $type->name);
            $parents = [];
            if ($type instanceof \PhpParser\Node\Stmt\Class_) {
                if ($type->extends !== null) $parents[] = (string)$type->extends;
                foreach ($type->implements as $interface) $parents[] = (string)$interface;
            } elseif ($type instanceof \PhpParser\Node\Stmt\Interface_) {
                foreach ($type->extends as $interface) $parents[] = (string)$interface;
            }
            $traits = []; $adaptations = 0;
            foreach ($type->stmts as $statement) if ($statement instanceof \PhpParser\Node\Stmt\TraitUse) {
                foreach ($statement->traits as $trait) $traits[] = (string)$trait;
                $adaptations += count($statement->adaptations);
            }
            $evidence['types'][] = ['name' => $name, 'kind' => $type->getType(), 'location' => $relative . ':' . $type->getStartLine(), 'parents' => $parents, 'traits' => $traits, 'trait_adaptation_count' => $adaptations];
            foreach ($type->getMethods() as $method) if ($method->isPublic()) {
                $evidence['callables'][] = apiEntry($name . '::' . $method->name, $method, $relative);
            }
        }
        foreach ($finder->findInstanceOf($nodes, \PhpParser\Node\Stmt\Function_::class) as $function) {
            $evidence['callables'][] = apiEntry((string)($function->namespacedName ?? $function->name), $function, $relative);
        }
    }
}
function apiEntry(string $name, \PhpParser\Node $node, string $relative): array
{
    $parameters = [];
    foreach ($node->params as $parameter) $parameters[] = ['name' => (string)$parameter->var->name, 'variadic' => $parameter->variadic, 'by_reference' => $parameter->byRef];
    return ['name' => $name, 'location' => $relative . ':' . $node->getStartLine(), 'parameters' => $parameters];
}
ksort($evidence['source_hashes']); sort($evidence['excluded_prefixes']);
foreach (['types', 'callables'] as $key) usort($evidence[$key], static fn(array $a, array $b): int => strcmp($a['name'], $b['name']) ?: strcmp($a['location'], $b['location']));
$evidence['generator_sha256'] = hash_file('sha256', __FILE__);
$evidence['parser_version'] = \Composer\InstalledVersions::getPrettyVersion('nikic/php-parser');
$output = $root . '/maintenance/nomenclatura-apis-20261006.json';
$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
$sections = [];
foreach ($evidence as $key => $value) {
    if (in_array($key, ['types', 'callables'], true)) {
        $items = array_map(static fn(array $entry): string => '    ' . json_encode($entry, $flags), $value);
        $encoded = "[\n" . implode(",\n", $items) . "\n  ]";
    } else {
        $encoded = str_replace("\n", "\n  ", json_encode($value, $flags | JSON_PRETTY_PRINT));
    }
    $sections[] = '  ' . json_encode($key, $flags) . ': ' . $encoded;
}
$json = "{\n" . implode(",\n", $sections) . "\n}\n";
if (file_put_contents($output, $json) === false) throw new RuntimeException('No se pudo guardar el catálogo de firmas.');
echo json_encode(['php_files' => count($evidence['source_hashes']), 'types' => count($evidence['types']), 'public_declarations' => count($evidence['callables'])], JSON_THROW_ON_ERROR), "\n";
