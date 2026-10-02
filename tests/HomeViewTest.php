<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class HomeViewTest extends TestCase
{
    public function testHomeSharesTheViewportWithTheHeaderAndCommonFooter(): void
    {
        $root = dirname(__DIR__) . '/resources/skeleton/';
        $view = (string)file_get_contents($root . 'app/views/home/homeIndex.php');
        $css = (string)file_get_contents($root . 'public/css/home/home.css');
        self::assertStringContainsString('aria-labelledby="homeTitle"', $view);
        self::assertStringContainsString('Algo maravilloso se construye aquí.', $view);
        self::assertStringNotContainsString('fixed-top', $view);
        self::assertStringContainsString('min-height: 100svh;', $css);
        self::assertStringContainsString('flex: 1 1 auto;', $css);
        self::assertStringContainsString('min-height: 0;', $css);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $css);
        self::assertStringNotContainsString('overflow: hidden', $css);
        self::assertStringNotContainsString('.footer-credits', $css);
        self::assertStringNotContainsString('display: grid', $css);
    }
}
