<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * Same-host `<a href>` extraction for the spider fallback (SPEC §19
 * item 5: "a `wget --spider --recursive` run"). Off-host links are
 * dropped — this is enumerating *this* site's own URLs, not everything it
 * happens to link to. A deliberately simple HTML scan (regex, not a DOM
 * parser): the legacy site's markup is unknown and unvalidated (unlike
 * this engine's own renderer output, SPEC §4.4), so a strict parser would
 * just as likely choke on it; a link is a link either way.
 */
final class LinkExtractor
{
    /**
     * @return list<string> Absolute URLs, same host as $pageUrl, deduplicated.
     */
    public function extract(string $html, string $pageUrl): array
    {
        if (!preg_match_all('/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/i', $html, $matches)) {
            return [];
        }

        $host = parse_url($pageUrl, \PHP_URL_HOST);
        if (!is_string($host)) {
            return [];
        }

        $urls = [];
        foreach ($matches[2] as $href) {
            $resolved = $this->resolve(html_entity_decode($href, \ENT_QUOTES | \ENT_HTML5), $pageUrl);
            if ($resolved === null || parse_url($resolved, \PHP_URL_HOST) !== $host) {
                continue;
            }

            $urls[$this->stripFragment($resolved)] = true;
        }

        return array_keys($urls);
    }

    private function resolve(string $href, string $base): ?string
    {
        $href = trim($href);
        if (
            $href === ''
            || str_starts_with($href, '#')
            || str_starts_with($href, 'mailto:')
            || str_starts_with($href, 'tel:')
            || str_starts_with($href, 'javascript:')
        ) {
            return null;
        }

        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }

        $baseParts = parse_url($base);
        if ($baseParts === false || !isset($baseParts['scheme'], $baseParts['host'])) {
            return null;
        }

        $origin = $baseParts['scheme'] . '://' . $baseParts['host']
            . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '');

        if (str_starts_with($href, '//')) {
            return $baseParts['scheme'] . ':' . $href;
        }

        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }

        $basePath      = $baseParts['path'] ?? '/';
        $lastSlash     = strrpos($basePath, '/');
        $directory     = $lastSlash === false ? '/' : substr($basePath, 0, $lastSlash + 1);

        return $origin . $this->collapseDotSegments($directory . $href);
    }

    private function collapseDotSegments(string $path): string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $trailingSlash = str_ends_with($path, '/') ? '/' : '';

        return '/' . implode('/', $segments) . $trailingSlash;
    }

    private function stripFragment(string $url): string
    {
        return explode('#', $url)[0];
    }
}
