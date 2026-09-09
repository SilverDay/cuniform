<?php

declare(strict_types=1);

namespace Cuniform\Import;

use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\Slugifier;

/**
 * Turns a parsed WXR export into ready-to-review Cuniform documents (SPEC
 * §A.3's "Pipeline," the front-matter-emission half of it): partitions by
 * `wp:post_type`/`wp:status`, converts each surviving item's body (T35),
 * and writes front matter with `slug` from `wp:post_name` **verbatim**,
 * `date` from `wp:post_date_gmt`, and `source_id` from `wp:post_id`
 * (SPEC §A.3's own three explicitly-named fields) — plus every other
 * shared/post-only field SPEC §5.2/§5.3 make available from what a WXR
 * item actually carries.
 *
 * **Posts only, this pass.** The operator's real export contains no
 * `page` items to validate a page-import path against (BUILD-ORDER's T34
 * note: 6 items, all `post_type=post`) — a `page` item is recognized and
 * reported, never silently dropped, but not yet converted to a
 * `PageFrontMatter` document. Revisit once a real export actually has one.
 *
 * **Redirects are per-document `aliases` front matter, not a separately
 * written file.** SPEC §A.3: "The importer therefore emits a redirect
 * entry for every single imported document... wp:post_name is still
 * taken verbatim, so the mapping is mechanical." `RedirectMapCompiler`
 * (T21) already turns a document's own `aliases` into a compiled
 * redirect at build time — this class only has to put the item's old
 * path there, not duplicate that machinery. The *broader* redirects SPEC
 * §A.3 also mentions in the same breath — category/tag archives, feeds,
 * date archives — are deliberately out of scope here: they aren't tied
 * to any one document's own front matter, Cuniform has no "category"
 * concept to map WordPress's onto, and BUILD-ORDER's T37 line names
 * "redirect generation for every *document*" specifically. Left for a
 * dedicated follow-up.
 */
final class WxrImporter
{
    /**
     * WordPress's own structural item types, never content — silently
     * skipped, no warning: their absence isn't data loss, it's exactly
     * what "skip revisions, nav items, auto-drafts" (SPEC §A.3) asks for.
     */
    private const SILENTLY_SKIPPED_POST_TYPES = ['revision', 'nav_menu_item', 'attachment', 'custom_css', 'customize_changeset'];

    private const SILENTLY_SKIPPED_STATUSES = ['trash', 'auto-draft'];

    private readonly Slugifier $slugifier;
    private readonly FrontMatterEmitter $frontMatterEmitter;

    public function __construct(private readonly string $defaultLanguage)
    {
        $this->slugifier          = new Slugifier();
        $this->frontMatterEmitter = new FrontMatterEmitter();
    }

    /**
     * @return array{documents: list<ImportedDocument>, warnings: list<string>}
     */
    public function import(WxrDocument $wxr): array
    {
        $converter = new HtmlToMarkdownConverter($this->attachmentsById($wxr));

        $documents = [];
        $warnings  = [];

        foreach ($wxr->items as $item) {
            $status = $this->resolveStatus($item, $warnings);
            if ($status === null) {
                continue;
            }

            $slug = $this->resolveSlug($item, $warnings);
            if ($slug === null) {
                continue;
            }

            $date = $this->resolveDate($item, $warnings);
            if ($date === null) {
                continue;
            }

            $conversion = $converter->convert($item->contentEncoded);
            foreach ($conversion->warnings as $warning) {
                $warnings[] = "post_id={$item->postId} ({$slug}): {$warning}";
            }

            $fields = [
                'title'      => $item->title,
                'slug'       => $slug,
                'status'     => $status->value,
                'summary'    => $this->buildSummary($item, $conversion->markdown),
                'date'       => $this->formatDate($date),
                'updated'    => $this->resolveUpdated($item, $date),
                'tags'       => $this->buildTags($item),
                'source_id'  => (string) $item->postId,
                'aliases'    => $this->buildAliases($item),
            ];

            $contents = $this->frontMatterEmitter->emit($fields, $conversion->markdown);

            $documents[] = new ImportedDocument(
                $this->relativePath($slug, $date),
                $contents,
                (string) $item->postId,
            );
        }

        return ['documents' => $documents, 'warnings' => $warnings];
    }

    /**
     * @param list<string> $warnings
     */
    private function resolveStatus(WxrItem $item, array &$warnings): ?DocumentStatus
    {
        if ($item->postType === 'page') {
            $warnings[] = "post_id={$item->postId}: post_type 'page' is not imported yet (no page in the "
                . 'real export to validate that path against) — skipped, not converted';

            return null;
        }

        if ($item->postType !== 'post') {
            if (!in_array($item->postType, self::SILENTLY_SKIPPED_POST_TYPES, true)) {
                $warnings[] = "post_id={$item->postId}: unrecognized post_type '{$item->postType}' — skipped";
            }

            return null;
        }

        if (in_array($item->status, self::SILENTLY_SKIPPED_STATUSES, true)) {
            return null;
        }

        return match ($item->status) {
            'publish' => DocumentStatus::Published,
            'draft', 'pending' => DocumentStatus::Draft,
            'future' => DocumentStatus::Scheduled,
            'private' => $this->skip($warnings, "post_id={$item->postId}: status 'private' needs a manual "
                . 'decision (SPEC §A.3) — skipped'),
            default => $this->skip($warnings, "post_id={$item->postId}: unrecognized status "
                . "'{$item->status}' — skipped"),
        };
    }

    /**
     * @param list<string> $warnings
     */
    private function skip(array &$warnings, string $message): null
    {
        $warnings[] = $message;

        return null;
    }

    /**
     * @param list<string> $warnings
     */
    private function resolveSlug(WxrItem $item, array &$warnings): ?string
    {
        $slug = $item->postName;

        if ($slug === '') {
            $slug = $this->slugifier->slugify($item->title);
            $warnings[] = "post_id={$item->postId}: WXR post_name was empty, derived slug '{$slug}' from "
                . 'the title instead — SPEC §5.4\'s verbatim policy has nothing to take verbatim here';
        }

        if (!preg_match('/^[a-z0-9-]{1,96}$/', $slug)) {
            $warnings[] = "post_id={$item->postId}: post_name '{$item->postName}' is not a valid Cuniform "
                . 'slug and could not be imported — skipped';

            return null;
        }

        return $slug;
    }

    /**
     * @param list<string> $warnings
     */
    private function resolveDate(WxrItem $item, array &$warnings): ?\DateTimeImmutable
    {
        if ($item->postDateGmt !== null) {
            return $item->postDateGmt;
        }

        if ($item->pubDate !== null) {
            $warnings[] = "post_id={$item->postId}: wp:post_date_gmt was invalid, used pubDate instead";

            return $item->pubDate;
        }

        $warnings[] = "post_id={$item->postId}: no usable date (post_date_gmt and pubDate both invalid) — skipped";

        return null;
    }

    private function formatDate(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP');
    }

    private function resolveUpdated(WxrItem $item, \DateTimeImmutable $date): ?string
    {
        if ($item->postModifiedGmt === null) {
            return null;
        }

        // Same-day is treated as "not meaningfully different" — WordPress
        // touches post_modified on every save, including trivial internal
        // ones, and a same-day updated stamp would just be noise on top
        // of `date` rather than useful "this was revised later" signal.
        if ($item->postModifiedGmt->format('Y-m-d') === $date->format('Y-m-d')) {
            return null;
        }

        return $this->formatDate($item->postModifiedGmt);
    }

    private function buildSummary(WxrItem $item, string $markdown): string
    {
        $excerpt = trim(strip_tags($item->excerptEncoded));
        $source  = $excerpt !== '' ? $excerpt : trim($markdown);

        $source = (string) preg_replace('/\s+/u', ' ', $source);
        if (mb_strlen($source, 'UTF-8') <= 200) {
            return $source;
        }

        $truncated = mb_substr($source, 0, 197, 'UTF-8');
        $lastSpace = mb_strrpos($truncated, ' ', 0, 'UTF-8');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($truncated) . '...';
    }

    /**
     * @return list<string>
     */
    private function buildTags(WxrItem $item): array
    {
        // Cuniform has no "category" concept distinct from tags — WordPress
        // categories and tags are merged into one flat, deduplicated list.
        // A documented judgment call (BUILD-ORDER's T37 note), not a SPEC
        // requirement: this export's own categories (general, webdesign,
        // virtual-worlds, ...) read as broad topical tags in practice, not
        // a rigid hierarchy Cuniform would need a separate concept for.
        $seen = [];
        $tags = [];

        foreach ($item->categories as $category) {
            if ($category->domain !== 'category' && $category->domain !== 'post_tag') {
                continue;
            }

            $key = mb_strtolower($category->name, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $tags[]     = $category->name;
        }

        return $tags;
    }

    /**
     * @return list<string>
     */
    private function buildAliases(WxrItem $item): array
    {
        $path  = parse_url($item->link, \PHP_URL_PATH);
        $query = parse_url($item->link, \PHP_URL_QUERY);

        // A query-string permalink (?p=123) never had a real indexed path
        // — nothing to redirect from. Only a genuine path-based old URL
        // (WordPress's pretty permalinks) becomes an alias.
        if (!is_string($path) || $path === '' || $path === '/' || is_string($query)) {
            return [];
        }

        return [rtrim($path, '/') . '/'];
    }

    private function relativePath(string $slug, \DateTimeImmutable $date): string
    {
        $utc = $date->setTimezone(new \DateTimeZone('UTC'));

        return sprintf(
            'posts/%s/%s/%s-%s.md',
            $this->defaultLanguage,
            $utc->format('Y'),
            $utc->format('Y-m-d'),
            $slug,
        );
    }

    /**
     * @return array<int, WxrItem>
     */
    private function attachmentsById(WxrDocument $wxr): array
    {
        $attachments = [];
        foreach ($wxr->items as $item) {
            if ($item->postType === 'attachment') {
                $attachments[$item->postId] = $item;
            }
        }

        return $attachments;
    }
}
