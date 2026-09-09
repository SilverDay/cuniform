<?php

declare(strict_types=1);

namespace Cuniform\Tests\Content;

use Cuniform\Content\ContentException;
use Cuniform\Content\LanguageResolver;
use PHPUnit\Framework\TestCase;

final class LanguageResolverTest extends TestCase
{
    public function testResolvesLanguageForAPost(): void
    {
        $resolver = new LanguageResolver(['de', 'en']);

        self::assertSame('de', $resolver->resolve('posts/de/2026/foo.md'));
    }

    public function testResolvesLanguageForAPage(): void
    {
        $resolver = new LanguageResolver(['de', 'en']);

        self::assertSame('en', $resolver->resolve('pages/en/about.md'));
    }

    public function testResolvesLanguageForATopLevelPage(): void
    {
        // pages/de/impressum.md — no subdirectory beyond the language segment (SPEC §6.3).
        $resolver = new LanguageResolver(['de', 'en']);

        self::assertSame('de', $resolver->resolve('pages/de/impressum.md'));
    }

    public function testResolvesLanguageForADeeplyNestedPost(): void
    {
        $resolver = new LanguageResolver(['de', 'en']);

        self::assertSame('de', $resolver->resolve('posts/de/2026/03/foo.md'));
    }

    public function testNormalizesALeadingSlash(): void
    {
        $resolver = new LanguageResolver(['de']);

        self::assertSame('de', $resolver->resolve('/posts/de/foo.md'));
    }

    public function testRejectsAnUnconfiguredLanguageDirectory(): void
    {
        $resolver = new LanguageResolver(['de', 'en']);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches("/'fr'.*not in the configured languages \\(de, en\\)/");
        $resolver->resolve('posts/fr/foo.md');
    }

    public function testRejectsAPathNotUnderPostsOrPages(): void
    {
        $resolver = new LanguageResolver(['de', 'en']);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches('/not under posts\/ or pages\//');
        $resolver->resolve('media/2026/03/photo.jpg');
    }

    public function testRejectsAPathMissingTheLanguageSegmentEntirely(): void
    {
        // SPEC §7.2: the language segment is required even for a single-language site.
        $resolver = new LanguageResolver(['en']);

        $this->expectException(ContentException::class);
        $resolver->resolve('posts/foo.md');
    }

    public function testSingleLanguageSiteStillRequiresTheSegment(): void
    {
        $resolver = new LanguageResolver(['en']);

        self::assertSame('en', $resolver->resolve('posts/en/foo.md'));
    }
}
