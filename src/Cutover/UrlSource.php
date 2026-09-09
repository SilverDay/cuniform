<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * How a LegacyUrlEntry was discovered — sitemap and spider are mutually
 * exclusive per crawl (LegacyUrlCrawler prefers the sitemap and only
 * falls back to spidering when none exists), but the source is still
 * worth recording per entry for the inventory file's own readability.
 */
enum UrlSource: string
{
    case Sitemap = 'sitemap';
    case Crawl   = 'crawl';
}
