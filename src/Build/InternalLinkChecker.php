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
 * One deliberate carve-out: a link to a bare language home (`/en/`, `/de/`)
 * is reported as a warning, not a build-blocking error. SPEC's route table
 * (§8.1) defines that path, hreflang's `x-default` (§7.5) always points at
 * it, and the language switcher can link to it (§7.7) — but no template
 * generates it yet (T17's `index.php` remains unbuilt), so it can never
 * resolve *by construction* on any multi-language site today. Treating
 * that as fatal would make full verification permanently unpassable rather
 * than catching a real mistake; a genuinely broken authored link (a typo'd
 * path, a missing image) still fails the build. Remove this carve-out once
 * T17 generates the home route.
 */
final class InternalLinkChecker
{
    /**
     * @param list<string> $languages
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $languages,
    ) {
    }

    /**
     * @param  list<GeneratedFile>  $pages
     * @param  array<string, true>  $knownReleasePaths Every release-relative
     *                                                   path this build will
     *                                                   actually write.
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function check(array $pages, array $knownReleasePaths): array
    {
        $errors     = [];
        $warnings   = [];
        $homePaths  = array_map(static fn (string $l): string => "/{$l}/", $this->languages);

        foreach ($pages as $page) {
            foreach ($this->internalPathsIn($page->html) as $urlPath) {
                $releasePath = $this->toReleasePath($urlPath);
                if (isset($knownReleasePaths[$releasePath])) {
                    continue;
                }

                if (in_array($urlPath, $homePaths, true)) {
                    $warnings[] = "{$page->routePath}: links to '{$urlPath}', which no route generates yet "
                        . '(T17\'s index page is still unbuilt)';

                    continue;
                }

                $errors[] = "{$page->routePath}: internal link '{$urlPath}' does not resolve to any file "
                    . 'in this release (SPEC §10.3)';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
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
