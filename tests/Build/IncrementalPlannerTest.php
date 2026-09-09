<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\BuildCacheKey;
use Cuniform\Build\CacheManifest;
use Cuniform\Build\CachedDocument;
use Cuniform\Build\DiscoveredDocument;
use Cuniform\Build\IncrementalPlanner;
use Cuniform\Build\ParsedDocument;
use Cuniform\Build\ResolvedDocument;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Content\FrontMatter\SharedFrontMatter;
use PHPUnit\Framework\TestCase;

final class IncrementalPlannerTest extends TestCase
{
    private string $templatesDir;
    private string $langDir;

    protected function setUp(): void
    {
        $this->templatesDir = sys_get_temp_dir() . '/cuniform_planner_tpl_' . uniqid();
        $this->langDir      = sys_get_temp_dir() . '/cuniform_planner_lang_' . uniqid();
        mkdir($this->templatesDir, 0o755, true);
        mkdir($this->langDir, 0o755, true);
        file_put_contents($this->templatesDir . '/layout.php', '<?php // v1');
        file_put_contents($this->langDir . '/en.php', "<?php\nreturn [];\n");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->templatesDir);
        $this->removeDirectory($this->langDir);
    }

    public function testUnchangedDocumentIsNotDirty(): void
    {
        $doc = $this->post('a', 'aaa', null);
        $key = $this->keyComputer();

        $previous = new CacheManifest('nav', [
            'en:posts/en/a.md' => $this->cached($key->forDocument('aaa'), null),
        ]);

        $plan = (new IncrementalPlanner())->plan([$doc], $previous, 'nav', $key, full: false);

        self::assertFalse($plan->isDirty('en:posts/en/a.md'));
        self::assertFalse($plan->fullRebuild);
    }

    public function testChangedFileContentMakesItsDocumentDirty(): void
    {
        $doc = $this->post('a', 'aaa-changed', null);
        $key = $this->keyComputer();

        $previous = new CacheManifest('nav', [
            'en:posts/en/a.md' => $this->cached($key->forDocument('aaa-old'), null),
        ]);

        $plan = (new IncrementalPlanner())->plan([$doc], $previous, 'nav', $key, full: false);

        self::assertTrue($plan->isDirty('en:posts/en/a.md'));
    }

    public function testANewDocumentNotInThePreviousManifestIsDirty(): void
    {
        $doc = $this->post('a', 'aaa', null);
        $key = $this->keyComputer();

        $plan = (new IncrementalPlanner())->plan([$doc], CacheManifest::empty(), 'nav', $key, full: false);

        self::assertTrue($plan->fullRebuild, 'an empty previous manifest means no cache at all — full rebuild');
    }

    public function testFullFlagForcesEveryDocumentDirtyEvenWithAMatchingCache(): void
    {
        $doc = $this->post('a', 'aaa', null);
        $key = $this->keyComputer();

        $previous = new CacheManifest('nav', [
            'en:posts/en/a.md' => $this->cached($key->forDocument('aaa'), null),
        ]);

        $plan = (new IncrementalPlanner())->plan([$doc], $previous, 'nav', $key, full: true);

        self::assertTrue($plan->fullRebuild);
        self::assertTrue($plan->isDirty('en:posts/en/a.md'));
    }

    public function testANavHashChangeForcesAFullRebuild(): void
    {
        $doc = $this->post('a', 'aaa', null);
        $key = $this->keyComputer();

        $previous = new CacheManifest('old-nav', [
            'en:posts/en/a.md' => $this->cached($key->forDocument('aaa'), null),
        ]);

        $plan = (new IncrementalPlanner())->plan([$doc], $previous, 'new-nav', $key, full: false);

        self::assertTrue($plan->fullRebuild);
    }

    public function testAnUnchangedSiblingIsMarkedDirtyWhenAnotherGroupMemberChanges(): void
    {
        $key = $this->keyComputer();
        $de  = $this->post('de-a', 'de-content-changed', 'shared-key', language: 'de');
        $en  = $this->post('en-a', 'en-content', 'shared-key', language: 'en');

        $previous = new CacheManifest('nav', [
            'de:posts/de/de-a.md' => $this->cached($key->forDocument('de-content-old'), 'shared-key'),
            'en:posts/en/en-a.md' => $this->cached($key->forDocument('en-content'), 'shared-key'),
        ]);

        $plan = (new IncrementalPlanner())->plan([$de, $en], $previous, 'nav', $key, full: false);

        self::assertTrue($plan->isDirty('de:posts/de/de-a.md'), 'directly changed');
        self::assertTrue($plan->isDirty('en:posts/en/en-a.md'), 'unchanged itself, but its translation sibling changed');
    }

    public function testAGroupWithNoChangedMemberStaysClean(): void
    {
        $key = $this->keyComputer();
        $de  = $this->post('de-a', 'de-content', 'shared-key', language: 'de');
        $en  = $this->post('en-a', 'en-content', 'shared-key', language: 'en');

        $previous = new CacheManifest('nav', [
            'de:posts/de/de-a.md' => $this->cached($key->forDocument('de-content'), 'shared-key'),
            'en:posts/en/en-a.md' => $this->cached($key->forDocument('en-content'), 'shared-key'),
        ]);

        $plan = (new IncrementalPlanner())->plan([$de, $en], $previous, 'nav', $key, full: false);

        self::assertFalse($plan->isDirty('de:posts/de/de-a.md'));
        self::assertFalse($plan->isDirty('en:posts/en/en-a.md'));
    }

    public function testARemovedTranslationSiblingInvalidatesTheRemainingUnchangedMember(): void
    {
        $key = $this->keyComputer();
        // Only the German post is included this build (say the English one
        // was unpublished/deleted) — it must still be marked dirty, since
        // its hreflang alternates list just lost a member.
        $de = $this->post('de-a', 'de-content', 'shared-key', language: 'de');

        $previous = new CacheManifest('nav', [
            'de:posts/de/de-a.md' => $this->cached($key->forDocument('de-content'), 'shared-key'),
            'en:posts/en/en-a.md' => $this->cached($key->forDocument('en-content'), 'shared-key'),
        ]);

        $plan = (new IncrementalPlanner())->plan([$de], $previous, 'nav', $key, full: false);

        self::assertTrue($plan->isDirty('de:posts/de/de-a.md'), 'its own content is unchanged, but a sibling disappeared');
    }

    public function testANewlyAddedTranslationSiblingInvalidatesTheExistingUnchangedMember(): void
    {
        $key = $this->keyComputer();
        $de  = $this->post('de-a', 'de-content', 'shared-key', language: 'de');
        $en  = $this->post('en-a', 'en-content', 'shared-key', language: 'en'); // new this build

        // Previously, only the German post existed — no English sibling yet.
        $previous = new CacheManifest('nav', [
            'de:posts/de/de-a.md' => $this->cached($key->forDocument('de-content'), 'shared-key'),
        ]);

        $plan = (new IncrementalPlanner())->plan([$de, $en], $previous, 'nav', $key, full: false);

        self::assertTrue($plan->isDirty('de:posts/de/de-a.md'), 'gained a hreflang alternate, even though its own content is unchanged');
        self::assertTrue($plan->isDirty('en:posts/en/en-a.md'), 'brand new document');
    }

    private function keyComputer(): BuildCacheKey
    {
        return new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);
    }

    private function cached(string $baseKey, ?string $translationKey): CachedDocument
    {
        return new CachedDocument($baseKey, $translationKey, '<p>cached</p>', [], '<html>cached</html>', '/en/cached/');
    }

    private function post(string $slug, string $bodyMarker, ?string $translationKey, string $language = 'en'): ResolvedDocument
    {
        $shared = new SharedFrontMatter(
            title: $slug,
            slug: $slug,
            status: DocumentStatus::Published,
            summary: 'Summary.',
            translationKey: $translationKey,
            updated: null,
            image: null,
            imageAlt: null,
            canonical: null,
            noindex: false,
            aliases: [],
            toc: false,
            sourceId: null,
        );

        $frontMatter  = new PostFrontMatter($shared, new \DateTimeImmutable('2026-01-01'), [], null, "<p>{$bodyMarker}</p>");
        $relativePath = "posts/{$language}/{$slug}.md";
        $discovered   = new DiscoveredDocument("/tmp/{$relativePath}", $relativePath, DocumentKind::Post, $language, 0, $bodyMarker);
        $parsed       = new ParsedDocument($discovered, $frontMatter);

        return new ResolvedDocument($parsed, "/{$language}/{$slug}/", null);
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
