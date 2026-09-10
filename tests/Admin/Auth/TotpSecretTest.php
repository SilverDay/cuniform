<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\TotpSecret;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TotpSecretTest extends TestCase
{
    public function testGenerateProducesTwentyBytesByDefault(): void
    {
        self::assertSame(20, strlen(TotpSecret::generate()->raw));
    }

    public function testGenerateRejectsFewerThanOneByte(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TotpSecret::generate(0);
    }

    public function testToBase32ThenFromBase32RoundTrips(): void
    {
        $secret    = TotpSecret::generate();
        $roundTrip = TotpSecret::fromBase32($secret->toBase32());

        self::assertSame($secret->raw, $roundTrip->raw);
    }

    public function testTwoGeneratedSecretsDiffer(): void
    {
        self::assertNotSame(TotpSecret::generate()->raw, TotpSecret::generate()->raw);
    }
}
