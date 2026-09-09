<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * One `<item>` from a WXR export (SPEC §A.1) — a post, page, attachment,
 * or one of WordPress's other built-in item types (`nav_menu_item`,
 * `revision`, ...). This class is a faithful transcription of the XML,
 * nothing more: WxrReader does not decide which items matter for import
 * (skipping revisions/nav items/auto-drafts, mapping `wp:status` to
 * Cuniform's own `DocumentStatus`, is SPEC §A.3's "Pipeline" step, not
 * this one's) — every item the export contains comes back as one of
 * these, and a later stage partitions them.
 *
 * `postDate`/`postModified` are kept as WordPress wrote them (the site's
 * configured local time, format unspecified beyond "a date-like string")
 * rather than parsed — SPEC §A.3 says to use `wp:post_date_gmt`, so that
 * one alone is parsed into a `DateTimeImmutable`; the local-time fields
 * are carried for reference only. Comments (`<wp:comment>`) are
 * deliberately not exposed here at all — SPEC §14.4/§A.6: "Comments in
 * the export are not imported."
 */
final class WxrItem
{
    /**
     * @param list<WxrCategory>           $categories
     * @param array<string, list<string>> $postmeta Raw key => values, WordPress
     *                                     allows a meta key to repeat. Values are
     *                                     opaque strings (some are PHP-serialized
     *                                     by WordPress itself) — no attempt is
     *                                     made to interpret them here.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $link,
        public readonly ?\DateTimeImmutable $pubDate,
        public readonly string $creator,
        public readonly string $guid,
        public readonly string $description,
        public readonly string $contentEncoded,
        public readonly string $excerptEncoded,
        public readonly int $postId,
        public readonly string $postDate,
        public readonly ?\DateTimeImmutable $postDateGmt,
        public readonly string $postModified,
        public readonly ?\DateTimeImmutable $postModifiedGmt,
        public readonly string $commentStatus,
        public readonly string $pingStatus,
        public readonly string $postName,
        public readonly string $status,
        public readonly int $postParent,
        public readonly int $menuOrder,
        public readonly string $postType,
        public readonly string $postPassword,
        public readonly bool $isSticky,
        public readonly ?string $attachmentUrl,
        public readonly array $categories,
        public readonly array $postmeta,
    ) {
    }
}
