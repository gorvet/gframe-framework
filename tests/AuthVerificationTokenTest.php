<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthModel;
use GFrame\Auth\TokenManager;
use PHPUnit\Framework\TestCase;

final class AuthVerificationTokenTest extends TestCase
{
    public function testFreshVerificationTokenActivatesAccount(): void
    {
        $model = new VerificationTokenAuthModel(
            new TokenManager(60),
            date('Y-m-d H:i:s')
        );

        $result = $model->validateAcount('verification-token');

        self::assertSame('account_verified', $result['code']);
        self::assertSame('verify', $model->user['status']);
        self::assertSame(1, $model->writes);
    }

    public function testExpiredVerificationTokenIsRejectedWithoutWriting(): void
    {
        $model = new VerificationTokenAuthModel(
            new TokenManager(60),
            date('Y-m-d H:i:s', time() - 120)
        );

        $result = $model->validateAcount('verification-token');

        self::assertSame(['status' => 'error', 'code' => 'invalid_token'], $result);
        self::assertSame('unverify', $model->user['status']);
        self::assertSame(0, $model->writes);
    }
}

final class VerificationTokenAuthModel extends AuthModel
{
    public array $user;
    public int $writes = 0;

    public function __construct(TokenManager $tokens, string $issuedAt)
    {
        parent::__construct(tokens: $tokens);
        $this->user = [
            'user_id' => 1,
            'status' => 'unverify',
            'token' => 'verification-token',
            'token_updated_at' => $issuedAt,
        ];
    }

    public function findByToken(string $token): ?array
    {
        return $token === $this->user['token'] ? $this->user : null;
    }

    public function updateAuthUser(int $userID, array $attributes): void
    {
        ++$this->writes;
        $this->user = array_replace($this->user, $attributes);
    }
}
