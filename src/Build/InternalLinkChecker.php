<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;

/**
 * "Any internal link resolves outside the new release tree" and "any
 * referenced media file is missing" (SPEC §10.3) — one scan covers both,
 * since a missing media file *is* an unresolvable internal `src`. Scans
 * every rendered page's `href="..."`/`src="..."` for a same-site URL (the
 * configured `base_url`, or a bare `/`-rooted path) and checks it against
 * every path this release will actually contain.
 *
 * Used to carve out a warning (not an error) for a bare language home
 * link (`/en/`, `/de/`) — SPEC's route table (§8.1) defines that path and
 * hreflang's `x-default` (§7.5) always points at it, but no template
 * generated it until ListingTemplateStage (T17) built `index.php`. Now
 * that it exists, the home route always resolves, so the carve-out is
 * gone: a link to it is checked exactly like any other internal link.
 */
final class InternalLinkChecker
{
    public function __construct(private readonly string $baseUrl)
    {
    }

    /**
     * @param  list<GeneratedFile>  $pages
     * @param  array<string, true>  $knownReleasePaths Every release-relative
     *                                                   path this build will
     *                                                   actually write.
     * @return list<string> Errors — every unresolvable internal link/src.
     */
    public function check(array $pages, array $knownReleasePaths): array
    {
        $errors = [];

        foreach ($pages as $page) {
            foreach ($this->internalPathsIn($page->html) as $urlPath) {
                $releasePath = $this->toReleasePath($urlPath);
                if (isset($knownReleasePaths[$releasePath])) {
                    continue;
                }

                $errors[] = "{$page->routePath}: internal link '{$urlPath}' does not resolve to any file "
                    . 'in this release (SPEC §10.3)';
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function internalPathsIn(string $html): array
    {
        preg_match_all('/\b(?:href|src)="([^"]*)"/', $html, $matches);

        $paths = [];
        foreach ($matches[1] as $value) {
            $path = $this->asInternalPath($value);
            if ($path !== null) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function asInternalPath(string $value): ?string
    {
        $base = rtrim($this->baseUrl, '/');

        if (str_starts_with($value, $base . '/') || $value === $base) {
            $value = substr($value, strlen($base));
            $value = $value === '' ? '/' : $value;
        } elseif (!str_starts_with($value, '/')) {
            // Not root-relative and not our own base_url — external, mailto:,
            // tel:, a fragment-only "#x", or already-rejected by eUrl().
            return null;
        }

        // Strip a query string or fragment; neither affects which file resolves.
        $path = explode('#', explode('?', $value)[0])[0];

        return $path === '' ? '/' : $path;
    }

    private function toReleasePath(string $urlPath): string
    {
        return RoutePathResolver::toReleaseFilePath($urlPath);
    }
}
