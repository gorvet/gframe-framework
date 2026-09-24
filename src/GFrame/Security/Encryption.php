<?php

namespace GFrame\Security;

use InvalidArgumentException;
use RuntimeException;

final class Encryption
{
    private const CIPHER = 'aes-256-gcm';
    private const VERSION = 1;

    private string $key;

    public function __construct(string $key)
    {
        if (trim($key) === '') {
            throw new InvalidArgumentException('La clave de cifrado no puede estar vacía.');
        }

        $this->key = hash('sha256', $key, true);
    }

    public function encrypt(string $plainText): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength === false || $ivLength <= 0) {
            throw new RuntimeException('El cifrado requerido no está disponible.');
        }

        $iv = random_bytes($ivLength);
        $tag = '';
        $cipherText = openssl_encrypt(
            $plainText,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($cipherText === false || $tag === '') {
            throw new RuntimeException('No se pudo cifrar el contenido.');
        }

        $payload = json_encode([
            'v' => self::VERSION,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipherText),
        ], JSON_UNESCAPED_SLASHES);

        if (!is_string($payload)) {
            throw new RuntimeException('No se pudo serializar el contenido cifrado.');
        }

        return base64_encode($payload);
    }

    public function decrypt(string $payload): string
    {
        $json = base64_decode($payload, true);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($data) || (int)($data['v'] ?? 0) !== self::VERSION) {
            throw new InvalidArgumentException('El contenido cifrado no es válido.');
        }

        $iv = base64_decode((string)($data['iv'] ?? ''), true);
        $tag = base64_decode((string)($data['tag'] ?? ''), true);
        $cipherText = base64_decode((string)($data['data'] ?? ''), true);
        if (!is_string($iv) || !is_string($tag) || !is_string($cipherText)) {
            throw new InvalidArgumentException('El contenido cifrado no es válido.');
        }

        $plainText = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plainText === false) {
            throw new RuntimeException('No se pudo descifrar el contenido.');
        }

        return $plainText;
    }
}
