# Session Handover — 2026-09-09 (T17)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T17 (the remaining templates) on top of T1-T16, T18-T23 from earlier sessions.
`docs/SPEC.md` and `docs/BUILD-ORDER.md` remain authoritative — this file explains the *why*
behind decisions those documents don't fully capture, and what to do next. Safe to delete once
it goes stale.

## Current state

- `make check` is green: 444 tests, 886 assertions, PHPStan level 8, PSR-12 + escaping lint.
- BUILD-ORDER.md status: T1–T23 are all `[x]` except T24 (incremental builds), which is the
  only remaining M3 task. M1/M2 are fully done; M3 is one task away from done.
- A real (non-dry-run) `bin/cuniform build` now generates and deploys the full route set SPEC
  §8.1 describes: posts, pages, per-language paginated home index, paginated tag archives,
  series indexes, year archives, a client-side search page, per-language 404s, and the neutral
  root `/404.html` — plus the feeds/sitemap/search-index/robots/security.txt T20 already built.
  Read T17's own note in BUILD-ORDER.md before assuming a design detail matches an early
  guess — several things shifted mid-session (see below).

## Decisions made this session that a future reader should know about

1. **`ListingResolver` is a second, independent read of `ResolvedSite`, not something folded
   into `SiteResolver`/`ResolvedSite` itself.** It builds per-language post lists, tag
   archives, series archives, and year archives — `ResolvedSite`'s own docblock explains why
   this stays separate: routing/hreflang/nav resolution is needed by *every* document on
   *every* build, listing aggregation only by the generated-listing routes, and coupling them
   would make `SiteResolver` do two unrelated jobs.
2. **`feed.xml.php` was deliberately never built.** `FeedGenerator` (T20) already produces
   RSS 2.0 and Atom directly via `DOMDocument` — SPEC §9's Template set names `feed.xml.php`
   as one *possible* implementation route, not a second deliverable on top of a working one.
   `TemplateResolver`'s allow-list documents this explicitly so it doesn't read as an oversight.
3. **The neutral root `/404.html` doesn't go through `layout.php`.** That file hard-requires a
   single `<html lang>`, and SPEC §7.12 wants this specific page to carry *every* configured
   language's own blurb and home link with no language assumption. It's its own small template
   (`404-root.php`, fed by `NeutralErrorContext`, not a `ViewModel`) and is generated
   unconditionally — SPEC §3.2's vhost skeleton declares `ErrorDocument 404 /404.html` outside
   any `<Location>` block, so its necessity doesn't depend on how many languages are
   configured or whether URLs are prefixed. The *per-language* `/{L}404.html` only exists when
   that language is actually prefixed (`RouteBuilder::prefixFor($language) !== ''`).
4. **`InternalLinkChecker`'s bare-home-link carve-out (from T22) is gone.** `index.php` now
   generates `/{L}/` for real, so a link to it is checked like any other internal link. Cleaned
   up along with it: the class no longer takes a `$languages` constructor argument, and
   `check()` returns a plain `list<string>` instead of an `{errors, warnings}` shape that could
   now only ever produce an empty `warnings` array — noticed while updating the carve-out's own
   test and traced back to its only source being gone.
5. **`AssetFingerprinter` is now general-purpose**, deriving `<name>.<hash8>.<ext>` from the
   source file's own name instead of a hardcoded `style.css`. `search.php`'s client-side search
   (`templates/search.js`, plain vanilla JS — no inline script, per SPEC §14.1's CSP
   preference) fingerprints through the exact same path `style.css` always has.
6. **`SitemapGenerator` (T20) now also emits page-1 of every listing type** — the home index,
   each tag archive, each series index, each year archive — reading SPEC §11.2's "excludes ...
   pagination beyond page 1" as implying page 1 itself belongs in the sitemap (there'd be
   nothing to exclude "beyond page 1" of otherwise). Search and 404 pages are excluded on
   purpose; neither is content worth indexing.
7. **Two judgment calls where SPEC is silent — flagged in BUILD-ORDER's T17 note, not treated
   as settled:**
   - Series ordering (reverse-chronological, matching every other listing type) — SPEC §5.3
     doesn't say, and "publication order" (ascending, reading start-to-finish) is equally
     defensible.
   - Every generated listing page carries a null `HreflangSet`, so the language switcher
     renders nothing on them — consistent with how an untranslated real document already
     behaves, but for the home page and search page specifically (which *always* have an
     equivalent in every configured language, unlike a tag archive) this reads more like a
     real gap than a deliberate simplification. Worth a second look.

## What's still deferred and why

- T24 (incremental build cache) — every build is still a full build. This is now the only
  open M3 task.
- Tag/series/archive pages are unpaginated except tag (SPEC §8.1 only explicitly calls out the
  tag archive as paginated) — a large series or year could in principle produce a very long
  page; not addressed, since nothing in SPEC asks for it.
- The two judgment calls in point 7 above.

## Recommended next step

**T24** (incremental build cache and invalidation) is the only M3 task left, and it's the
natural next step — it's explicitly scoped in BUILD-ORDER already ("editing one post rebuilds
that post, its list pages, and every page in its translation group"), and "its list pages" now
has real meaning it didn't have before this session: a changed post must invalidate not just
its own page but the home index pages, tag archive pages, series page, and year archive page it
appears on. After that, M4 (cutover: T25-27) and M5 (admin: T28-33) are the remaining phases.
