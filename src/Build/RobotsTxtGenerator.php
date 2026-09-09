<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;

/**
 * `/robots.txt` (SPEC §11, §8.1) — site-wide, never language-prefixed.
 * `/admin` is excluded because it's an Alias outside the release tree
 * (§3.1) with its own authentication (§13.1); crawling it serves nobody.
 * Per-document `noindex` (SPEC §5.2) is a separate mechanism (a page-level
 * meta tag, not a crawl-blocking rule) and doesn't belong in this file.
 */
final class RobotsTxtGenerator
{
    public function __construct(private readonly Config $config)
    {
    }

    public function generate(): ArtifactFile
    {
        $sitemapUrl = rtrim($this->config->baseUrl, '/') . '/sitemap.xml';

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            '',
            "Sitemap: {$sitemapUrl}",
        ];

        return new ArtifactFile('robots.txt', implode("\n", $lines) . "\n");
    }
}
