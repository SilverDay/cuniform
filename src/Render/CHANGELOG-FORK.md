# Md2Html — fork changelog

`src/Render/Md2Html.php` is an owned fork, not a vendored copy (SPEC §4, Appendix B #6).
Every change to that file — bug fix or feature — gets an entry here. This file, not a
byte-hash check, is the provenance record and the review control.

## Forked from

- **Upstream:** [`SilverDay/md2html-php`](https://github.com/SilverDay/md2html-php)
- **Commit:** `f1e01621bef89498ef1a0bfb4d4d9c7488fb34e8` (2026-08-21)
- **Imported into Cuniform:** 2026-09-09
- **License:** MIT (Klaus-E. Klingner), retained as-is

## Included from upstream at fork time

Both already fixed in the forked commit, so no porting work was needed for them — recorded
here for provenance since they are exactly the "serious bugs found on another project" that
prompted forking instead of continuing to vendor:

- **`cf88832` — Fix infinite recursion when a list item has an indented continuation
  paragraph.** `parseList()` recursed into nested-list parsing for any more-indented line,
  assuming it was a nested list item. An indented continuation paragraph (not a list item)
  made the recursive call return without advancing, so the caller re-entered the same
  recursion on an unchanged index forever. Found via a real production hang (30s timeout)
  rendering a numbered list with continuation text under each item.
- **`f1e0162` — Fold list-item continuation paragraphs into the same `<li>`.** The fix above
  stopped the hang by ending the list at the continuation line, which reset numbering to 1
  on every continuation paragraph instead of continuing 1, 2, 3… Now checks whether the
  more-indented line actually looks like a list marker before recursing; if not, it's folded
  into the preceding `<li>` as an additional paragraph and the list (and its numbering)
  keeps going. The recursion-progress guard from `cf88832` stays as a defensive fallback.

## Changes made during import into Cuniform

- Added `namespace Cuniform\Render;`; the class is now `Cuniform\Render\Md2Html`, PSR-4
  autoloaded like every other engine class. No behavioural change.
- `renderPage()`'s bundled-CSS lookup (`dirname(__DIR__) . '/assets/css/md2html.css'`)
  assumes the classic standalone package layout (`assets/` beside `src/`), which does not
  exist in Cuniform's tree. Left as-is rather than fixed or removed: Cuniform's adapter
  always calls `convert()` in headless mode (SPEC §9 — Cuniform ships its own stylesheet),
  so `renderPage()` is not on Cuniform's render path and this is dead code here. Revisit if
  that ever changes.
- Two `preg_match()` results (`parseList()`'s indent detection) are typed by PHPStan as
  possibly not containing offset 1, since the stub can't know `/^([ \t]*)/` always matches.
  Added `?? ''` at both call sites — level 8 clean, no behavioural change.
- Built the four features SPEC §4.3 previously described as proposed upstream
  contributions directly into the fork, since there's no upstream release cycle to wait on
  anymore. Each is additive (new option, new method) — no existing behaviour changed, and
  every case above has a test in `tests/Render/Md2HtmlTest.php`:
  - **UTF-8-aware `slugify()`.** The unfixed version used `\w` without `/u`, so a multi-byte
    character's individual bytes each failed `\w` and got stripped one at a time —
    `Sicherheitsprüfung` corrupted to `sicherheitsprfung` instead of a slug that simply
    omits what it can't represent. Now uses `\p{L}`/`\p{N}` with `/u`, preserving Unicode
    letters and digits: `Sicherheitsprüfung` → `sicherheitsprüfung`. No transliteration —
    that's Cuniform's own content-slug policy (SPEC §5.4, engine-level Slugifier, T5), not a
    heading-anchor concern, and deliberately stays out of this general-purpose fork.
  - **Injectable `slugify` option** (`callable(string): string`) to override heading-id
    generation entirely, resolved via the new private `resolveSlug()`.
  - **`getHeadings(): array`**, returning `list<array{level: int, id: string, text: string}>`
    in document order, reset at the start of every `convert()`/`convertFile()` call. Collects
    ATX headings only (the parser doesn't assign ids to setext headings — a pre-existing gap,
    not something this pass introduced or fixed).
  - **`imageAttributes` option** (default `'loading="lazy" decoding="async"'`), replacing the
    previously hardcoded `loading="lazy"` on every `<img>` tag.
  - **`stripFrontMatter` option** (default off) plus `getFrontMatter(): string`. Strips a
    leading `---`-delimited block textually — no YAML parsing, that stays the caller's job —
    and leaves a `---` anywhere else (e.g. a horizontal rule) alone.
- Added `isHeadless(): bool`. `RenderAdapter` (T7) now takes an injected `Md2Html` instance
  instead of constructing its own, so it can share one instance with the `[toc]` shortcode
  handler (T9 — it needs `getHeadings()` after the document renders); this getter lets the
  adapter assert the shared instance is actually headless (SPEC §9) instead of trusting the
  caller silently.
