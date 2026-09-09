<?php

declare(strict_types=1);

namespace Cuniform\Tests\Import;

use Cuniform\Import\GutenbergBlockStripper;
use PHPUnit\Framework\TestCase;

final class GutenbergBlockStripperTest extends TestCase
{
    public function testStripsSimpleBlockComments(): void
    {
        $html = "<!-- wp:paragraph -->\n<p>Hello.</p>\n<!-- /wp:paragraph -->";

        self::assertSame("\n<p>Hello.</p>\n", (new GutenbergBlockStripper())->strip($html));
    }

    public function testStripsBlockCommentsWithJsonAttributes(): void
    {
        $html = '<!-- wp:image {"id":123,"sizeSlug":"large"} --><figure><img src="a.jpg"></figure><!-- /wp:image -->';

        $stripped = (new GutenbergBlockStripper())->strip($html);

        self::assertSame('<figure><img src="a.jpg"></figure>', $stripped);
    }

    public function testLeavesClassicContentWithNoBlockCommentsUnchanged(): void
    {
        $html = '<p>Plain classic content.</p>';

        self::assertSame($html, (new GutenbergBlockStripper())->strip($html));
    }
}
