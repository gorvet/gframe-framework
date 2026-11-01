<?php

namespace GFrame\Media;

use Closure;
use GFrame\Config\ConfigRepository;

/** The HTTP boundary supplies the authenticated, scope-aware permission checker. */
final class MediaAccessPolicy
{
    private Closure $authorize;

    public function __construct(callable $authorize)
    {
        $this->authorize = Closure::fromCallable($authorize);
    }

    public function allows(string $source, string $action): bool
    {
        $prefix = (string)ConfigRepository::get('media.sources.' . $source . '.private_permission', 'media.private');
        return $prefix !== '' && ($this->authorize)($prefix . '.' . $action) === true;
    }

    public function filters(): array
    {
        $allowed = $denied = [];
        foreach ((array)ConfigRepository::get('media.sources', []) as $source => $settings) {
            if ($this->allows((string)$source, 'view')) $allowed[] = (string)$source;
            else $denied[] = (string)$source;
        }
        return ['private_default' => $this->allows('', 'view'), 'private_allowed_sources' => $allowed, 'private_denied_sources' => $denied];
    }
}
