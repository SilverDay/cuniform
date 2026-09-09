# Session Handover — 2026-09-09 (T37)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T37 (front matter emission, verbatim slugs, redirect generation) on top of T1-T36
— except T36, deliberately skipped in sequence at the operator's own direction (their real
export has zero media references, so there was nothing to validate a media downloader
against; T37 was checkable against real data instead). M1-M3 fully done; M4: T25 done, T26 not
applicable, T27 deliverables done (checkbox open pending live verification); M6: T34, T35, T37
done, T36/T38/T39 still open. `docs/SPEC.md` and `docs/BUILD-ORDER.md` remain authoritative —
this file explains the *why* behind decisions those documents don't fully capture, and what to
do next. Safe to delete once it goes stale.

## Current state

- `make check` is green: 617 tests, 1221 assertions, PHPStan level 8, PSR-12 + escaping lint.
- New in `src/Import/`: `FrontMatterEmitter`, `WxrImporter`, `ImportedDocumentWriter` — the
  first code in this repo that *writes* front matter rather than only parsing it.
- New CLI command: `bin/cuniform import-wxr <path-to-export.xml> [--output-dir=<path>]` —
  reads a WXR file, converts every eligible item, and writes candidate documents to
  `var/import/` by default. **Never writes into `content/`** — see decision 1 below.
- All six of the operator's real posts run all the way through `WxrImporter` and the resulting
  front matter parses cleanly through the *actual* `FrontMatterParser` (T4) with zero errors —
  one real warning fires (a draft with an empty WXR `post_name`, handled by the documented
  title-derived-slug fallback).

## Decisions made this session that a future reader should know about

1. **`import-wxr` stages output in `var/import/`, never writes into `content/` directly.**
   SPEC §A.5 requires every imported document to be reviewed before it ships — writing
   straight into `content/posts/` would make a freshly imported document visible to the very
   next `bin/cuniform build` with no review step in between, which is exactly the gap §A.5
   exists to close. Promoting a reviewed document into `content/` is left as the operator's own
   deliberate action (copy the file over); nothing in this session builds a "promote" command.
2. **Posts only, this pass — `page` items are recognized and reported, not silently dropped,
   but not yet converted.** Same reasoning as T35's table-support deferral: the real export has
   no `page` items (T34's own pre-work: 6 items, all `post_type=post`), so building a
   page-import path now would be exercising code against nothing real. `WxrImporter`'s own
   docblock names this as the thing to revisit once an export actually has one.
3. **Redirect generation is scoped to exactly what BUILD-ORDER's own T37 line names: "for
   every document."** Each qualifying item's old WordPress path (from `wp:link`, when it was a
   real pretty-permalink path rather than a `?p=123` query string) becomes that document's own
   `aliases` front matter entry — and that's the *entire* mechanism. `RedirectMapCompiler`
   (T21, already built) already turns a document's `aliases` into a compiled redirect at build
   time, so nothing new had to be built for the per-document case at all, just correct front
   matter. The *broader* redirects SPEC §A.3 mentions in the same breath — category/tag
   archives, feeds, date archives — are explicitly out of scope: they aren't tied to any one
   document, and Cuniform has no "category" concept to map WordPress's onto in the first place
   (see the next point). Flagged as deliberately deferred, not silently skipped.
4. **WordPress categories and tags are merged into one flat, deduplicated `tags` list** —
   a documented judgment call, not a SPEC requirement (same pattern T17 already established:
   flag it, don't silently decide). Cuniform's content model has no separate "category"
   concept, and the real export's own categories (general, webdesign, virtual-worlds, ...) read
   as broad topical tags in practice, which is what makes this reasonable rather than merely
   convenient. Worth a second look if a future export's categories are more clearly
   hierarchical than this one's.
5. **`FrontMatterEmitter`'s escaping was verified against the real parser, not derived from
   documentation.** A quick empirical check confirmed `str_replace(['\\','"'], ['\\\\','\\"'],
   $value)` round-trips correctly through `RestrictedYamlParser`'s own decoder for values
   containing quotes, backslashes, or both together — kept as `FrontMatterEmitterTest`'s
   `roundTrippableValues()` data set rather than a one-time manual check.
6. **`date` prefers `wp:post_date_gmt` (explicit UTC offset, unambiguous), falling back to
   `pubDate`only when the GMT field is invalid** (WordPress's own `0000-00-00 00:00:00`
   sentinel for "never really set," which the real export's own item 180 — id, not
   coincidence — actually has). The fallback is reported, not silent, same as the empty-slug
   case. `updated` is only emitted when `post_modified_gmt` falls on a different *day* than
   `date` — WordPress touches `post_modified` on every save, including trivial internal ones,
   and a same-day stamp would just be noise on top of `date` rather than a genuine
   "revised later" signal.

## What's still deferred and why

- T36 (media downloader) — skipped in task order at the operator's own direction; still needed
  as a general capability (SPEC §A.3), but has nothing real to validate against in this export.
- T38 (verification: count reconciliation, URL diff, word-count tolerance, migration report),
  T39 (manual review tracking file and checklist workflow) — neither built yet.
- Page import (point 2 above).
- The broader category/tag-archive/feed/date-archive redirect generation (point 3 above).
- The bracket/Markdown-character escaping gap T35 already documented — unchanged this session,
  still inert (not present in the real export).

## Recommended next step

**T38** (verification: count reconciliation, URL diff, word-count tolerance, migration report)
is next in M6's sequence and is directly checkable against the real corpus the same way
T34/T35/T37 were — "count reconciliation" in particular has an exact, known-good answer to
check against right now: 6 WXR items in, 2 skipped by design this session (`page`,
`attachment`), leaving a specific expected document count `WxrImporter` should always produce
for this export. **T36** (media downloader) remains the one task in this milestone with no real
data to validate against — worth building whenever it's convenient, but not blocking anything
else in M6, and the operator may want to revisit whether it's worth prioritizing at all given
the real export's own lack of media.
