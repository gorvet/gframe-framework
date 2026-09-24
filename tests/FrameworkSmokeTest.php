<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Config\Environment;
use GFrame\Foundation\Bootstrap;
use PHPUnit\Framework\TestCase;

final class FrameworkSmokeTest extends TestCase
{
    public function testCoreClassesAreAutoloadable(): void
    {
        self::assertTrue(class_exists(Bootstrap::class));
        self::assertTrue(class_exists(\RouteBuilder::class));
        self::assertTrue(class_exists(\DatabaseManager::class));
        self::assertTrue(class_exists(\Middleware::class));
        self::assertTrue(class_exists(\Render::class));
    }

    public function testExternalDependenciesAreManagedByComposer(): void
    {
        self::assertTrue(class_exists(\PHPMailer\PHPMailer\PHPMailer::class));
        self::assertTrue(class_exists(\Hhxsv5\SSE\SSE::class));
        self::assertTrue(class_exists(\Opis\Closure\SerializableClosure::class));
        self::assertTrue(class_exists(\Wamania\Snowball\StemmerFactory::class));
    }

    public function testClosuresCanBeSerializedForBackgroundJobs(): void
    {
        $serialized = serialize(new \Opis\Closure\SerializableClosure(
            static fn(int $value): int => $value * 2
        ));
        $wrapper = unserialize($serialized);

        self::assertInstanceOf(\Opis\Closure\SerializableClosure::class, $wrapper);
        self::assertSame(8, $wrapper->getClosure()(4));
    }

    public function testConfigurationSupportsNestedValuesAndOverrides(): void
    {
        ConfigRepository::replace([
            'app' => ['name' => 'GFrame', 'debug' => false, 'languages' => ['es', 'en']],
            'database' => ['default' => 'main'],
        ]);
        ConfigRepository::merge(['app' => ['debug' => true, 'languages' => ['es']]]);

        self::assertSame('GFrame', ConfigRepository::get('app.name'));
        self::assertTrue(ConfigRepository::get('app.debug'));
        self::assertSame(['es'], ConfigRepository::get('app.languages'));
        self::assertSame('fallback', ConfigRepository::get('app.missing', 'fallback'));
    }

    public function testEnvironmentParsesTypedValues(): void
    {
        $_ENV['GFRAME_TEST_BOOLEAN'] = 'false';
        $_ENV['GFRAME_TEST_INTEGER'] = '42';

        self::assertFalse(Environment::bool('GFRAME_TEST_BOOLEAN', true));
        self::assertSame(42, Environment::int('GFRAME_TEST_INTEGER'));

        unset($_ENV['GFRAME_TEST_BOOLEAN'], $_ENV['GFRAME_TEST_INTEGER']);
    }
}
