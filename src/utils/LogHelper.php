<?php

/**
 * Helper estatico para escritura de logs.
 *
 * Conserva la escritura plana histórica mediante write() y añade registro por
 * niveles con contexto estructurado, rotación por tamaño y redacción de claves
 * sensibles. Los niveles disponibles, de menor a mayor severidad, son debug,
 * info, warning y error.
 *
 * Overrides opcionales de configuración (todos con valor por defecto en código,
 * así que funcionan aunque la configuración no esté cargada):
 * - logging.level      Umbral mínimo a registrar en los métodos por nivel. Por
 *                      defecto "debug" (registra todo). write() no se filtra.
 * - logging.max_bytes  Tamaño máximo del archivo antes de rotar. Por defecto
 *                      5.000.000; 0 desactiva la rotación.
 */
final class LogHelper
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    /** @var array<string,int> Severidad relativa de cada nivel. */
    private const LEVELS = [
        self::DEBUG => 10,
        self::INFO => 20,
        self::WARNING => 30,
        self::ERROR => 40,
    ];

    /** Claves cuyo valor se oculta al registrar contexto. */
    private const SENSITIVE_PATTERN = '/pass|token|secret|authorization|cookie|csrf|api[_-]?key|credential/i';

    private function __construct()
    {
    }

    /**
     * Escribe una entrada plana en /logs/<filename>.txt (compatibilidad histórica).
     *
     * @param mixed $data Data a serializar.
     * @param string $filename Nombre de archivo sin extension.
     */
    public static function write($data, string $filename = 'loggs'): void
    {
        $text = is_string($data) ? $data : print_r($data, true);
        self::append($filename, '[' . self::stamp() . "]\n" . $text . "\n----\n");
    }

    public static function debug(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log(self::DEBUG, $message, $context, $channel);
    }

    public static function info(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log(self::INFO, $message, $context, $channel);
    }

    public static function warning(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log(self::WARNING, $message, $context, $channel);
    }

    public static function error(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log(self::ERROR, $message, $context, $channel);
    }

    /**
     * Registra un mensaje con nivel y contexto si alcanza el umbral configurado.
     *
     * @param array<string,mixed> $context
     */
    public static function log(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        $level = strtolower($level);
        if (!isset(self::LEVELS[$level])) {
            $level = self::INFO;
        }
        if (self::LEVELS[$level] < self::thresholdLevel()) {
            return;
        }

        $line = '[' . self::stamp() . '] ' . strtoupper($level) . ' ' . trim($message);
        if ($context !== []) {
            $encoded = json_encode(
                self::redact($context),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
            );
            if (is_string($encoded)) {
                $line .= ' ' . $encoded;
            }
        }

        self::append($channel, $line . "\n");
    }

    private static function stamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    private static function thresholdLevel(): int
    {
        $configured = self::DEBUG;
        if (class_exists(\GFrame\Config\ConfigRepository::class)) {
            $configured = strtolower((string)\GFrame\Config\ConfigRepository::get('logging.level', self::DEBUG));
        }
        return self::LEVELS[$configured] ?? self::LEVELS[self::DEBUG];
    }

    private static function maxBytes(): int
    {
        if (class_exists(\GFrame\Config\ConfigRepository::class)) {
            return max(0, (int)\GFrame\Config\ConfigRepository::get('logging.max_bytes', 5000000));
        }
        return 5000000;
    }

    /**
     * Sustituye por "[redacted]" los valores cuyas claves parezcan sensibles,
     * recorriendo también el interior de los objetos (que de otro modo
     * json_encode serializaría con sus secretos visibles).
     */
    private static function redact(mixed $value): mixed
    {
        if (is_object($value)) {
            $decoded = json_decode((string)json_encode($value), true);
            if (!is_array($decoded)) {
                return '[object ' . get_class($value) . ']';
            }
            $value = $decoded;
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match(self::SENSITIVE_PATTERN, $key) === 1) {
                $value[$key] = '[redacted]';
                continue;
            }
            $value[$key] = self::redact($item);
        }

        return $value;
    }

    private static function append(string $filename, string $line): void
    {
        $dir = defined('ABSPATH') ? (string)ABSPATH . '/logs' : __DIR__ . '/../../logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $safeName = preg_replace('/[^a-z0-9_\-\.]/i', '_', $filename);
        if ($safeName === '' || $safeName === null) {
            $safeName = 'loggs';
        }
        $file = rtrim($dir, '/\\') . '/' . $safeName . '.txt';

        self::rotateIfNeeded($file, strlen($line));
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private static function rotateIfNeeded(string $file, int $incoming): void
    {
        $max = self::maxBytes();
        if ($max <= 0 || !is_file($file)) {
            return;
        }
        $size = @filesize($file);
        if ($size === false || ($size + $incoming) <= $max) {
            return;
        }
        // Rotación simple: conserva una copia anterior en <archivo>.1.
        $backup = $file . '.1';
        if (is_file($backup)) {
            @unlink($backup);
        }
        @rename($file, $backup);
    }
}
