<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\Base32;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Base32Test extends TestCase
{
    public function testKnownVectorFromRfc4648(): void
    {
        // RFC 4648 §10's own test vectors (unpadded — this implementation never emits '=').
        $base32 = new Base32();

        self::assertSame('', $base32->encode(''));
        self::assertSame('MY', $base32->encode('f'));
        self::assertSame('MZXQ', $base32->encode('fo'));
        self::assertSame('MZXW6', $base32->encode('foo'));
        self::assertSame('MZXW6YQ', $base32->encode('foob'));
        self::assertSame('MZXW6YTB', $base32->encode('fooba'));
        self::assertSame('MZXW6YTBOI', $base32->encode('foobar'));
    }

    public function testDecodeReversesEncodeForRandomBytes(): void
    {
        $base32 = new Base32();
        $bytes  = random_bytes(20);

        self::assertSame($bytes, $base32->decode($base32->encode($bytes)));
    }

    public function testDecodeIsCaseInsensitiveAndIgnoresDashesSpacesAndPadding(): void
    {
        $base32 = new Base32();

        self::assertSame('foobar', $base32->decode('mzxw6ytboi'));
        self::assertSame('foobar', $base32->decode('MZ XW-6Y TB-OI======'));
    }

    public function testDecodeRejectsAnInvalidCharacter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Base32())->decode('MZXW6YTB01'); // '0' and '1' aren't in the RFC 4648 alphabet
    }
}
