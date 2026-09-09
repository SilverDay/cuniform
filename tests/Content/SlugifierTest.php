<?php

declare(strict_types=1);

namespace Cuniform\Tests\Content;

use Cuniform\Content\Slugifier;
use PHPUnit\Framework\TestCase;

final class SlugifierTest extends TestCase
{
    public function testTransliteratesGermanUmlautsAndEszett(): void
    {
        $slugifier = new Slugifier();

        self::assertSame('sicherheitspruefung', $slugifier->slugify('Sicherheitsprüfung'));
        self::assertSame('strasse', $slugifier->slugify('Straße'));
        self::assertSame('koeln', $slugifier->slugify('Köln'));
        self::assertSame('aergernis', $slugifier->slugify('Ärgernis'));
    }

    public function testLowercasesPlainAsciiInput(): void
    {
        self::assertSame('hello-world', (new Slugifier())->slugify('Hello World'));
    }

    public function testCollapsesRunsOfNonAlphanumericsToASingleDash(): void
    {
        self::assertSame('foo-bar', (new Slugifier())->slugify('Foo & Bar'));
        self::assertSame('foo-bar', (new Slugifier())->slugify('foo---bar'));
        self::assertSame('foo-bar', (new Slugifier())->slugify('  foo   bar  '));
    }

    public function testTrimsLeadingAndTrailingDashes(): void
    {
        self::assertSame('foo', (new Slugifier())->slugify('-Foo-'));
    }

    public function testNonGermanNonAsciiCharactersAreStrippedNotCorrupted(): void
    {
        $result = (new Slugifier())->slugify('Café Münchner');

        self::assertMatchesRegularExpression('/^[a-z0-9-]*$/', $result);
        self::assertStringNotContainsString("\xC3", $result);
    }

    public function testEmptyAfterStrippingReturnsEmptyString(): void
    {
        self::assertSame('', (new Slugifier())->slugify('!!!'));
    }

    public function testTruncatesToMaxLengthWithoutEndingOnADash(): void
    {
        $input  = str_repeat('a', 100) . ' b';
        $result = (new Slugifier())->slugify($input);

        self::assertLessThanOrEqual(96, strlen($result));
        self::assertStringEndsNotWith('-', $result);
    }

    public function testEnsureUniqueReturnsTheSlugUnchangedWhenNotTaken(): void
    {
        self::assertSame('awareness', (new Slugifier())->ensureUnique('awareness', ['culture']));
    }

    public function testEnsureUniqueAppendsDashTwoOnFirstCollision(): void
    {
        self::assertSame('awareness-2', (new Slugifier())->ensureUnique('awareness', ['awareness']));
    }

    public function testEnsureUniqueIncrementsThroughMultipleCollisions(): void
    {
        $taken = ['awareness', 'awareness-2', 'awareness-3'];

        self::assertSame('awareness-4', (new Slugifier())->ensureUnique('awareness', $taken));
    }

    public function testEnsureUniqueRespectsTheLengthCapWhenAppendingASuffix(): void
    {
        $slug   = str_repeat('a', 96);
        $result = (new Slugifier())->ensureUnique($slug, [$slug]);

        self::assertLessThanOrEqual(96, strlen($result));
        self::assertStringEndsWith('-2', $result);
    }
}
