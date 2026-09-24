<?php

trait HeartbeatChannelTrait {
    protected function hbInt(array $payload, string $key, int $default, int $min, int $max): int {
        $value = (int)($payload[$key] ?? $default);
        if ($value < $min) return $min;
        if ($value > $max) return $max;
        return $value;
    }

    protected function hbSuccess(array $data = [], array $extra = []): array {
        return array_merge([
            'status' => 'success',
            'data' => $data,
        ], $extra);
    }

    protected function hbError(string $code, string $message = ''): array {
        return [
            'status' => 'error',
            'code' => $code,
            'message' => $message,
        ];
    }
}

