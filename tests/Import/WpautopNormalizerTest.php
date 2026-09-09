<?php

declare(strict_types=1);

namespace Cuniform\Tests\Import;

use Cuniform\Import\WpautopNormalizer;
use PHPUnit\Framework\TestCase;

final class WpautopNormalizerTest extends TestCase
{
    public function testWrapsBlankLineSeparatedTextInParagraphs(): void
    {
        $result = (new WpautopNormalizer())->normalize("First paragraph.\n\nSecond paragraph.");

        self::assertSame("<p>First paragraph.</p>\n\n<p>Second paragraph.</p>", $result);
    }

    public function testSingleNewlineWithinAParagraphBecomesABreak(): void
    {
        $result = (new WpautopNormalizer())->normalize("Line one\nline two.");

        self::assertSame("<p>Line one<br>\nline two.</p>", $result);
    }

    public function testContentAlreadyContainingBlockTagsIsLeftAlone(): void
    {
        $html = "<p>Already wrapped.</p>\n\nBare text that would otherwise get wrapped.";

        self::assertSame($html, (new WpautopNormalizer())->normalize($html));
    }

    public function testContentContainingAListIsLeftAlone(): void
    {
        $html = "<ul><li>one</li></ul>";

        self::assertSame($html, (new WpautopNormalizer())->normalize($html));
    }

    public function testEmptyInputProducesNoParagraphs(): void
    {
        self::assertSame('', (new WpautopNormalizer())->normalize("\n\n  \n\n"));
    }
}
