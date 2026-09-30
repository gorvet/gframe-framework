<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Media\MediaScopeResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MediaScopeResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        ConfigRepository::replace([]);
    }

    public function testGlobalScopeDoesNotRequireAnIdentity(): void
    {
        ConfigRepository::replace(['media' => ['scope' => 'global']]);

        $scope = (new MediaScopeResolver())->resolve([]);

        self::assertSame('global', $scope->type());
        self::assertNull($scope->id());
    }

    public function testUserScopeUsesTheNormalizedIdentity(): void
    {
        ConfigRepository::replace(['media' => ['scope' => 'user']]);

        $scope = (new MediaScopeResolver())->resolve(['auth' => ['id' => 27]]);

        self::assertSame('user', $scope->type());
        self::assertSame(27, $scope->id());
    }

    public function testTenantScopeUsesTheConfiguredSessionKey(): void
    {
        ConfigRepository::replace([
            'media' => ['scope' => 'tenant'],
            'tenancy' => ['key' => 'company_id'],
        ]);

        $scope = (new MediaScopeResolver())->resolve(['company_id' => 14]);

        self::assertSame('tenant', $scope->type());
        self::assertSame(14, $scope->id());
    }

    public function testScopedLibraryRejectsAMissingIdentity(): void
    {
        ConfigRepository::replace(['media' => ['scope' => 'user']]);

        $this->expectException(RuntimeException::class);
        (new MediaScopeResolver())->resolve([]);
    }
}
