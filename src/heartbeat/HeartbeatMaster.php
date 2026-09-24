<?php

class HeartbeatMaster {
    protected HeartbeatChannelRegistry $registry;
    private string $sessionLastRunKey = '__gf_heartbeat_last_run';
    protected int $baseBeatMs = 60000;

    public function __construct() {
        $this->registry = new HeartbeatChannelRegistry();
    }

    public function dispatch(): array {
        $context = $this->readClientContext();
        $channels = $this->registry->all();

        $out = [];
        foreach ($channels as $key => $definition) {
            if (!$this->isValidChannelKey($key)) {
                continue;
            }
            if (!$this->shouldRunChannel($key, $definition, $context)) {
                continue;
            }

            try {
                $payload = (array)($definition['payload'] ?? []);
                $out[$key] = $this->runChannel($definition['handler'] ?? null, $payload, $context);
                $this->markChannelRun($key);
            } catch (Exception $e) {
                $out[$key] = [
                    'status' => 'error',
                    'code' => (string)$e->getCode(),
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'status' => 'success',
            'data' => [
                'channels' => $out,
            ],
        ];
    }

    protected function registerChannel(string $channel, array $options, $handler): void {
        $interval = max($this->baseBeatMs, (int)($options['interval_ms'] ?? $this->baseBeatMs));
        $runWhenHidden = !empty($options['run_when_hidden']);
        $payload = is_array($options['payload'] ?? null) ? (array)$options['payload'] : [];
        $this->registry->register($channel, $handler, [
            'interval_ms' => $interval,
            'run_when_hidden' => $runWhenHidden,
            'payload' => $payload,
        ]);
    }

    protected function isValidChannelKey(string $key): bool {
        return (bool)preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $key);
    }

    protected function readClientContext(): array {
        $visible = (int)($_POST['hb_visible'] ?? 1) === 1;
        $force = (int)($_POST['hb_force'] ?? 0) === 1;
        return [
            'visible' => $visible,
            'force' => $force,
            'session' => $_SESSION ?? [],
        ];
    }

    /** @param array{interval_ms:int,run_when_hidden:bool,payload:array} $definition */
    protected function shouldRunChannel(string $channel, array $definition, array $context): bool {
        if (empty($context['visible']) && empty($definition['run_when_hidden'])) {
            return false;
        }

        if (!empty($context['force'])) {
            return true;
        }

        $lastRunMs = $this->getLastRunForChannel($channel);
        if ($lastRunMs <= 0) {
            return true;
        }
        $elapsed = (int)floor(microtime(true) * 1000) - $lastRunMs;
        return $elapsed >= max($this->baseBeatMs, (int)($definition['interval_ms'] ?? $this->baseBeatMs));
    }

    protected function getLastRunForChannel(string $channel): int {
        $bucket = $_SESSION[$this->sessionLastRunKey] ?? [];
        if (!is_array($bucket)) {
            return 0;
        }
        return (int)($bucket[$channel] ?? 0);
    }

    protected function markChannelRun(string $channel): void {
        if (!isset($_SESSION[$this->sessionLastRunKey]) || !is_array($_SESSION[$this->sessionLastRunKey])) {
            $_SESSION[$this->sessionLastRunKey] = [];
        }
        $_SESSION[$this->sessionLastRunKey][$channel] = (int)floor(microtime(true) * 1000);
    }

    protected function runChannel($handler, array $payload, array $context): array {
        if (is_callable($handler)) {
            $result = $handler($payload, $context);
            return is_array($result) ? $result : ['status' => 'success', 'data' => $result];
        }

        if (!is_string($handler) || strpos($handler, '@') === false) {
            return ['status' => 'error', 'code' => 'bad_handler', 'message' => 'Handler invalido'];
        }

        [$controller, $method] = explode('@', $handler, 2);
        $controller = trim((string)$controller);
        $method = trim((string)$method);
        if ($controller === '' || $method === '') {
            return ['status' => 'error', 'code' => 'bad_handler', 'message' => 'Handler invalido'];
        }

        $path = realpath(ABSPATH . 'app/controllers/' . str_replace('\\', '/', $controller) . '.php');
        if ($path === false || !file_exists($path)) {
            return ['status' => 'error', 'code' => 'bad_controller', 'message' => 'Controlador no encontrado'];
        }
        require_once $path;

        $parts = explode('/', str_replace('\\', '/', $controller));
        $className = end($parts);
        if (!class_exists($className)) {
            return ['status' => 'error', 'code' => 'missing_class', 'message' => 'Clase no encontrada'];
        }

        $instance = new $className();
        if (!method_exists($instance, $method) || !is_callable([$instance, $method])) {
            return ['status' => 'error', 'code' => 'bad_method', 'message' => 'Metodo no disponible'];
        }

        $result = $instance->$method($payload, $context);
        return is_array($result) ? $result : ['status' => 'success', 'data' => $result];
    }
}
