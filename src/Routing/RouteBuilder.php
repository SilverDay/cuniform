<?php

declare(strict_types=1);

namespace Cuniform\Routing;

use Cuniform\Config\UrlPrefix;

/**
 * `{L}` substitution and per-document route paths (SPEC §7.4, §8.1). Scoped
 * to what a single document needs — a post's permalink pattern, a page's
 * directory-mirrored path — not the corpus-wide generated routes (index,
 * pagination, tag/series/archive, feeds, search, 404), which depend on
 * aggregated data (tags, years, series) no component builds yet.
 */
final class RouteBuilder
{
    /**
     * @param list<string> $languages
     * @param string       $permalink Tokens: {slug} {year} {month} {day} (SPEC §8.1).
     */
    public function __construct(
        private readonly UrlPrefix $urlPrefix,
        private readonly array $languages,
        private readonly string $permalink,
    ) {
    }

    /**
     * The `{L}` value for one language under the active url_prefix mode:
     * always prefixes; auto prefixes only when more than one language is
     * configured; never never prefixes (and SPEC §7.1 already rejects
     * `never` with more than one language at config validation, T2).
     */
    public function prefixFor(string $language): string
    {
        return match ($this->urlPrefix) {
            UrlPrefix::Always => "{$language}/",
            UrlPrefix::Auto => count($this->languages) > 1 ? "{$language}/" : '',
            UrlPrefix::Never => '',
        };
    }

    /**
     * @return array{0: string, 1: string} [full path, first path segment —
     *         the latter is what reserved-slug checking (RouteTable) applies to]
     */
    public function postRoute(string $language, string $slug, \DateTimeImmutable $date): array
    {
        $relative = ltrim(
            str_replace(
                ['{slug}', '{year}', '{month}', '{day}'],
                [$slug, $date->format('Y'), $date->format('m'), $date->format('d')],
                $this->permalink
            ),
            '/'
        );

        return $this->finish($language, $relative);
    }

    /**
     * @param string $path Directory-mirrored page path, e.g. "vortraege" or
     *                      "vortraege/coffee-factor" — no leading/trailing slash.
     * @return array{0: string, 1: string}
     */
    public function pageRoute(string $language, string $path): array
    {
        return $this->finish($language, trim($path, '/') . '/');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function finish(string $language, string $relative): array
    {
        $firstSegment = explode('/', $relative, 2)[0];

        return ['/' . $this->prefixFor($language) . $relative, $firstSegment];
    }
}
