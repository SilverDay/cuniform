<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * Feed items carry "full content, relative URLs rewritten to absolute"
 * (SPEC §11.1) — a feed reader has no base URL to resolve `/media/...`
 * against the way a browser resolves it against the page it's on. Rewrites
 * only root-relative `src="/..."` and `href="/..."` attribute values;
 * already-absolute (`https://...`) and non-root-relative (`#anchor`,
 * `mailto:...`) values are left alone.
 */
final class AbsoluteUrlRewriter
{
    public function rewrite(string $html, string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');

        return preg_replace_callback(
            '/\b(src|href)="(\/[^"]*)"/',
            static fn (array $m): string => "{$m[1]}=\"{$base}{$m[2]}\"",
            $html
        ) ?? $html;
    }
}
