<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

use Cuniform\Content\FrontMatter\DocumentKind;

/**
 * A save/create request from the editor form (SPEC §12). Fields are raw
 * strings wherever FrontMatterParser (T4) is the authoritative validator of
 * their shape — `status`, `nav_group`, `legal` as enum-like strings, `date`/
 * `updated` as ISO-8601 text, `nav_order`/`sitemap_priority` as numeric
 * text — deliberately, so EditorDocumentStore::save() can hand them to
 * FrontMatterEmitter and re-parse the result through the real parser
 * instead of re-implementing its validation rules a second time. Only
 * fields an HTML form already makes unambiguous (a checkbox, a textarea
 * split into lines, the kind radio) arrive already typed.
 */
final class EditorSaveRequest
{
    /**
     * @param list<string> $aliases
     * @param list<string> $tags
     */
    public function __construct(
        public readonly ?string $identifier,
        public readonly ?string $expectedSha256,
        public readonly string $language,
        public readonly DocumentKind $kind,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $status,
        public readonly string $summary,
        public readonly ?string $translationKey,
        public readonly ?string $updated,
        public readonly ?string $image,
        public readonly ?string $imageAlt,
        public readonly ?string $canonical,
        public readonly bool $noindex,
        public readonly array $aliases,
        public readonly bool $toc,
        public readonly ?string $sourceId,
        public readonly ?string $date,
        public readonly array $tags,
        public readonly ?string $series,
        public readonly ?string $template,
        public readonly ?string $navLabel,
        public readonly ?string $navOrder,
        public readonly ?string $navParent,
        public readonly ?string $navGroup,
        public readonly ?string $sitemapPriority,
        public readonly ?string $legal,
        public readonly string $body,
    ) {
    }
}
