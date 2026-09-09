<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * What a post-card partial needs (SPEC §9's `partials/post-card.php`) —
 * shared by the index, tag, series, and archive listing pages, none of
 * which need the post's full rendered body. Each tag carries its own
 * archive's already-built route (RouteBuilder::tagRoute(), via
 * ListingResolver) rather than a bare slug — a template must never
 * construct a route itself (it has no way to know the active
 * `url_prefix` mode), so the URL is precomputed here, same as `url`.
 */
final class PostSummary
{
    /**
     * @param list<array{slug: string, url: string, label: string}> $tags
     *        `slug` is carried alongside the already-built `url` only for
     *        ListingResolver's own tag-archive grouping — a template
     *        should only ever read `url`/`label`.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $summary,
        public readonly \DateTimeImmutable $date,
        public readonly string $formattedDate,
        public readonly array $tags,
        public readonly ?string $series,
    ) {
    }
}
