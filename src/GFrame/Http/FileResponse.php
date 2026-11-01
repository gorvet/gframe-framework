<?php

namespace GFrame\Http;

use RuntimeException;

/** A prepared, already-authorized local file response. Never redirects to a physical URL. */
final class FileResponse
{
    private int $start = 0;
    private int $length;
    private int $status = 200;
    private array $headers;

    public function __construct(private readonly string $path, string $name, string $mime = 'application/octet-stream', bool $inline = false)
    {
        if (!is_file($path) || !is_readable($path)) throw new RuntimeException('El archivo no está disponible.');
        $this->length = (int)filesize($path);
        $name = basename(str_replace('\\', '/', preg_replace('/[\x00-\x1f\x7f]/', '', $name) ?? 'archivo'));
        $mime = preg_match('~^[a-z0-9.+-]+/[a-z0-9.+-]+$~i', $mime) ? $mime : 'application/octet-stream';
        // Only passive formats may be displayed in the application's origin.
        $inline = $inline && (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true)
            || str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/'));
        $this->headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="archivo"; filename*=UTF-8\'\'' . rawurlencode($name),
            'Content-Length' => (string)$this->length,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ];
    }

    public function range(?string $range): self
    {
        if ($range === null || $range === '') return $this;
        $size = (int)filesize($this->path);
        if (!preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $match) || ($match[1] === '' && $match[2] === '') || $size === 0) {
            return $this->unsatisfiable($size);
        }
        $start = $match[1] === '' ? max(0, $size - (int)$match[2]) : (int)$match[1];
        $end = $match[1] === '' || $match[2] === '' ? $size - 1 : min($size - 1, (int)$match[2]);
        if ($start >= $size || $end < $start) return $this->unsatisfiable($size);
        $this->status = 206;
        $this->start = $start;
        $this->length = $end - $start + 1;
        $this->headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        $this->headers['Content-Length'] = (string)$this->length;
        return $this;
    }

    public function status(): int { return $this->status; }
    public function headers(): array { return $this->headers; }

    public function send(string $method = 'GET'): never
    {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD');
            exit;
        }
        $stream = fopen($this->path, 'rb');
        if ($stream === false) throw new RuntimeException('No se pudo abrir el archivo.');
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) header($name . ': ' . $value);
        if ($method === 'GET' && $this->status !== 416) {
            fseek($stream, $this->start);
            $remaining = $this->length;
            while ($remaining > 0 && !feof($stream) && !connection_aborted()) {
                $chunk = fread($stream, min(65536, $remaining));
                if ($chunk === false || $chunk === '') break;
                echo $chunk;
                $remaining -= strlen($chunk);
            }
        }
        fclose($stream);
        exit;
    }

    private function unsatisfiable(int $size): self
    {
        $this->status = 416;
        $this->headers['Content-Range'] = 'bytes */' . $size;
        $this->headers['Content-Length'] = '0';
        return $this;
    }
}
