<?php

/**
 * Helper estatico para manejo global de excepciones y errores fatales.
 *
 * Objetivos:
 * - Capturar excepciones no controladas y errores fatales.
 * - Responder segun el canal solicitado (web/ajax/api/webhook/sse).
 * - En DebugMode mostrar una vista tecnica legible para desarrollo.
 */
final class ErrorHandler
{
    /** @var bool Evita registrar handlers mas de una vez. */
    private static bool $registered = false;

    /** @var bool Evita recursion si el propio handler falla. */
    private static bool $handling = false;

    /** @var object|null Render u otro objeto con errorControl(). */
    private static ?object $renderController = null;

    private function __construct()
    {
    }

    /**
     * Registra handlers globales de excepcion, error y shutdown fatal.
     *
     * @param object|null $renderController Instancia opcional con errorControl().
     * @return void
     */
    public static function register(?object $renderController = null): void
    {
        if (self::$registered) {
            if ($renderController !== null) {
                self::$renderController = $renderController;
            }
            return;
        }

        self::$registered = true;
        self::$renderController = $renderController;

        set_exception_handler(static function (\Throwable $exception): void {
            self::handleThrowable($exception);
        });

        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            // Convert only recoverable/error-level signals to exceptions.
            // Warnings/notices keep default PHP behavior to avoid regressions.
            $convertible = [E_USER_ERROR, E_RECOVERABLE_ERROR];
            if (!in_array($severity, $convertible, true)) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(static function (): void {
            $lastError = error_get_last();
            if (!is_array($lastError)) {
                return;
            }

            $fatalTypes = [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR,
                E_USER_ERROR,
                E_RECOVERABLE_ERROR,
            ];

            $type = (int)($lastError['type'] ?? 0);
            if (!in_array($type, $fatalTypes, true)) {
                return;
            }

            $message = (string)($lastError['message'] ?? 'Fatal error');
            $file = (string)($lastError['file'] ?? '');
            $line = (int)($lastError['line'] ?? 0);

            $fatal = new \ErrorException($message, 0, $type, $file, $line);
            self::handleThrowable($fatal);
        });
    }

    /**
     * Actualiza la instancia del renderer para fallback web en produccion.
     *
     * @param object|null $renderController Instancia con errorControl().
     * @return void
     */
    public static function setRender(?object $renderController): void
    {
        self::$renderController = $renderController;
    }

    /**
     * Manejo central de cualquier Throwable no capturado.
     *
     * @param \Throwable $exception Excepcion a procesar.
     * @return void
     */
    private static function handleThrowable(\Throwable $exception): void
    {
        if (self::$handling) {
            self::renderEmergencyFallback($exception);
            exit();
        }

        self::$handling = true;
        self::clearOutputBuffers();

        $channel = self::detectChannel();

        if ($channel !== 'web') {
            self::respondForNonWeb($channel, $exception);
            exit();
        }

        if (self::isDebugEnabled()) {
            self::renderDebugPage($exception);
            exit();
        }

        self::renderProductionWebError();
        exit();
    }

    /**
     * Retorna true cuando DebugMode esta activo.
     *
     * @return bool
     */
    private static function isDebugEnabled(): bool
    {
        return defined('DebugMode') && DebugMode === true;
    }

    /**
     * Limpia buffers abiertos para evitar mezclar salida parcial con errores.
     *
     * @return void
     */
    private static function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }

    /**
     * Detecta canal de la request a partir del path actual.
     *
     * @return string web|ajax|api|webhook|sse|system
     */
    private static function detectChannel(): string
    {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string)parse_url($requestUri, PHP_URL_PATH);
        $base = dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $relative = ltrim((string)substr($path, strlen((string)$base)), '/');

        $segments = $relative === '' ? [] : explode('/', $relative);
        $first = strtolower((string)($segments[0] ?? ''));

        if (defined('SUPPORTED_LANGS') && in_array($first, (array)SUPPORTED_LANGS, true)) {
            array_shift($segments);
            $first = strtolower((string)($segments[0] ?? ''));
        }

        return in_array($first, ['ajax', 'api', 'webhook', 'sse', 'system'], true) ? $first : 'web';
    }

    /**
     * Respuesta de error para canales no-web.
     *
     * @param string $channel Canal detectado.
     * @param \Throwable $exception Excepcion capturada.
     * @return void
     */
    private static function respondForNonWeb(string $channel, \Throwable $exception): void
    {
        $message = self::isDebugEnabled()
            ? self::buildCompactExceptionMessage($exception)
            : 'Internal server error';

        switch ($channel) {
            case 'ajax':
            case 'system':
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(200);
                }
                echo self::safeJsonEncode([
                    'status' => 'error',
                    'code' => 'internal_error',
                    'message' => $message,
                ]);
                return;

            case 'api':
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(500);
                }
                echo self::safeJsonEncode([
                    'status' => 'error',
                    'code' => 'internal_error',
                    'message' => $message,
                    'http_code' => 500,
                ]);
                return;

            case 'webhook':
                if (!headers_sent()) {
                    header('Content-Type: text/plain; charset=utf-8');
                    http_response_code(500);
                }
                echo $message;
                return;

            case 'sse':
                if (!headers_sent()) {
                    header('Content-Type: text/event-stream; charset=utf-8');
                    header('Cache-Control: no-cache, no-transform');
                    header('X-Accel-Buffering: no');
                    http_response_code(500);
                }
                echo "event: error\n";
                echo 'data: ' . self::safeJsonEncode(['message' => $message]) . "\n\n";
                return;

            default:
                if (!headers_sent()) {
                    header('Content-Type: text/plain; charset=utf-8');
                    http_response_code(500);
                }
                echo $message;
                return;
        }
    }

    /**
     * Render web de produccion usando ErrorResponder si hay renderer disponible.
     *
     * @return void
     */
    private static function renderProductionWebError(): void
    {
        if (is_object(self::$renderController) && method_exists(self::$renderController, 'errorControl')) {
            try {
                self::$renderController->errorControl('internal_error');
                return;
            } catch (\Throwable $ignored) {
                // fallback minimal
            }
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!doctype html><html><head><meta charset="utf-8"><title>500</title></head><body>';
        echo '<h1>500 - Internal Server Error</h1>';
        echo '<p>Something went wrong.</p>';
        echo '</body></html>';
    }

    /**
     * Render debug tecnico para web con estilo legible.
     *
     * @param \Throwable $exception Excepcion capturada.
     * @return void
     */
    private static function renderDebugPage(\Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        $title = htmlspecialchars((string)get_class($exception), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars((string)$exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars((string)$exception->getFile(), ENT_QUOTES, 'UTF-8');
        $line = (int)$exception->getLine();
        $trace = htmlspecialchars((string)$exception->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        $requestMethod = htmlspecialchars((string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'), ENT_QUOTES, 'UTF-8');
        $requestUri = htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8');
        $timestamp = htmlspecialchars((string)date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8');
        $snippet = htmlspecialchars(self::buildSourceSnippet($exception->getFile(), $line), ENT_QUOTES, 'UTF-8');

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>Application Error</title>';
        echo '<style>';
        echo 'body{margin:0;padding:28px;font-family:Segoe UI,Arial,sans-serif;background:#f3f4f6;color:#111827;}';
        echo '.wrap{max-width:1100px;margin:0 auto;}';
        echo '.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.07);}';
        echo '.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-weight:700;font-size:12px;}';
        echo 'h1{margin:14px 0 6px;font-size:28px;line-height:1.2;}';
        echo '.muted{color:#4b5563;font-size:14px;margin-bottom:18px;}';
        echo '.grid{display:grid;grid-template-columns:160px 1fr;gap:8px 14px;font-size:14px;margin-bottom:16px;}';
        echo '.grid b{color:#374151;}';
        echo 'pre{margin:0;background:#0f172a;color:#e5e7eb;padding:14px;border-radius:10px;overflow:auto;font-size:13px;line-height:1.55;}';
        echo '.section{margin-top:16px;}';
        echo '.section h2{font-size:15px;margin:0 0 8px;color:#111827;}';
        echo '</style></head><body>';
        echo '<div class="wrap"><div class="card">';
        echo '<span class="badge">DebugMode enabled</span>';
        echo '<h1>Unhandled exception</h1>';
        echo '<p class="muted">Technical details are visible because DebugMode is active.</p>';
        echo '<div class="grid">';
        echo '<b>Type</b><span>' . $title . '</span>';
        echo '<b>Message</b><span>' . $message . '</span>';
        echo '<b>File</b><span>' . $file . '</span>';
        echo '<b>Line</b><span>' . $line . '</span>';
        echo '<b>Request</b><span>' . $requestMethod . ' ' . $requestUri . '</span>';
        echo '<b>Timestamp</b><span>' . $timestamp . '</span>';
        echo '</div>';

        if ($snippet !== '') {
            echo '<div class="section"><h2>Source snippet</h2><pre>' . $snippet . '</pre></div>';
        }

        echo '<div class="section"><h2>Stack trace</h2><pre>' . $trace . '</pre></div>';
        echo '</div></div></body></html>';
    }

    /**
     * Fallback de emergencia para evitar pantalla en blanco total.
     *
     * @param \Throwable $exception Excepcion original.
     * @return void
     */
    private static function renderEmergencyFallback(\Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }

        if (self::isDebugEnabled()) {
            echo self::buildCompactExceptionMessage($exception);
            return;
        }

        echo 'Internal server error';
    }

    /**
     * Crea un texto corto para debug en canales no-web.
     *
     * @param \Throwable $exception Excepcion capturada.
     * @return string Mensaje compacto.
     */
    private static function buildCompactExceptionMessage(\Throwable $exception): string
    {
        return sprintf(
            '%s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
    }

    /**
     * Genera snippet de codigo alrededor de una linea.
     *
     * @param string $file Ruta del archivo.
     * @param int $line Linea del error.
     * @param int $padding Lineas antes y despues.
     * @return string Snippet con numeracion.
     */
    private static function buildSourceSnippet(string $file, int $line, int $padding = 6): string
    {
        if ($file === '' || !is_file($file)) {
            return '';
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines) || empty($lines)) {
            return '';
        }

        $total = count($lines);
        $line = max(1, min($total, $line));
        $start = max(1, $line - $padding);
        $end = min($total, $line + $padding);

        $chunk = [];
        for ($index = $start; $index <= $end; $index++) {
            $marker = $index === $line ? '>>' : '  ';
            $text = (string)($lines[$index - 1] ?? '');
            $chunk[] = sprintf('%s %5d | %s', $marker, $index, rtrim($text));
        }

        return implode("\n", $chunk);
    }

    /**
     * JSON encode seguro para evitar warnings por UTF-8 invalido.
     *
     * @param mixed $value Valor a serializar.
     * @return string JSON valido.
     */
    private static function safeJsonEncode($value): string
    {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return is_string($json) ? $json : '{}';
    }
}
