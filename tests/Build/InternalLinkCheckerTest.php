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

        $result = $this->check($pages, $known);

        self::assertSame([], $result['errors']);
        self::assertSame([], $result['warnings']);
    }

    public function testAnUnresolvableLinkIsAnError(): void
    {
        $pages = [new GeneratedFile('/de/a/', '<a href="/de/missing/">x</a>')];

        $result = $this->check($pages, []);

        self::assertNotSame([], $result['errors']);
        self::assertStringContainsString('/de/a/', $result['errors'][0]);
        self::assertStringContainsString('/de/missing/', $result['errors'][0]);
    }

    public function testAMissingMediaSrcIsAnError(): void
    {
        $pages = [new GeneratedFile('/de/a/', '<img src="/media/2026/03/missing.jpg">')];

        $result = $this->check($pages, []);

        self::assertNotSame([], $result['errors']);
        self::assertStringContainsString('/media/2026/03/missing.jpg', $result['errors'][0]);
    }

    public function testAbsoluteBaseUrlLinksAreCheckedTheSameAsRootRelativeOnes(): void
    {
        $pages = [new GeneratedFile('/de/a/', '<a href="https://blog.silverday.de/de/b/">b</a>')];
        $known = ['de/b/index.html' => true];

        $result = $this->check($pages, $known);

        self::assertSame([], $result['errors']);
    }

    public function testExternalMailtoAndFragmentLinksAreIgnored(): void
    {
        $pages = [new GeneratedFile(
            '/de/a/',
            '<a href="https://example.com/">ext</a><a href="mailto:a@example.com">m</a><a href="#toc">frag</a>'
        )];

        $result = $this->check($pages, []);

        self::assertSame([], $result['errors']);
        self::assertSame([], $result['warnings']);
    }

    public function testABareLanguageHomeLinkIsAWarningNotAnError(): void
    {
        $pages = [new GeneratedFile('/de/a/', '<a href="/en/">home</a>')];

        $result = $this->check($pages, []);

        self::assertSame([], $result['errors']);
        self::assertNotSame([], $result['warnings']);
        self::assertStringContainsString('/en/', $result['warnings'][0]);
    }

    /**
     * @param  list<GeneratedFile>  $pages
     * @param  array<string, true>  $known
     * @return array{errors: list<string>, warnings: list<string>}
     */
    private function check(array $pages, array $known): array
    {
        return (new InternalLinkChecker('https://blog.silverday.de', ['de', 'en']))->check($pages, $known);
    }
}
