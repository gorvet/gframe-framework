<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Verifica la CLI del canal de coordinación (bin/agent-coord.php): reservas,
 * conflictos, liberación, caducidad (TTL), normalización de rutas entre
 * worktrees y mensajes. Usa un almacén temporal, sin tocar el canal real.
 */
final class AgentCoordChannelTest extends TestCase
{
    private string $channel;
    private string $cli;

    protected function setUp(): void
    {
        $this->channel = sys_get_temp_dir() . '/gframe-coord-' . bin2hex(random_bytes(6)) . '.json';
        $this->cli = dirname(__DIR__) . '/bin/agent-coord.php';
        putenv('GFRAME_AGENT_CHANNEL=' . $this->channel);
    }

    protected function tearDown(): void
    {
        @unlink($this->channel);
        @unlink($this->channel . '.lock');
        putenv('GFRAME_AGENT_CHANNEL');
    }

    /** Ejecuta la CLI y devuelve [code, out, err]. */
    private function cli(array $args): array
    {
        $cmd = array_merge([PHP_BINARY, $this->cli], $args);
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $code = proc_close($proc);
        return [$code, (string)$out, (string)$err];
    }

    public function testClaimConflictAndRelease(): void
    {
        [$c] = $this->cli(['claim', '--agent=claude', '--task=ORM', '--files=src/a.php,src/b.php']);
        self::assertSame(0, $c);

        // Otro agente no puede reservar lo ya reservado.
        [$c] = $this->cli(['claim', '--agent=codex', '--files=src/a.php']);
        self::assertSame(2, $c);

        // Pero sí un archivo libre.
        [$c] = $this->cli(['claim', '--agent=codex', '--files=src/c.php']);
        self::assertSame(0, $c);

        // check: ajeno=2, propio=0, libre=0.
        self::assertSame(2, $this->cli(['check', '--file=src/a.php', '--agent=codex'])[0]);
        self::assertSame(0, $this->cli(['check', '--file=src/a.php', '--agent=claude'])[0]);
        self::assertSame(0, $this->cli(['check', '--file=src/z.php', '--agent=codex'])[0]);

        // Tras liberar, el otro ya puede reservarlo.
        self::assertSame(0, $this->cli(['release', '--agent=claude', '--files=src/a.php'])[0]);
        self::assertSame(0, $this->cli(['claim', '--agent=codex', '--files=src/a.php'])[0]);
    }

    public function testExpiredClaimsArePrunedWithoutSleeping(): void
    {
        // Escribe directamente una reserva ya caducada.
        file_put_contents($this->channel, json_encode([
            'claims' => [[
                'agent' => 'codex', 'task' => 'viejo', 'files' => ['src/a.php'],
                'claimed_at' => time() - 10000, 'expires_at' => time() - 1, 'status' => 'active',
            ]],
            'messages' => [],
        ]));

        // La reserva caducada no bloquea ni aparece activa.
        self::assertSame(0, $this->cli(['check', '--file=src/a.php', '--agent=claude'])[0]);
        [, $out] = $this->cli(['status']);
        self::assertStringContainsString('(ninguna)', $out);
    }

    public function testPathSeparatorsAreNormalizedAcrossWorktrees(): void
    {
        // Reserva con barra invertida (estilo Windows).
        self::assertSame(0, $this->cli(['claim', '--agent=codex', '--files=src\\\\models\\\\X.php'])[0]);
        // La comprobación con barra normal detecta el conflicto igualmente.
        self::assertSame(2, $this->cli(['check', '--file=src/models/X.php', '--agent=claude'])[0]);
    }

    public function testMessagesRoundTrip(): void
    {
        self::assertSame(0, $this->cli(['say', '--agent=claude', '--text=hola equipo'])[0]);
        [, $out] = $this->cli(['read']);
        self::assertStringContainsString('claude: hola equipo', $out);
    }

    public function testCorruptChannelFailsClosed(): void
    {
        file_put_contents($this->channel, 'esto no es json {{{');
        // check falla cerrado (bloquea), no lo traduce a "libre".
        self::assertSame(2, $this->cli(['check', '--file=src/a.php', '--agent=claude'])[0]);
        // claim no anuncia éxito sobre un canal corrupto.
        self::assertSame(1, $this->cli(['claim', '--agent=claude', '--files=src/a.php'])[0]);
    }

    public function testAbsolutePathReducesToRepoRelative(): void
    {
        // Reserva un archivo real por su ruta ABSOLUTA dentro de este worktree…
        $abs = dirname(__DIR__) . '/bin/agent-coord.php';
        self::assertSame(0, $this->cli(['claim', '--agent=codex', '--files=' . $abs])[0]);
        // …y la comprobación con la ruta RELATIVA al repo detecta el conflicto.
        self::assertSame(2, $this->cli(['check', '--file=bin/agent-coord.php', '--agent=claude'])[0]);
    }

    public function testNewNestedAbsolutePathMatchesRelativeClaim(): void
    {
        self::assertSame(0, $this->cli(['claim', '--agent=claude', '--files=not-created/subdir/new.php'])[0]);
        self::assertSame(2, $this->cli(['check', '--agent=codex', '--file=' . dirname(__DIR__) . '/not-created/subdir/new.php'])[0]);
    }

    public function testInvalidChannelShapeFailsClosed(): void
    {
        file_put_contents($this->channel, '{"claims":"invalid","messages":[]}');
        self::assertSame(2, $this->cli(['check', '--file=src/a.php', '--agent=codex'])[0]);
    }

    public function testFailedLockDoesNotPublishSuccess(): void
    {
        mkdir($this->channel . '.lock');
        try {
            [$code, $out] = $this->cli(['claim', '--agent=codex', '--files=src/a.php']);
            self::assertSame(1, $code);
            self::assertSame('', $out);
            self::assertFileDoesNotExist($this->channel);
        } finally {
            rmdir($this->channel . '.lock');
        }
    }

    public function testConcurrentClaimsHaveOnlyOneWinner(): void
    {
        $processes = [];
        foreach (['claude', 'codex'] as $agent) {
            $proc = proc_open([PHP_BINARY, $this->cli, 'claim', '--agent=' . $agent, '--files=src/race.php'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $processes[] = [$proc, $pipes];
        }
        $codes = [];
        foreach ($processes as [$proc, $pipes]) {
            stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $codes[] = proc_close($proc);
        }
        sort($codes);
        self::assertSame([0, 2], $codes);
        $state = json_decode(file_get_contents($this->channel), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $state['claims']);
    }

    public function testPanelDoesNotHideCorruptChannelAsEmptyState(): void
    {
        file_put_contents($this->channel, 'invalid json');
        $code = '$_SERVER["REQUEST_URI"]="/api/state"; $_SERVER["REQUEST_METHOD"]="GET"; require $argv[1]; echo "STATUS=".http_response_code();';
        $proc = proc_open([PHP_BINARY, '-r', $code, dirname(__DIR__) . '/bin/agent-coord-server.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($proc), $err);
        self::assertStringContainsString('"error":', $out);
        self::assertStringContainsString('STATUS=503', $out);
    }
}
