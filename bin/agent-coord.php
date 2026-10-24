<?php

/**
 * Canal de coordinación entre asistentes (Claude, Codex, …).
 *
 * Almacén JSON compartido FUERA de los worktrees, para que todos lean las
 * reservas de archivos y los mensajes al instante, sin esperar commits ni
 * merges. Las reservas caducan (TTL) para que una sesión interrumpida no deje
 * archivos bloqueados indefinidamente.
 *
 * Ruta del almacén: $GFRAME_AGENT_CHANNEL, o <home>/.gframe-agents/channel.json.
 *
 * Uso:
 *   php bin/agent-coord.php claim   --agent=claude --task="ORM" --files=src/a.php,src/b.php [--ttl=7200]
 *   php bin/agent-coord.php release --agent=claude [--files=src/a.php]
 *   php bin/agent-coord.php check   --file=src/a.php [--agent=claude]
 *   php bin/agent-coord.php status  [--json]
 *   php bin/agent-coord.php say     --agent=claude --text="voy con el router"
 *   php bin/agent-coord.php read    [--since=ISO8601] [--json]
 *
 * `check` sale con código 0 si el archivo está libre o es tuyo, y 2 si otro
 * agente lo tiene reservado (pensado para un hook previo a editar).
 */

const DEFAULT_TTL = 7200; // 2 horas
const MESSAGE_KEEP = 200;

function store_path(): string
{
    $explicit = getenv('GFRAME_AGENT_CHANNEL');
    if (is_string($explicit) && trim($explicit) !== '') {
        return $explicit;
    }
    $home = getenv('USERPROFILE') ?: getenv('HOME') ?: sys_get_temp_dir();
    return rtrim(str_replace('\\', '/', $home), '/') . '/.gframe-agents/channel.json';
}

function args(array $argv): array
{
    $out = [];
    foreach (array_slice($argv, 2) as $token) {
        if (preg_match('/^--([a-z0-9_]+)(?:=(.*))?$/i', $token, $m)) {
            $out[strtolower($m[1])] = $m[2] ?? true;
        }
    }
    return $out;
}

function now(): int { return time(); }
function iso(int $ts): string { return gmdate('c', $ts); }

function norm_file(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#/+#', '/', $path);
    return rtrim($path, '/');
}

/** Raíz del repositorio (toplevel git) que contiene $dir, o null. */
function git_toplevel(string $dir): ?string
{
    while (!is_dir($dir) && dirname($dir) !== $dir) $dir = dirname($dir);
    if (!is_dir($dir)) return null;
    $proc = @proc_open(['git', '-C', $dir, 'rev-parse', '--show-toplevel'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) return null;
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0) return null;
    $top = norm_file(trim((string)$out));
    return $top !== '' ? $top : null;
}

/**
 * Clave común de un archivo entre worktrees: si la ruta es absoluta y cae dentro
 * de un repositorio git, se reduce a su ruta relativa al toplevel; si es relativa
 * se deja tal cual. Así una reserva relativa (claim) y una ruta absoluta (hook)
 * del mismo archivo coinciden aunque vengan de worktrees distintos.
 */
function repo_key(string $path): string
{
    $p = norm_file($path);
    if ($p === '') return $p;
    if (!preg_match('#^(?:[A-Za-z]:/|/)#', $p)) return $p; // relativa: ya es la clave
    $top = git_toplevel($path);
    if ($top !== null) {
        $pl = strtolower($p); $tl = strtolower($top);
        if ($pl === $tl) return '';
        if (str_starts_with($pl, $tl . '/')) return substr($p, strlen($top) + 1);
    }
    return $p; // fuera de un repo git: se queda absoluta
}

function files_arg($value): array
{
    if (!is_string($value) || trim($value) === '') return [];
    return array_values(array_filter(array_map('repo_key', explode(',', $value)), fn($f) => $f !== ''));
}

/**
 * Carga el almacén, podando reservas caducadas. Falla en voz alta (excepción)
 * si el canal existe pero es ilegible o está corrupto, para no confundir un
 * error con "canal vacío"; un archivo inexistente sí es vacío legítimo.
 */
function load(string $path): array
{
    $data = ['claims' => [], 'messages' => []];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('No se pudo leer el canal: ' . $path);
        }
        if (trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || !is_array($decoded['claims'] ?? null) || !is_array($decoded['messages'] ?? null)) {
                throw new RuntimeException('El canal está corrupto: ' . $path);
            }
            $data['claims'] = is_array($decoded['claims'] ?? null) ? $decoded['claims'] : [];
            $data['messages'] = is_array($decoded['messages'] ?? null) ? $decoded['messages'] : [];
        }
    }
    $t = now();
    $data['claims'] = array_values(array_filter(
        $data['claims'],
        fn($c) => is_array($c) && (int)($c['expires_at'] ?? 0) > $t && !empty($c['files'])
    ));
    return $data;
}

/**
 * Candado exclusivo para operaciones de lectura-modificación-escritura.
 * Serializa a escritores concurrentes; los lectores no lo necesitan porque
 * save() publica de forma atómica (escritura a temporal + rename). El sistema
 * libera el flock al terminar el proceso aunque no se desbloquee explícitamente.
 */
function lock_acquire(string $path)
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $fh = @fopen($path . '.lock', 'c');
    if (!$fh || !@flock($fh, LOCK_EX)) {
        if ($fh) @fclose($fh);
        throw new RuntimeException('No se pudo bloquear el canal: ' . $path);
    }
    return $fh;
}

function lock_release($fh): void
{
    if ($fh) { @flock($fh, LOCK_UN); @fclose($fh); }
}

/** Publica el almacén de forma atómica (temporal + rename). Devuelve false si falla. */
function save(string $path, array $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    if (count($data['messages']) > MESSAGE_KEEP) {
        $data['messages'] = array_slice($data['messages'], -MESSAGE_KEEP);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return false;
    }
    $tmp = $path . '.tmp' . getmypid();
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/** Reserva viva de otro agente sobre un archivo, o null. */
function foreign_holder(array $data, string $file, string $agent): ?array
{
    $file = repo_key($file);
    foreach ($data['claims'] as $claim) {
        if (strcasecmp((string)($claim['agent'] ?? ''), $agent) === 0) continue;
        foreach ((array)($claim['files'] ?? []) as $claimed) {
            if (strcasecmp(repo_key((string)$claimed), $file) === 0) {
                return $claim;
            }
        }
    }
    return null;
}

$path = store_path();
$cmd = $argv[1] ?? 'status';
$o = args($argv);
$agent = isset($o['agent']) && is_string($o['agent']) ? strtolower(trim($o['agent'])) : '';

try {
switch ($cmd) {
    case 'claim': {
        if ($agent === '') { fwrite(STDERR, "Falta --agent\n"); exit(1); }
        $files = files_arg($o['files'] ?? '');
        if ($files === []) { fwrite(STDERR, "Falta --files\n"); exit(1); }
        $ttl = max(60, (int)($o['ttl'] ?? DEFAULT_TTL));
        $lock = lock_acquire($path);
        $data = load($path);

        $conflicts = [];
        foreach ($files as $f) {
            $holder = foreign_holder($data, $f, $agent);
            if ($holder !== null) $conflicts[] = $f . ' (lo tiene ' . ($holder['agent'] ?? '?') . ')';
        }
        if ($conflicts !== []) {
            fwrite(STDERR, "Conflicto, no se reservó:\n - " . implode("\n - ", $conflicts) . "\n");
            exit(2);
        }

        $found = false;
        foreach ($data['claims'] as &$claim) {
            if (strcasecmp((string)($claim['agent'] ?? ''), $agent) === 0) {
                $claim['files'] = array_values(array_unique(array_merge((array)$claim['files'], $files)));
                $claim['task'] = (string)($o['task'] ?? $claim['task'] ?? '');
                $claim['expires_at'] = now() + $ttl;
                $found = true;
                break;
            }
        }
        unset($claim);
        if (!$found) {
            $data['claims'][] = [
                'agent' => $agent,
                'task' => (string)($o['task'] ?? ''),
                'files' => $files,
                'claimed_at' => now(),
                'expires_at' => now() + $ttl,
                'status' => 'active',
            ];
        }
        if (!save($path, $data)) { fwrite(STDERR, "No se pudo escribir el canal.\n"); exit(1); }
        lock_release($lock);
        echo "Reservado por {$agent} (expira " . iso(now() + $ttl) . "):\n - " . implode("\n - ", $files) . "\n";
        break;
    }

    case 'release': {
        if ($agent === '') { fwrite(STDERR, "Falta --agent\n"); exit(1); }
        $files = files_arg($o['files'] ?? '');
        $lock = lock_acquire($path);
        $data = load($path);
        $next = [];
        foreach ($data['claims'] as $claim) {
            if (strcasecmp((string)($claim['agent'] ?? ''), $agent) !== 0) { $next[] = $claim; continue; }
            if ($files === []) continue; // libera todo lo del agente
            $claim['files'] = array_values(array_filter(
                (array)$claim['files'],
                fn($f) => !in_array(repo_key((string)$f), array_map('repo_key', $files), true)
            ));
            if ($claim['files'] !== []) $next[] = $claim;
        }
        $data['claims'] = $next;
        if (!save($path, $data)) { fwrite(STDERR, "No se pudo escribir el canal.\n"); exit(1); }
        lock_release($lock);
        echo "Liberado.\n";
        break;
    }

    case 'check': {
        $file = isset($o['file']) && is_string($o['file']) ? $o['file'] : '';
        if ($file === '') { fwrite(STDERR, "Falta --file\n"); exit(1); }
        $data = load($path);
        $holder = foreign_holder($data, $file, $agent);
        if ($holder !== null) {
            fwrite(STDERR, "⚠ {$file} está reservado por '" . ($holder['agent'] ?? '?') . "'"
                . (($holder['task'] ?? '') !== '' ? " (tarea: {$holder['task']})" : '')
                . ". Coordina antes de editar.\n");
            exit(2);
        }
        exit(0);
    }

    case 'say': {
        if ($agent === '') { fwrite(STDERR, "Falta --agent\n"); exit(1); }
        $text = isset($o['text']) && is_string($o['text']) ? trim($o['text']) : '';
        if ($text === '') { fwrite(STDERR, "Falta --text\n"); exit(1); }
        $lock = lock_acquire($path);
        $data = load($path);
        $data['messages'][] = ['at' => iso(now()), 'agent' => $agent, 'text' => $text];
        if (!save($path, $data)) { fwrite(STDERR, "No se pudo escribir el canal.\n"); exit(1); }
        lock_release($lock);
        echo "Mensaje publicado.\n";
        break;
    }

    case 'read': {
        $data = load($path);
        $since = isset($o['since']) && is_string($o['since']) ? strtotime($o['since']) : 0;
        $msgs = array_values(array_filter(
            $data['messages'],
            fn($m) => $since <= 0 || strtotime((string)($m['at'] ?? '')) > $since
        ));
        if (!empty($o['json'])) { echo json_encode($msgs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"; break; }
        if ($msgs === []) { echo "(sin mensajes)\n"; break; }
        foreach ($msgs as $m) echo "[{$m['at']}] {$m['agent']}: {$m['text']}\n";
        break;
    }

    case 'status':
    default: {
        $data = load($path);
        if (!empty($o['json'])) { echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"; break; }
        echo "Canal: {$path}\n\n== Reservas activas ==\n";
        if ($data['claims'] === []) echo "(ninguna)\n";
        foreach ($data['claims'] as $c) {
            echo "- {$c['agent']} — " . ($c['task'] !== '' ? $c['task'] : '(sin tarea)')
                . " — expira {$c['expires_at']} (" . iso((int)$c['expires_at']) . ")\n";
            foreach ((array)$c['files'] as $f) echo "    · {$f}\n";
        }
        echo "\n== Últimos mensajes ==\n";
        $tail = array_slice($data['messages'], -10);
        if ($tail === []) echo "(ninguno)\n";
        foreach ($tail as $m) echo "[{$m['at']}] {$m['agent']}: {$m['text']}\n";
        break;
    }
}
} catch (\Throwable $e) {
    // Fallar cerrado ante un canal ilegible/corrupto: check bloquea (2); el
    // resto no anuncia éxito (1). No se traduce un error interno a éxito.
    fwrite(STDERR, '⚠ ' . $e->getMessage() . "\n");
    exit($cmd === 'check' ? 2 : 1);
}
