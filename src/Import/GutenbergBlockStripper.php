<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * Strips Gutenberg block comments (SPEC §A.3's "strip block comments →",
 * the pipeline step immediately before HTML→Markdown) — `<!-- wp:x -->`/
 * `<!-- /wp:x -->`, with or without a JSON attributes object
 * (`<!-- wp:image {"id":123} -->`). What's left is the block's own
 * already-rendered HTML, which flows into the same HTML→Markdown step
 * Classic content uses — Gutenberg and Classic content converge here
 * rather than needing two separate conversion paths (SPEC §A.2 expects
 * a long-lived site to have both).
 */
final class GutenbergBlockStripper
{
    public function strip(string $html): string
    {
        return (string) preg_replace('/<!--\s*\/?wp:\S*(?:\s[^>]*)?-->/', '', $html);
    }
}
