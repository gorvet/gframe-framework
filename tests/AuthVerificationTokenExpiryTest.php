<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthModel;
use GFrame\Auth\TokenManager;
use PHPUnit\Framework\TestCase;

final class AuthVerificationTokenExpiryTest extends TestCase
{
    public function testExpiredVerificationTokenIsRejectedWithoutUpdatingTheAccount(): void
    {
        $model = new VerificationTokenAuthModel(
            date('Y-m-d H:i:s', strtotime('-2 hours')),
            new TokenManager(3600)
        );

        $result = $model->validateAcount('expired-token');

        self::assertSame(['status' => 'error', 'code' => 'invalid_token'], $result);
        self::assertFalse($model->updated);
    }

    public function testFreshVerificationTokenStillActivatesTheAccount(): void
    {
        $model = new VerificationTokenAuthModel(
            date('Y-m-d H:i:s', strtotime('-10 minutes')),
            new TokenManager(3600)
        );

        $result = $model->validateAcount('fresh-token');

        self::assertSame('success', $result['status']);
        self::assertSame('account_verified', $result['code']);
        self::assertTrue($model->updated);
        self::assertSame('verify', $model->lastUpdate['status'] ?? null);
    }
}

final class VerificationTokenAuthModel extends AuthModel
{
    public bool $updated = false;
    public array $lastUpdate = [];

    public function __construct(
        private readonly string $tokenUpdatedAt,
        TokenManager $tokens
    ) {
        parent::__construct(tokens: $tokens);
    }

    public function findByToken(string $token): ?array
    {
        return [
            'user_id' => 1,
            'status' => 'unverify',
            'token_updated_at' => $this->tokenUpdatedAt,
        ];
    }

    public function updateAuthUser(int $userID, array $attributes): void
    {
        $this->updated = true;
        $this->lastUpdate = $attributes;
    }
}
