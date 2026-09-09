<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * LegacyUrlCrawler's output (SPEC §19 item 5) — every path found on the
 * legacy site, with `baseUrl` recorded so the inventory file is
 * self-describing without needing to re-derive which crawl produced it.
 */
final class UrlInventory
{
    /**
     * @param list<LegacyUrlEntry> $entries
     */
    public function __construct(
        public readonly string $baseUrl,
        public readonly array $entries,
    ) {
    }
}
