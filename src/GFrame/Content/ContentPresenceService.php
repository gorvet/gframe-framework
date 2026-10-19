<?php

namespace GFrame\Content;

use InvalidArgumentException;

final class ContentPresenceService
{
    public const LIFETIME = 90;

    public function __construct(private readonly ContentPresenceModel $model = new ContentPresenceModel()) {}

    public function touch(int $userID, ?int $tenantID, string $resource, string $editorToken, ?int $now = null): array
    {
        if ($userID <= 0 || ($tenantID !== null && $tenantID <= 0) || $resource === '' || strlen($resource) > 512 || !preg_match('/^[a-f0-9]{32}$/D', $editorToken)) {
            throw new InvalidArgumentException('Identidad de presencia no válida.');
        }
        $now ??= time();
        $resourceKey = hash('sha256', json_encode([$tenantID, $resource], JSON_THROW_ON_ERROR));
        $key = hash('sha256', json_encode([$resourceKey, $userID, $editorToken], JSON_THROW_ON_ERROR));
        $count = $this->model->touchPresence($key, $resourceKey, $now, $now + self::LIFETIME);
        return ['status' => 'success', 'code' => 'content_presence', 'message' => $count === 0 ? '' : ($count === 1 ? 'Hay otra sesión editando este contenido.' : 'Hay ' . $count . ' sesiones más editando este contenido.'), 'data' => ['other_sessions' => $count]];
    }
}
