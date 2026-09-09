<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * Maps a route/URL path (leading `/`, e.g. "/de/x/" or "/media/y.jpg") to
 * the release-relative file path that route resolves to on disk — the
 * same rule `GeneratedFile::relativeFilePath()` applies to a route
 * (SPEC §8.1: "Trailing slash canonical, DirectoryIndex index.html"),
 * generalized so the Verify stage's checkers (which see URL paths pulled
 * out of rendered HTML and redirect targets, not routes) can use the same
 * rule without going through a GeneratedFile at all.
 */
final class RoutePathResolver
{
    public static function toReleaseFilePath(string $urlPath): string
    {
        if (str_ends_with($urlPath, '/')) {
            $trimmed = trim($urlPath, '/');

            return $trimmed === '' ? 'index.html' : "{$trimmed}/index.html";
        }

        return ltrim($urlPath, '/');
    }
}
