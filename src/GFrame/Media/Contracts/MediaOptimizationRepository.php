<?php

namespace GFrame\Media\Contracts;

use GFrame\Media\MediaScope;

/** Optional atomic write capability; existing MediaRepository implementations remain compatible. */
interface MediaOptimizationRepository extends MediaRepository
{
    public function saveOptimization(int $mediaID, MediaScope $scope, array $expected, array $replacement): bool;
}
