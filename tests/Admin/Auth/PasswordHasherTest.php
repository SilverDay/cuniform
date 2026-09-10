<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testVerifyAcceptsTheOriginalPassword(): void
    {
        $hasher = new PasswordHasher();

        self::assertTrue($hasher->verify('correct horse battery staple', $hasher->hash('correct horse battery staple')));
    }

    public function testVerifyRejectsAWrongPassword(): void
    {
        $hasher = new PasswordHasher();

        self::assertFalse($hasher->verify('wrong password', $hasher->hash('correct horse battery staple')));
    }

    public function testHashIsArgon2idWithAtLeast64MebibyteMemoryCost(): void
    {
        $hasher = new PasswordHasher();
        $info   = password_get_info($hasher->hash('correct horse battery staple'));

        self::assertSame(PASSWORD_ARGON2ID, $info['algo']);
        self::assertGreaterThanOrEqual(65536, $info['options']['memory_cost']);
    }

    public function testNeedsRehashIsFalseForAFreshHash(): void
    {
        $hasher = new PasswordHasher();

        self::assertFalse($hasher->needsRehash($hasher->hash('correct horse battery staple')));
    }

    public function testNeedsRehashIsTrueForAWeakerHash(): void
    {
        $hasher = new PasswordHasher();
        $weak   = password_hash('correct horse battery staple', PASSWORD_ARGON2ID, ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);

        self::assertTrue($hasher->needsRehash($weak));
    }
}
