<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * The one piece of URL parsing both `WxrImporter` (building `aliases`) and
 * `ImportVerifier` (T38's URL diff — SPEC §A.4) need: whether a WXR
 * `<link>` is a genuine pretty-permalink path worth redirecting, as
 * opposed to a query-string permalink (`?p=123`) that never had a real
 * indexed URL. Pulled out once both needed the identical rule, rather than
 * kept duplicated — the definition of "a real old URL" must stay single-
 * sourced or the two checks can silently drift apart.
 */
final class LegacyPermalink
{
    public static function realPathOf(string $link): ?string
    {
        $path  = parse_url($link, \PHP_URL_PATH);
        $query = parse_url($link, \PHP_URL_QUERY);

        if (!is_string($path) || $path === '' || $path === '/' || is_string($query)) {
            return null;
        }

        return rtrim($path, '/') . '/';
    }
}
