<?php

/**
 * Helper estatico para escritura de logs planos.
 */
final class LogHelper
{
    private function __construct()
    {
    }

    /**
     * Escribe una entrada en /logs/<filename>.txt.
     *
     * @param mixed $data Data a serializar.
     * @param string $filename Nombre de archivo sin extension.
     * @return void
     */
    public static function write($data, string $filename = 'loggs'): void
    {
        $dir = defined('ABSPATH') ? (string)ABSPATH . '/logs' : __DIR__ . '/../../logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $safeName = preg_replace('/[^a-z0-9_\-\.]/i', '_', $filename);
        $file = rtrim($dir, '/\\') . '/' . $safeName . '.txt';
        $stamp = date('Y-m-d H:i:s');
        $text = is_string($data) ? $data : print_r($data, true);
        $line = "[$stamp]\n$text\n----\n";
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}

