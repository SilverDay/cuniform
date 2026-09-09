<?php

declare(strict_types=1);

namespace Cuniform\Tests\Render\Shortcode\Handlers;

use Cuniform\Render\RenderException;
use Cuniform\Render\Shortcode\Handlers\IncludedPage;
use Cuniform\Render\Shortcode\Handlers\IncludedPageRepository;
use Cuniform\Render\Shortcode\Handlers\IncludeHandler;
use PHPUnit\Framework\TestCase;

final class IncludeHandlerTest extends TestCase
{
    public function testInlinesTheTargetPagesBodyHtml(): void
    {
        $repo    = $this->fakeRepository(['about' => new IncludedPage('en', '<p>About us.</p>')]);
        $handler = new IncludeHandler($repo, 'en');

        self::assertSame('<p>About us.</p>', $handler->render(['page' => 'about'], null));
    }

    public function testMissingPageAttributeThrows(): void
    {
        $handler = new IncludeHandler($this->fakeRepository([]), 'en');

        $this->expectException(RenderException::class);
        $this->expectExceptionMessageMatches("/requires a 'page' attribute/");
        $handler->render([], null);
    }

    public function testUnresolvedSlugThrows(): void
    {
        $handler = new IncludeHandler($this->fakeRepository([]), 'en');

        $this->expectException(RenderException::class);
        $this->expectExceptionMessageMatches('/found no page/');
        $handler->render(['page' => 'missing'], null);
    }

    public function testCrossLanguageIncludeThrows(): void
    {
        $repo    = $this->fakeRepository(['ueber-uns' => new IncludedPage('de', '<p>Über uns.</p>')]);
        $handler = new IncludeHandler($repo, 'en');

        $this->expectException(RenderException::class);
        $this->expectExceptionMessageMatches('/cross-language includes are a build error/');
        $handler->render(['page' => 'ueber-uns'], null);
    }

    public function testSameLanguageIncludeSucceeds(): void
    {
        $repo    = $this->fakeRepository(['about' => new IncludedPage('de', '<p>Über uns.</p>')]);
        $handler = new IncludeHandler($repo, 'de');

        self::assertSame('<p>Über uns.</p>', $handler->render(['page' => 'about'], null));
    }

    public function testDepthOneAndTwoAreAllowed(): void
    {
        $repo = $this->fakeRepository(['a' => new IncludedPage('en', 'A'), 'b' => new IncludedPage('en', 'B')]);

        self::assertSame('A', (new IncludeHandler($repo, 'en', currentDepth: 0))->render(['page' => 'a'], null));
        self::assertSame('B', (new IncludeHandler($repo, 'en', currentDepth: 1))->render(['page' => 'b'], null));
    }

    public function testDepthExceedingTwoThrows(): void
    {
        $repo    = $this->fakeRepository(['c' => new IncludedPage('en', 'C')]);
        $handler = new IncludeHandler($repo, 'en', currentDepth: 2);

        $this->expectException(RenderException::class);
        $this->expectExceptionMessageMatches('/exceeds the maximum include depth of 2/');
        $handler->render(['page' => 'c'], null);
    }

    public function testCycleIsDetected(): void
    {
        $repo    = $this->fakeRepository(['a' => new IncludedPage('en', 'A')]);
        $handler = new IncludeHandler($repo, 'en', currentDepth: 1, inclusionChain: ['start', 'a']);

        $this->expectException(RenderException::class);
        $this->expectExceptionMessageMatches('/cycle detected/');
        $handler->render(['page' => 'a'], null);
    }

    public function testIsBlockLevelWithNoBody(): void
    {
        $handler = new IncludeHandler($this->fakeRepository([]), 'en');

        self::assertTrue($handler->isBlockLevel());
        self::assertSame('include', $handler->name());
    }

    /**
     * @param array<string, IncludedPage> $pages
     */
    private function fakeRepository(array $pages): IncludedPageRepository
    {
        return new class ($pages) implements IncludedPageRepository {
            /**
             * @param array<string, IncludedPage> $pages
             */
            public function __construct(private readonly array $pages)
            {
            }

            public function find(string $slug, string $language): ?IncludedPage
            {
                return $this->pages[$slug] ?? null;
            }
        };
    }
}
