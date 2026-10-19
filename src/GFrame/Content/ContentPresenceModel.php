<?php

namespace GFrame\Content;

final class ContentPresenceModel extends \ORM
{
    protected $table = 'content_editing_presence';
    protected $primaryKey = 'presence_key';
    protected $fillable = ['presence_key', 'resource_key', 'expires_at'];

    public function touchPresence(string $key, string $resource, int $now, int $expires): int
    {
        $this->reset()->where('resource_key', '=', $resource)->where('expires_at', '<=', $now)->deleteWhere();
        $this->reset()->upsert(['presence_key' => $key], ['resource_key' => $resource, 'expires_at' => $expires]);
        return (int)$this->reset()->where('resource_key', '=', $resource)->where('presence_key', '!=', $key)->where('expires_at', '>', $now)->count();
    }
}
