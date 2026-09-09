<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\ArtifactFile;
use Cuniform\Build\GeneratedFile;
use Cuniform\Build\WellFormednessChecker;
use PHPUnit\Framework\TestCase;

final class WellFormednessCheckerTest extends TestCase
{
    public function testValidHtmlAndXmlProduceNoErrors(): void
    {
        $pages = [new GeneratedFile('/de/x/', '<!DOCTYPE html><html><body><p>Hello</p></body></html>')];
        $xml   = [new ArtifactFile('sitemap.xml', '<?xml version="1.0"?><urlset><url><loc>x</loc></url></urlset>')];

        self::assertSame([], (new WellFormednessChecker())->check($pages, $xml));
    }

    public function testOrdinaryHtml5ElementsDoNotFalselyFail(): void
    {
        $pages = [new GeneratedFile(
            '/de/x/',
            '<!DOCTYPE html><html><body><figure><img src="/x.jpg"><figcaption>Cap</figcaption></figure>'
            . '<nav></nav><video controls></video></body></html>'
        )];

        self::assertSame([], (new WellFormednessChecker())->check($pages, []));
    }

    public function testMalformedXmlIsAnError(): void
    {
        $xml = [new ArtifactFile('feed.xml', '<rss><channel><title>Unclosed</channel></rss>')];

        $errors = (new WellFormednessChecker())->check([], $xml);

        self::assertNotSame([], $errors);
        self::assertStringContainsString('feed.xml', $errors[0]);
    }

    public function testMismatchedHtmlTagsAreAnError(): void
    {
        $pages = [new GeneratedFile(
            '/de/broken/',
            '<!DOCTYPE html><html><body><figure><img src="/x.jpg"><figcaption>Cap</figure></body></html>'
        )];

        $errors = (new WellFormednessChecker())->check($pages, []);

        self::assertNotSame([], $errors);
        self::assertStringContainsString('/de/broken/', $errors[0]);
    }

    public function testVoidElementsWithoutASelfClosingSlashAreNotFalselyFlagged(): void
    {
        $pages = [new GeneratedFile(
            '/de/x/',
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="/style.css">'
            . '</head><body><img src="/x.jpg"><br></body></html>'
        )];

        self::assertSame([], (new WellFormednessChecker())->check($pages, []));
    }
}
