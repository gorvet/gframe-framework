<?php

namespace GFrame\Tests;

use GFrame\Media\MediaProcessor;
use PHPUnit\Framework\TestCase;

final class ConfigurationDefaultsTest extends TestCase
{
    public function testMediaUploadLimitHasSingleConfigurationSource(): void
    {
        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $mediaConfig = require dirname(__DIR__) . '/resources/modules/media-library/config/media.php';

        self::assertArrayHasKey('media', $defaults);
        self::assertArrayNotHasKey('max_upload_bytes', $defaults['media']);
        self::assertArrayHasKey('max_upload_bytes', $mediaConfig);
        self::assertSame((int)$mediaConfig['max_upload_bytes'], (new MediaProcessor())->getMaxUploadBytes());
    }
}
