<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\CsrfToken;
use PHPUnit\Framework\TestCase;

final class CsrfTokenTest extends TestCase
{
    public function testMatchesWhenCookieAndSubmittedValueAreEqual(): void
    {
        $token = (new CsrfToken())->issue();

        self::assertTrue((new CsrfToken())->matches($token, $token));
    }

    public function testDoesNotMatchWhenValuesDiffer(): void
    {
        $csrf = new CsrfToken();

        self::assertFalse($csrf->matches($csrf->issue(), $csrf->issue()));
    }

    public function testDoesNotMatchWhenEitherSideIsMissing(): void
    {
        $csrf  = new CsrfToken();
        $token = $csrf->issue();

        self::assertFalse($csrf->matches(null, $token));
        self::assertFalse($csrf->matches($token, null));
        self::assertFalse($csrf->matches(null, null));
        self::assertFalse($csrf->matches('', ''));
    }

    public function testIssueProducesDistinctTokens(): void
    {
        $csrf = new CsrfToken();

        self::assertNotSame($csrf->issue(), $csrf->issue());
    }
}
