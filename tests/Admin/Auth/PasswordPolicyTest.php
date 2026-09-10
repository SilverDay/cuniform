<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\BreachedPasswordChecker;
use Cuniform\Admin\Auth\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testRejectsAPasswordShorterThanTwelveCharacters(): void
    {
        $errors = (new PasswordPolicy($this->neverBreached()))->validate('short11'); // 7 chars

        self::assertNotSame([], $errors);
    }

    public function testAcceptsATwelveCharacterAllLowercasePassphraseWithNoDigitsOrSymbols(): void
    {
        // Proves "no composition rules" (SPEC §13.1) by absence: a plain
        // lowercase phrase with no digit/symbol/uppercase must still pass.
        self::assertSame([], (new PasswordPolicy($this->neverBreached()))->validate('correcthorse'));
    }

    public function testRejectsABreachedPassword(): void
    {
        $errors = (new PasswordPolicy($this->alwaysBreached()))->validate('anything-long-enough');

        self::assertNotSame([], $errors);
    }

    public function testAcceptsAPasswordExactlyTwelveCharactersLong(): void
    {
        self::assertSame([], (new PasswordPolicy($this->neverBreached()))->validate('123456789012'));
    }

    public function testRejectsAPasswordElevenCharactersLong(): void
    {
        self::assertNotSame([], (new PasswordPolicy($this->neverBreached()))->validate('12345678901'));
    }

    private function neverBreached(): BreachedPasswordChecker
    {
        return new class () implements BreachedPasswordChecker {
            public function isBreached(string $password): bool
            {
                return false;
            }
        };
    }

    private function alwaysBreached(): BreachedPasswordChecker
    {
        return new class () implements BreachedPasswordChecker {
            public function isBreached(string $password): bool
            {
                return true;
            }
        };
    }
}
