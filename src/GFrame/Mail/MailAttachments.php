<?php

namespace GFrame\Mail;

use InvalidArgumentException;
use PHPMailer\PHPMailer\PHPMailer;

/** Copias de adjuntos locales o generados, independientes del archivo original. */
final class MailAttachments
{
    public const MAX_COUNT = 10;
    public const MAX_BYTES = 10485760;

    public static function prepare(mixed $attachments): array
    {
        if (!is_array($attachments) || !array_is_list($attachments) || count($attachments) > self::MAX_COUNT) {
            throw new InvalidArgumentException('Lista de adjuntos inválida.');
        }
        $prepared = [];
        $total = 0;
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) throw new InvalidArgumentException('Adjunto inválido.');
            $hasPath = array_key_exists('path', $attachment);
            if ($hasPath === array_key_exists('content', $attachment)) throw new InvalidArgumentException('Origen de adjunto inválido.');
            $name = $attachment['name'] ?? ($hasPath && is_string($attachment['path']) ? basename($attachment['path']) : null);
            $type = $attachment['type'] ?? 'application/octet-stream';
            if (!is_string($name) || $name === '' || strlen($name) > 255 || preg_match('~[\x00-\x1f\x7f/\\\\]~', $name)
                || !is_string($type) || !preg_match('~^[a-zA-Z0-9!#$&^_.+-]+/[a-zA-Z0-9!#$&^_.+-]+$~D', $type)) {
                throw new InvalidArgumentException('Nombre o tipo de adjunto inválido.');
            }
            if ($hasPath) {
                $path = $attachment['path'];
                if (!is_string($path) || $path === '' || str_contains($path, "\0") || str_contains($path, '://')
                    || !is_file($path) || !is_readable($path)) throw new InvalidArgumentException('Archivo adjunto no disponible.');
                // Lectura acotada incluso si el archivo crece entre la validación y la lectura.
                $content = @file_get_contents($path, false, null, 0, self::MAX_BYTES - $total + 1);
                if ($content === false) throw new InvalidArgumentException('Archivo adjunto no disponible.');
            } else {
                $content = $attachment['content'];
                if (!is_string($content)) throw new InvalidArgumentException('Contenido de adjunto inválido.');
            }
            $total += strlen($content);
            if ($total > self::MAX_BYTES) throw new InvalidArgumentException('Los adjuntos superan el límite.');
            $prepared[] = ['name' => $name, 'type' => $type, 'content' => $content];
        }
        return $prepared;
    }

    public static function apply(PHPMailer $mailer, array $prepared): void
    {
        foreach ($prepared as $attachment) {
            $mailer->addStringAttachment($attachment['content'], $attachment['name'], PHPMailer::ENCODING_BASE64, $attachment['type']);
        }
    }
}
