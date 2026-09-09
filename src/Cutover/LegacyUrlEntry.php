<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * One URL found on the legacy site. `path` is site-relative (scheme/host
 * stripped — the inventory is about which *paths* need a redirect entry
 * once the new site takes the hostname, SPEC §7.4/§15.5, not about the
 * host itself). `statusCode`/`contentType` are only ever known for a
 * spider-discovered entry (fetching the page is how a link was found in
 * the first place); a sitemap-discovered entry carries both as null —
 * SPEC §19 item 5 describes enumeration, not verification, and verifying
 * every sitemap URL would mean a second full pass this task doesn't need.
 */
final class LegacyUrlEntry
{
    public function __construct(
        public readonly string $path,
        public readonly ?int $statusCode,
        public readonly ?string $contentType,
        public readonly UrlSource $source,
    ) {
    }
}
