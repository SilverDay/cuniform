<?php

declare(strict_types=1);

namespace Cuniform\Template;

/**
 * What `404-root.php` needs (SPEC §7.12): the neutral, no-language-
 * assumption root `/404.html` that a mistyped or stale URL frequently
 * lands on outside any language prefix. Not a ViewModel — there is no
 * single `language` this page is written in (it deliberately carries
 * every configured language's own home link and blurb, SPEC §7.12: "both
 * languages' home links, a short bilingual line"), so it doesn't go
 * through layout.php at all; it's a small, self-contained HTML document
 * of its own. `$defaultLanguage` sets the shell's own `<html lang>` (the
 * closest single value available); each per-language blurb still carries
 * its own `lang` attribute (NFR-4).
 */
final class NeutralErrorContext
{
    /**
     * @param list<array{language: string, homeUrl: string, searchUrl: string, heading: string, body: string, homeLinkLabel: string, searchLinkLabel: string}> $languages
     *        `searchLinkLabel` reuses the `search_heading` UI string —
     *        this page doesn't need a dedicated key just to say "Search"
     *        a second way.
     */
    public function __construct(
        public readonly array $languages,
        public readonly string $defaultLanguage,
        public readonly string $stylesheetUrl,
    ) {
    }
}
