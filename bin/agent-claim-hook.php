<?php

/**
 * Hook PreToolUse (Edit|Write) para avisar/bloquear si otro agente tiene
 * reservado el archivo que vas a editar. Inerte hasta registrarlo en
 * .claude/settings.json (ver maintenance/coordinacion-agentes.md).
 *
 * Lee el JSON del hook por stdin, saca la ruta del archivo y delega en
 * agent-coord.php check. Sale con 2 (bloquea) si hay conflicto; 0 en caso
 * contrario. El nombre del agente se toma de $GFRAME_AGENT (por defecto claude).
 */

$raw = stream_get_contents(STDIN);
$input = json_decode((string)$raw, true);
$file = '';
if (is_array($input)) {
    $toolInput = $input['tool_input'] ?? [];
    if (is_array($toolInput)) {
        $file = (string)($toolInput['file_path'] ?? $toolInput['path'] ?? '');
    }
}
if ($file === '') {
    exit(0);
}

$agent = getenv('GFRAME_AGENT') ?: 'claude';
$cmd = escapeshellarg(PHP_BINARY) . ' '
     . escapeshellarg(__DIR__ . '/agent-coord.php') . ' check'
     . ' --file=' . escapeshellarg($file)
     . ' --agent=' . escapeshellarg($agent);

$code = 0;
passthru($cmd, $code);
exit($code === 0 ? 0 : 2);
