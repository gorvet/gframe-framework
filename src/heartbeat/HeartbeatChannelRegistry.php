<?php

class HeartbeatChannelRegistry {
    /** @var array<string, array{handler: mixed, interval_ms: int, run_when_hidden: bool, payload: array}> */
    private array $channels = [];

    public function register(string $channel, $handler, array $options = []): void {
        $key = trim($channel);
        if ($key === '') {
            return;
        }
        $this->channels[$key] = [
            'handler' => $handler,
            'interval_ms' => max(1000, (int)($options['interval_ms'] ?? 60000)),
            'run_when_hidden' => !empty($options['run_when_hidden']),
            'payload' => is_array($options['payload'] ?? null) ? (array)$options['payload'] : [],
        ];
    }

    public function has(string $channel): bool {
        return isset($this->channels[$channel]);
    }

    /** @return array<string, array{handler: mixed, interval_ms: int, run_when_hidden: bool, payload: array}> */
    public function all(): array {
        return $this->channels;
    }

    /** @return array{handler: mixed, interval_ms: int, run_when_hidden: bool, payload: array}|null */
    public function get(string $channel): ?array {
        return $this->channels[$channel] ?? null;
    }
}
