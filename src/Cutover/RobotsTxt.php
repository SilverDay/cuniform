<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * A best-effort robots.txt reader for the spider fallback — good-citizen
 * crawling, not a SPEC requirement. Handles the common case: one or more
 * `User-agent:` lines followed by `Disallow:` prefixes, using them for
 * `*` (there's no reason to identify this crawler as anything narrower).
 * Deliberately not a full RFC 9309 implementation — no wildcards, no `$`
 * end-anchors, and a `Disallow` line only ever applies to whichever
 * `User-agent` line most recently preceded it, rather than full per-record
 * grouping. Good enough to skip an obvious `Disallow: /wp-admin/`; a
 * missing or unparseable robots.txt is treated as allow-all, never as a
 * reason to fail the crawl.
 */
final class RobotsTxt
{
    /**
     * @param list<string> $disallowedPrefixes
     */
    private function __construct(private readonly array $disallowedPrefixes)
    {
    }

    public static function allowAll(): self
    {
        return new self([]);
    }

    public static function parse(string $body): self
    {
        $prefixes           = [];
        $appliesToThisCrawl = false;

        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '') {
                continue;
            }

            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches) === 1) {
                $appliesToThisCrawl = trim($matches[1]) === '*';

                continue;
            }

            if ($appliesToThisCrawl && preg_match('/^Disallow:\s*(.*)$/i', $line, $matches) === 1) {
                $path = trim($matches[1]);
                if ($path !== '') {
                    $prefixes[] = $path;
                }
            }
        }

        return new self($prefixes);
    }

    public function isAllowed(string $path): bool
    {
        foreach ($this->disallowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }
}
