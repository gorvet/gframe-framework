<?php

namespace GFrame\Tests;

use GFrame\Security\CsrfToken;
use GFrame\Session\SessionRuntime;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class VisitorCsrfTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testAnonymousSessionGetsStableFieldsWithoutLoginAndRotatesOnLogin(): void
    {
        ini_set('session.use_cookies', '0');
        SessionRuntime::start(sys_get_temp_dir());
        self::assertArrayNotHasKey('auth', $_SESSION);
        $token = $_SESSION['csrfToken'];
        $timestamp = $_SESSION['csrfTimestamp'];
        self::assertSame(64, strlen($token));
        self::assertSame('success', CsrfToken::validate($token, (string)$timestamp)['status']);
        self::assertStringContainsString('name="csrfToken" value="' . $token, CsrfToken::fields());
        SessionRuntime::start(sys_get_temp_dir());
        self::assertSame($token, $_SESSION['csrfToken']);
        (new \GFrame\Auth\SessionManager())->login(['id' => 7]);
        self::assertNotSame($token, $_SESSION['csrfToken']);
        self::assertSame('unauthorized', CsrfToken::validate($token, (string)$timestamp)['status']);
        session_destroy();
    }

    #[RunInSeparateProcess]
    public function testNoSessionDoesNotCreateOrExposeTokens(): void
    {
        $_SESSION = ['csrfToken' => 'stale', 'csrfTimestamp' => 1];
        self::assertSame('', CsrfToken::fields());
        CsrfToken::ensure();
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testInvalidMissingCrossSessionAndMalformedInputsAreRejected(): void
    {
        $previous = $_SESSION ?? null;
        try {
            $_SESSION = ['csrfToken' => 'visitor-token', 'csrfTimestamp' => 100];
            foreach ([[null, null], ['other-session', '100'], [[], '100'], ['visitor-token', []], ['visitor-token', '100x']] as [$token, $timestamp]) {
                self::assertSame('fail_csrf', CsrfToken::validate($token, $timestamp)['code']);
            }
            self::assertSame('to_reload', CsrfToken::validate('visitor-token', '99')['code']);
            $_SESSION = [];
            self::assertSame('fail_csrf', CsrfToken::validate('visitor-token', '100')['code']);
        } finally {
            if ($previous === null) unset($_SESSION); else $_SESSION = $previous;
        }
    }

    public function testOriginalPublicFormsSendTokensAndAuthRoutesDoNotExcludeValidation(): void
    {
        $path = dirname(__DIR__) . '/resources/modules/auth-ui/application/';
        foreach (['Login', 'Register', 'Recovery', 'Reset'] as $view) {
            self::assertStringContainsString('CsrfToken::fields()', file_get_contents($path . 'app/views/auth-ui/auth' . $view . '.php'));
        }
        self::assertStringNotContainsString("excludeMiddleware(['CSRF'])", file_get_contents($path . 'routes/routes_ajax_auth.php'));
    }
}
