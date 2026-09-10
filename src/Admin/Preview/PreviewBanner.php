<?php

declare(strict_types=1);

namespace Cuniform\Admin\Preview;

/**
 * The one intended divergence from real build output (SPEC §12 Path C:
 * "Byte-identical to build output except for an injected preview banner").
 * Delimited by HTML comment markers rather than a fixed string so strip()
 * can remove exactly what inject() added regardless of the banner's own
 * markup — that's what makes the acceptance test ("byte-identical apart
 * from the banner") checkable without hand-trimming a literal in the test
 * itself. Inserted right after the single `<body>` tag layout.php always
 * emits (SPEC §9: one layout, one `<html lang>`) — never before it, since
 * everything before `<body>` (CSP-relevant `<head>` contents) must stay
 * exactly what a real build would emit.
 */
final class PreviewBanner
{
    private const START_MARKER = '<!-- cuniform-preview-banner:start -->';
    private const END_MARKER   = '<!-- cuniform-preview-banner:end -->';
    private const BODY_TAG     = '<body>';

    public static function inject(string $html): string
    {
        $bodyPos = strpos($html, self::BODY_TAG);
        if ($bodyPos === false) {
            // No <body> to anchor on (shouldn't happen for post.php/page.php
            // routes, the only ones preview renders) — prepend rather than
            // silently drop the banner.
            return self::banner() . $html;
        }

        $insertAt = $bodyPos + strlen(self::BODY_TAG);

        return substr($html, 0, $insertAt) . "\n" . self::banner() . substr($html, $insertAt);
    }

    /**
     * The inverse of inject() — used by tests to confirm preview output is
     * byte-identical to a real build's, apart from exactly this.
     */
    public static function strip(string $html): string
    {
        $start = strpos($html, self::START_MARKER);
        $end   = strpos($html, self::END_MARKER);
        if ($start === false || $end === false) {
            return $html;
        }

        $end += strlen(self::END_MARKER);

        $prefix = substr($html, 0, $start);
        if (str_ends_with($prefix, "\n")) {
            $prefix = substr($prefix, 0, -1);
        }

        return $prefix . substr($html, $end);
    }

    private static function banner(): string
    {
        return self::START_MARKER
            . '<div class="cuniform-preview-banner" role="status">'
            . 'Preview — this page is not published. It may not match what ships.'
            . '</div>'
            . self::END_MARKER;
    }
}
