<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Preview;

use Cuniform\Admin\Editor\EditorDocumentStore;
use Cuniform\Admin\Editor\EditorSaveRequest;
use Cuniform\Admin\Editor\GitRepository;
use Cuniform\Admin\Preview\PreviewBanner;
use Cuniform\Admin\Preview\PreviewRenderer;
use Cuniform\Build\BuildOptions;
use Cuniform\Build\BuildPipeline;
use Cuniform\Config\BuildSettings;
use Cuniform\Config\Config;
use Cuniform\Config\ConfigPaths;
use Cuniform\Config\MailSettings;
use Cuniform\Config\UrlPrefix;
use PHPUnit\Framework\TestCase;

/**
 * T30 acceptance test (SPEC §12 Path C): preview must be byte-identical to
 * real build output for the same document, apart from the injected banner,
 * and must also work for documents a real build would never emit at all
 * (drafts, unsaved edits) — while never writing anything to disk.
 */
final class PreviewRendererTest extends TestCase
{
    private const FIXTURE_CONTENT = __DIR__ . '/../../fixtures/Build/content';
    private const REAL_TEMPLATES  = __DIR__ . '/../../../templates';
    private const LANG_DIR        = __DIR__ . '/../../../config/lang';

    private string $scratchDir;
    private EditorDocumentStore $store;
    private PreviewRenderer $preview;

    protected function setUp(): void
    {
        $this->scratchDir = sys_get_temp_dir() . '/cuniform_preview_' . uniqid();
        mkdir($this->scratchDir . '/releases', 0o755, true);
        mkdir($this->scratchDir . '/var', 0o755, true);
        mkdir($this->scratchDir . '/public', 0o755, true);

        // Preview never writes (SPEC §12: "Never writes into releases/ or
        // public"), so it's safe to point it straight at the read-only
        // fixture tree BuildPipelineTest also uses — no git repo needed
        // either, since previewPath()/render() never call save()/move().
        $this->store = new EditorDocumentStore(
            self::FIXTURE_CONTENT,
            ['de', 'en'],
            'Europe/Berlin',
            new GitRepository(self::FIXTURE_CONTENT),
        );
        $this->preview = new PreviewRenderer($this->config(), self::LANG_DIR, $this->store);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->scratchDir);
    }

    public function testPreviewOfAnUnchangedPublishedDocumentIsByteIdenticalToBuildOutputApartFromTheBanner(): void
    {
        $pipeline = new BuildPipeline($this->config(), self::LANG_DIR);
        $result   = $pipeline->run(new BuildOptions());
        self::assertNotNull($result->releaseDir);
        $built = (string) file_get_contents($result->releaseDir . '/de/sicherheitskultur/index.html');

        $doc     = $this->store->load('posts/de/2026/2026-03-14-sicherheitskultur.md');
        $request = $doc->asSaveRequestForUpdate();

        $previewResult = $this->preview->render($request);

        self::assertTrue($previewResult->ok, implode('; ', $previewResult->errors));
        self::assertNotNull($previewResult->html);
        self::assertSame($built, PreviewBanner::strip($previewResult->html));
        self::assertNotSame($built, $previewResult->html, 'the raw preview HTML itself must differ — that is the banner');
    }

    public function testPreviewWorksForADraftThatABuildWouldNeverEmit(): void
    {
        $doc     = $this->store->load('posts/de/2026/2026-01-01-draft-post.md');
        $request = $doc->asSaveRequestForUpdate();

        $result = $this->preview->render($request);

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertStringContainsString('<h1>Entwurf</h1>', (string) $result->html);
        self::assertStringContainsString('Dieser Beitrag ist noch nicht fertig.', (string) $result->html);
    }

    public function testPreviewWorksForAScheduledPostNotYetDue(): void
    {
        $doc     = $this->store->load('posts/de/2026/2099-01-01-future-post.md');
        $request = $doc->asSaveRequestForUpdate();

        $result = $this->preview->render($request);

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertStringContainsString('<h1>Zukunftsbeitrag</h1>', (string) $result->html);
    }

    public function testPreviewReflectsAnUnsavedEditAndNeverWritesToDisk(): void
    {
        $before  = (string) file_get_contents(self::FIXTURE_CONTENT . '/posts/de/2026/2026-03-14-sicherheitskultur.md');
        $doc     = $this->store->load('posts/de/2026/2026-03-14-sicherheitskultur.md');
        $edited  = $doc->asSaveRequestForUpdate();

        $withUnsavedBody = $this->withRequest($edited, body: 'This body was typed into the editor and never saved.');

        $result = $this->preview->render($withUnsavedBody);

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertStringContainsString('This body was typed into the editor and never saved.', (string) $result->html);

        $after = (string) file_get_contents(self::FIXTURE_CONTENT . '/posts/de/2026/2026-03-14-sicherheitskultur.md');
        self::assertSame($before, $after, 'preview must never write the unsaved buffer to disk');
    }

    public function testPreviewOfAnUnconfiguredLanguageIsInvalid(): void
    {
        $doc     = $this->store->load('posts/de/2026/2026-03-14-sicherheitskultur.md');
        $request = $doc->asSaveRequestForUpdate();

        $badLanguageRequest = $this->withRequest($request, language: 'fr');

        $result = $this->preview->render($badLanguageRequest);

        self::assertFalse($result->ok);
        self::assertNotEmpty($result->errors);
    }

    public function testPreviewOfAnUnknownIdentifierIsInvalid(): void
    {
        $doc     = $this->store->load('posts/de/2026/2026-03-14-sicherheitskultur.md');
        $request = $doc->asSaveRequestForUpdate();

        $missing = $this->withRequest($request, identifier: 'posts/de/2026/does-not-exist.md');

        $result = $this->preview->render($missing);

        self::assertFalse($result->ok);
        self::assertNotEmpty($result->errors);
    }

    /**
     * EditorSaveRequest is a readonly value object with no `with*()` of its
     * own — this fills in every field from $base except the ones $overrides
     * supplies, so each test only has to name what it's actually varying.
     */
    private function withRequest(
        EditorSaveRequest $base,
        ?string $identifier = null,
        ?string $language = null,
        ?string $body = null,
    ): EditorSaveRequest {
        return new EditorSaveRequest(
            identifier: $identifier ?? $base->identifier,
            expectedSha256: $base->expectedSha256,
            language: $language ?? $base->language,
            kind: $base->kind,
            title: $base->title,
            slug: $base->slug,
            status: $base->status,
            summary: $base->summary,
            translationKey: $base->translationKey,
            updated: $base->updated,
            image: $base->image,
            imageAlt: $base->imageAlt,
            canonical: $base->canonical,
            noindex: $base->noindex,
            aliases: $base->aliases,
            toc: $base->toc,
            sourceId: $base->sourceId,
            date: $base->date,
            tags: $base->tags,
            series: $base->series,
            template: $base->template,
            navLabel: $base->navLabel,
            navOrder: $base->navOrder,
            navParent: $base->navParent,
            navGroup: $base->navGroup,
            sitemapPriority: $base->sitemapPriority,
            legal: $base->legal,
            body: $body ?? $base->body,
        );
    }

    private function config(): Config
    {
        return new Config(
            baseUrl: 'https://blog.silverday.de',
            title: 'SilverDay',
            timezone: 'Europe/Berlin',
            languages: ['de', 'en'],
            defaultLanguage: 'en',
            urlPrefix: UrlPrefix::Always,
            permalink: '/{slug}/',
            postsPerPage: 10,
            feedItems: 20,
            paths: new ConfigPaths(
                content: self::FIXTURE_CONTENT,
                templates: self::REAL_TEMPLATES,
                releases: $this->scratchDir . '/releases',
                public: $this->scratchDir . '/public',
                var: $this->scratchDir . '/var',
            ),
            build: new BuildSettings(5, 2 * 1024 * 1024, 0.10, 750 * 1024),
            mail: new MailSettings(false, 'a@example.com', 'a@example.com', 'a@example.com'),
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
            is_link($full) || !is_dir($full) ? unlink($full) : $this->removeDirectory($full);
        }

        rmdir($path);
    }
}
