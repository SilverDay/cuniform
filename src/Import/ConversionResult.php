<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * HtmlToMarkdownConverter's output: the Markdown source, and every
 * construct it couldn't faithfully convert — an unrecognized HTML tag
 * (stripped, its text content kept, but the tag itself is never emitted
 * — SPEC §4.4/T35's own acceptance: "the converter never emits raw HTML
 * into a .md file") or an unrecognized `[shortcode]` (left verbatim in
 * the Markdown — SPEC §A.3: "unknown shortcodes preserved verbatim and
 * reported, never silently dropped"). Feeds T38's migration report.
 */
final class ConversionResult
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly string $markdown,
        public readonly array $warnings,
    ) {
    }
}
