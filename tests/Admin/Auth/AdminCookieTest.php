<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\AdminCookie;
use PHPUnit\Framework\TestCase;

final class AdminCookieTest extends TestCase
{
    public function testHeaderCarriesTheRequiredAttributes(): void
    {
        $header = (new AdminCookie())->header(AdminCookie::SESSION_NAME, 'abc123');

        self::assertStringStartsWith('__Secure-cuniform_admin=abc123;', $header);
        self::assertStringContainsString('Path=/admin', $header);
        self::assertStringContainsString('Secure', $header);
        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Strict', $header);
    }

    public function testHeaderDoesNotUseTheHostPrefix(): void
    {
        // SPEC §13.1: __Host- would mandate Path=/, which the spec rules out.
        self::assertStringStartsNotWith('__Host-', AdminCookie::SESSION_NAME);
    }

    public function testHeaderUrlEncodesTheValue(): void
    {
        $header = (new AdminCookie())->header(AdminCookie::SESSION_NAME, 'a b;c');

        self::assertStringContainsString(rawurlencode('a b;c'), $header);
    }

    public function testClearSetsAnExpiryInThePast(): void
    {
        $header = (new AdminCookie())->clear(AdminCookie::SESSION_NAME);

        self::assertStringContainsString('Expires=Thu, 01 Jan 1970', $header);
        self::assertStringStartsWith('__Secure-cuniform_admin=;', $header);
    }

    public function testTheThreeCookieNamesAreDistinct(): void
    {
        $names = [AdminCookie::SESSION_NAME, AdminCookie::PENDING_LOGIN_NAME, AdminCookie::CSRF_NAME];

        self::assertCount(3, array_unique($names));
    }
}
