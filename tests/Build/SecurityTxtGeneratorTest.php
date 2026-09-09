<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\SecurityTxtGenerator;
use PHPUnit\Framework\TestCase;

final class SecurityTxtGeneratorTest extends TestCase
{
    public function testContainsContactExpiresAndCanonical(): void
    {
        $config = ConfigFixture::make(notify: 'security@example.com');
        $now    = new \DateTimeImmutable('2026-03-14T00:00:00Z');

        $file = (new SecurityTxtGenerator($config))->generate($now);

        self::assertSame('.well-known/security.txt', $file->relativePath);
        self::assertStringContainsString('Contact: mailto:security@example.com', $file->contents);
        self::assertStringContainsString('Canonical: https://blog.silverday.de/.well-known/security.txt', $file->contents);
        self::assertMatchesRegularExpression('/Expires: 2027-03-14T/', $file->contents, 'Expires must be about a year out');
    }
}
