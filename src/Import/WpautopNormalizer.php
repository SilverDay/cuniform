<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * Some Classic-editor content stores literal `<p>` tags; some stores
 * bare, blank-line-separated text and relies on WordPress's own
 * `wpautop()` to wrap it in `<p>` at render time (SPEC §A.2 explicitly
 * names "wpautop newlines" as something a long-lived site's export can
 * contain) — confirmed both forms exist in the operator's own real
 * export, not merely a documented possibility (see BUILD-ORDER's T34
 * note). Content already containing block-level markup is trusted as-is
 * and left alone; only content with none gets this treatment, so this
 * never double-wraps or fights hand-written HTML.
 */
final class WpautopNormalizer
{
    private const BLOCK_TAG_PATTERN = '/<(?:p|div|ul|ol|li|h[1-6]|blockquote|pre|table|figure|section|article)\b/i';

    public function normalize(string $html): string
    {
        if (preg_match(self::BLOCK_TAG_PATTERN, $html) === 1) {
            return $html;
        }

        $blocks = preg_split('/\n[ \t]*\n/', trim($html)) ?: [];

        $paragraphs = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $withBreaks   = (string) preg_replace('/\n/', "<br>\n", $block);
            $paragraphs[] = "<p>{$withBreaks}</p>";
        }

        return implode("\n\n", $paragraphs);
    }
}
