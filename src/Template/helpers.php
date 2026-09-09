<?php

declare(strict_types=1);

use Cuniform\Render\Shortcode\Handlers\UrlAttribute;

/**
 * Template escaping helpers (SPEC §9). Global, deliberately: templates are
 * plain PHP files calling these bare — `<?= e($x) ?>` — not methods on
 * something a template would need to import. `make lint`'s escaping-lint
 * (tools/escaping-lint.php) already expects exactly these four names plus
 * `t()`; nothing there needs to change to match this file.
 *
 * $doc->bodyHtml (renderer output) is the one sink these are not used on
 * (SPEC §9) — everything else printed in a template goes through one of them.
 */
if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('eAttr')) {
    /**
     * Same escaping as e() — ENT_QUOTES already covers attribute contexts —
     * named separately so template code states which context a value is
     * going into, and so the two can diverge later without a mass rename.
     */
    function eAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('eUrl')) {
    /**
     * Scheme allow-list, not just entity-escaping — rejects javascript:,
     * data:, etc. Same allow-list as shortcode handlers use on their own
     * URL attributes (UrlAttribute::sanitize()); reused here rather than
     * duplicated a second time.
     */
    function eUrl(string $value): string
    {
        return UrlAttribute::sanitize($value);
    }
}

if (!function_exists('eJs')) {
    /**
     * Safe embedding of a PHP value into an inline <script> context: JSON
     * with the HEX_* flags so `<`, `>`, `'`, `"`, `&` in the encoded output
     * can't break out of either the surrounding HTML or a quoted JS string.
     *
     * @throws \JsonException
     */
    function eJs(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );
    }
}
