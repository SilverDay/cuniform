<?php

declare(strict_types=1);

namespace Cuniform\Tests\Render;

use Cuniform\Content\ContentException;
use Cuniform\Content\FilesystemGateway;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\FrontMatterParser;
use Cuniform\Render\Md2Html;
use Cuniform\Render\RenderAdapter;
use Cuniform\Render\Shortcode\Handlers\DetailsHandler;
use Cuniform\Render\Shortcode\Handlers\NoteHandler;
use Cuniform\Render\Shortcode\Handlers\TocHandler;
use Cuniform\Render\Shortcode\ShortcodeHandlerRegistry;
use PHPUnit\Framework\TestCase;

final class RenderAdapterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cuniform_render_adapter_' . uniqid();
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $items = scandir($this->root);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..') {
                    unlink($this->root . '/' . $item);
                }
            }
        }

        rmdir($this->root);
    }

    public function testRendersAPostEndToEnd(): void
    {
        $doc = <<<'MD'
        ---
        title: Security Culture
        slug: security-culture
        status: published
        summary: Why culture beats compliance.
        date: 2026-03-14
        ---
        # Security Culture

        Why **culture** beats compliance.

        ## Details
        MD;
        $path = $this->write('post.md', $doc);

        $result = $this->adapter()->render($path, DocumentKind::Post);

        self::assertSame('Security Culture', $result->frontMatter->shared->title);
        self::assertStringContainsString('<h1 id="security-culture">Security Culture</h1>', $result->bodyHtml);
        self::assertStringContainsString('<strong>culture</strong>', $result->bodyHtml);
        self::assertSame(
            [
                ['level' => 1, 'id' => 'security-culture', 'text' => 'Security Culture'],
                ['level' => 2, 'id' => 'details', 'text' => 'Details'],
            ],
            $result->headings
        );
    }

    public function testFrontMatterNeverReachesTheRenderedOutput(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-01-01\n---\nBody text.";
        $path = $this->write('post.md', $doc);

        $result = $this->adapter()->render($path, DocumentKind::Post);

        self::assertStringNotContainsString('title:', $result->bodyHtml);
        self::assertStringNotContainsString('---', $result->bodyHtml);
        self::assertStringContainsString('Body text.', $result->bodyHtml);
    }

    public function testRendersAPageEndToEnd(): void
    {
        $doc = "---\ntitle: Legal Notice\nslug: legal-notice\nstatus: published\nsummary: S\nlegal: impressum\n---\nBody.";
        $path = $this->write('impressum.md', $doc);

        $result = $this->adapter()->render($path, DocumentKind::Page);

        self::assertStringContainsString('Body.', $result->bodyHtml);
    }

    public function testEndToEndWithRealHandlersTocNoteAndDetails(): void
    {
        $doc = <<<'MD'
        ---
        title: Full Chain
        slug: full-chain
        status: published
        summary: End-to-end T7+T8+T9 check.
        date: 2026-01-01
        ---
        [toc]

        # Introduction

        [note type=warn]Read this **carefully**.[/note]

        # Details

        [details summary="More info"]Hidden **content**.[/details]
        MD;
        $path = $this->write('post.md', $doc);

        $renderer = new Md2Html(['headless' => true]);
        $handlers = new ShortcodeHandlerRegistry([
            new TocHandler($renderer),
            new NoteHandler(),
            new DetailsHandler(),
        ]);
        $adapter = new RenderAdapter(new FilesystemGateway($this->root), new FrontMatterParser(), $renderer, $handlers);

        $result = $adapter->render($path, DocumentKind::Post);

        self::assertStringContainsString('<nav class="toc">', $result->bodyHtml);
        self::assertStringContainsString('<a href="#introduction">Introduction</a>', $result->bodyHtml);
        self::assertStringContainsString('<a href="#details">Details</a>', $result->bodyHtml);
        self::assertStringContainsString('<div class="note note-warn">', $result->bodyHtml);
        self::assertStringContainsString('Read this <strong>carefully</strong>.', $result->bodyHtml);
        self::assertStringContainsString('<details><summary>More info</summary>', $result->bodyHtml);
        self::assertStringContainsString('Hidden <strong>content</strong>.', $result->bodyHtml);
        self::assertStringNotContainsString('%%CFSC', $result->bodyHtml);
        self::assertStringNotContainsString('[toc]', $result->bodyHtml);
    }

    public function testConstructorRejectsANonHeadlessRenderer(): void
    {
        $this->expectException(\Cuniform\Render\RenderException::class);
        new RenderAdapter(new FilesystemGateway($this->root), new FrontMatterParser(), new Md2Html());
    }

    public function testInvalidFrontMatterPropagatesAsContentException(): void
    {
        $doc  = "---\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-01-01\n---\nBody";
        $path = $this->write('post.md', $doc);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches("/missing required key 'title'/");
        $this->adapter()->render($path, DocumentKind::Post);
    }

    public function testDisallowedExtensionPropagatesAsContentException(): void
    {
        $path = $this->write('post.txt', "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-01-01\n---\nBody");

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches('/Disallowed file extension/');
        $this->adapter()->render($path, DocumentKind::Post);
    }

    private function adapter(): RenderAdapter
    {
        return new RenderAdapter(
            new FilesystemGateway($this->root),
            new FrontMatterParser(),
            new Md2Html(['headless' => true])
        );
    }

    private function write(string $relativePath, string $contents): string
    {
        $path = $this->root . '/' . $relativePath;
        file_put_contents($path, $contents);

        return $path;
    }
}
