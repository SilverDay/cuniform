<?php

declare(strict_types=1);

namespace Cuniform\Routing;

use Cuniform\Config\UrlPrefix;

/**
 * `{L}` substitution and per-document route paths (SPEC §7.4, §8.1) — a
 * post's permalink pattern, a page's directory-mirrored path — plus the
 * generated-listing routes (index/pagination, tag/series/archive, search,
 * per-language 404) once ListingResolver (T17) has the aggregated data
 * (tags, years, series) those depend on. These never go through
 * RouteTable::register(): every one of them starts with a reserved word
 * (SPEC §8.2's own reserved-slug list — `tag`, `series`, `archive`,
 * `page`, `search`), so a real content document can never collide with
 * one by construction, and the generated set can't collide with itself
 * either (language × kind × identifier is always distinct).
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

    /**
     * `/{L}` for page 1, `/{L}page/2/` for subsequent pages (SPEC §8.1's
     * route table names exactly this pair for the post index; tag archives
     * reuse the same `page/N/` suffix — not spelled out verbatim in §8.1,
     * but the only pattern consistent with it once a tag archive can also
     * span more than one page).
     */
    public function indexRoute(string $language, int $page): string
    {
        return $this->paginatedRoute($language, '', $page);
    }

    public function tagRoute(string $language, string $tagSlug, int $page): string
    {
        return $this->paginatedRoute($language, "tag/{$tagSlug}/", $page);
    }

    /** Not paginated (SPEC §8.1 only calls out the tag archive as such). */
    public function seriesRoute(string $language, string $seriesSlug): string
    {
        return '/' . $this->prefixFor($language) . "series/{$seriesSlug}/";
    }

    /** Not paginated (SPEC §8.1 only calls out the tag archive as such). */
    public function archiveRoute(string $language, int $year): string
    {
        return '/' . $this->prefixFor($language) . "archive/{$year}/";
    }

    public function searchRoute(string $language): string
    {
        return '/' . $this->prefixFor($language) . 'search/';
    }

    /**
     * `/{L}404.html` (SPEC §8.1, §7.12) — only meaningful when $language is
     * actually prefixed; an unprefixed single-language site has no
     * per-language error document, only the neutral root one (§7.12).
     */
    public function errorRoute(string $language): string
    {
        return '/' . $this->prefixFor($language) . '404.html';
    }

    private function paginatedRoute(string $language, string $base, int $page): string
    {
        $suffix = $page <= 1 ? '' : "page/{$page}/";

        return '/' . $this->prefixFor($language) . $base . $suffix;
    }
}
