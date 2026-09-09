<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\ArtifactFile;
use Cuniform\Build\BuildException;
use Cuniform\Build\BuildVerifier;
use Cuniform\Build\GeneratedFile;
use Cuniform\Build\RedirectEntry;
use PHPUnit\Framework\TestCase;

final class BuildVerifierTest extends TestCase
{
    private string $releasesRoot;
    private string $metaPath;

    protected function setUp(): void
    {
        $dir                = sys_get_temp_dir() . '/cuniform_verifier_' . uniqid();
        $this->releasesRoot = $dir . '/releases';
        $this->metaPath     = $dir . '/var/last-build-meta.json';
        mkdir($this->releasesRoot, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->releasesRoot));
    }

    public function testAValidBuildPassesAndReturnsNoWarnings(): void
    {
        $pages = [new GeneratedFile('/de/a/', '<!DOCTYPE html><html><body><a href="/de/a/">self</a></body></html>')];

        $warnings = $this->verifier()->verify($pages, [], [], [], false);

        self::assertSame([], $warnings);
    }

    public function testCollectsViolationsFromMultipleCheckersIntoOneException(): void
    {
        $pages = [new GeneratedFile('/de/a/', '<a href="/de/missing/">x</a>')];
        $redirects = [new RedirectEntry('/old/', '/de/also-missing/', 'content/redirects.map')];

        try {
            $this->verifier()->verify($pages, [], [], $redirects, false);
            self::fail('expected BuildException');
        } catch (BuildException $e) {
            self::assertStringContainsString('/de/missing/', $e->getMessage());
            self::assertStringContainsString('/de/also-missing/', $e->getMessage());
        }
    }

    public function testMalformedXmlArtifactFailsVerification(): void
    {
        $artifacts = [new ArtifactFile('feed.xml', '<rss><channel><title>Unclosed</channel></rss>')];

        $this->expectException(BuildException::class);
        $this->verifier()->verify([], $artifacts, [], [], false);
    }

    public function testHomePageLinkIsAWarningNotAFailure(): void
    {
        $pages = [new GeneratedFile('/de/a/', '<a href="/en/">home</a>')];

        $warnings = $this->verifier()->verify($pages, [], [], [], false);

        self::assertNotSame([], $warnings);
    }

    private function verifier(): BuildVerifier
    {
        return new BuildVerifier(
            ConfigFixture::make(languages: ['de', 'en'], defaultLanguage: 'en'),
            $this->releasesRoot,
            $this->metaPath,
        );
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}
