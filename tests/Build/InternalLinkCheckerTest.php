<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\GeneratedFile;
use Cuniform\Build\InternalLinkChecker;
use PHPUnit\Framework\TestCase;

final class InternalLinkCheckerTest extends TestCase
{
    public function testAResolvableLinkProducesNoFinding(): void
    {
        $pages = [new GeneratedFile('/de/a/', '<a href="/de/b/">b</a>')];
        $known = ['de/b/index.html' => true];

        self::assertSame([], $this->check($pages, $known));
    }

    public function testAnUnresolvableLinkIsAnError(): void
    {
        $pages = [new GeneratedFile('/de/a/', '<a href="/de/missing/">x</a>')];

        $errors = $this->check($pages, []);

        self::assertNotSame([], $errors);
        self::assertStringContainsString('/de/a/', $errors[0]);
        self::assertStringContainsString('/de/missing/', $errors[0]);
    }

    public function testAMissingMediaSrcIsAnError(): void
    {
        $pages = [new GeneratedFile('/de/a/', '<img src="/media/2026/03/missing.jpg">')];

        $errors = $this->check($pages, []);

        self::assertNotSame([], $errors);
        self::assertStringContainsString('/media/2026/03/missing.jpg', $errors[0]);
    }

    public function testAbsoluteBaseUrlLinksAreCheckedTheSameAsRootRelativeOnes(): void
    {
        $pages = [new GeneratedFile('/de/a/', '<a href="https://blog.silverday.de/de/b/">b</a>')];
        $known = ['de/b/index.html' => true];

        self::assertSame([], $this->check($pages, $known));
    }

    public function testExternalMailtoAndFragmentLinksAreIgnored(): void
    {
        $pages = [new GeneratedFile(
            '/de/a/',
            '<a href="https://example.com/">ext</a><a href="mailto:a@example.com">m</a><a href="#toc">frag</a>'
        )];

        self::assertSame([], $this->check($pages, []));
    }

    public function testABareLanguageHomeLinkIsCheckedLikeAnyOtherLink(): void
    {
        $resolved = [new GeneratedFile('/de/a/', '<a href="/en/">home</a>')];
        self::assertSame([], $this->check($resolved, ['en/index.html' => true]));

        $unresolved = [new GeneratedFile('/de/a/', '<a href="/en/">home</a>')];
        self::assertNotSame([], $this->check($unresolved, []));
    }

    /**
     * @param  list<GeneratedFile>  $pages
     * @param  array<string, true>  $known
     * @return list<string>
     */
    private function check(array $pages, array $known): array
    {
        return (new InternalLinkChecker('https://blog.silverday.de'))->check($pages, $known);
    }
}
