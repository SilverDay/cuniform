<?php

declare(strict_types=1);

namespace Cuniform\Tests\Content;

use Cuniform\Content\PagePathResolver;
use PHPUnit\Framework\TestCase;

final class PagePathResolverTest extends TestCase
{
    public function testIndexMdRouteIsItsOwnDirectory(): void
    {
        $resolver = new PagePathResolver();

        self::assertSame(
            'vortraege',
            $resolver->resolve('pages/de/vortraege/index.md', 'vortraege')
        );
    }

    public function testNonIndexFileUsesTheSlugAsItsOwnLeafSegment(): void
    {
        $resolver = new PagePathResolver();

        self::assertSame(
            'talks/coffee-factor',
            $resolver->resolve('pages/en/talks/coffee-factor.md', 'coffee-factor')
        );
    }

    public function testSlugOverridesAFilenameThatDiffersFromIt(): void
    {
        $resolver = new PagePathResolver();

        self::assertSame(
            'impressum',
            $resolver->resolve('pages/de/imprint-draft.md', 'impressum')
        );
    }

    public function testTopLevelIndexResolvesToTheEmptyPath(): void
    {
        $resolver = new PagePathResolver();

        self::assertSame('', $resolver->resolve('pages/de/index.md', 'home'));
    }

    public function testFlatPageHasNoDirectorySegment(): void
    {
        $resolver = new PagePathResolver();

        self::assertSame('impressum', $resolver->resolve('pages/de/impressum.md', 'impressum'));
    }
}
