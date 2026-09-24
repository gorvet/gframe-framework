<?php

namespace GFrame\Tests;

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
}
