# Session Handover — 2026-09-09 (T21)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T21 (redirect map compilation) on top of T1-T16/T18/T19/T20 from earlier sessions.
`docs/SPEC.md` and `docs/BUILD-ORDER.md` remain authoritative — this file explains the *why*
behind decisions those documents don't fully capture, and what to do next. Safe to delete once
it goes stale.

## Current state

- `make check` is green: 363 tests, 719 assertions, PHPStan level 8, PSR-12 + escaping lint.
- BUILD-ORDER.md status: T1–T16, T18, T19, T20, T21 are `[x]`. T17 is still `[ ]` (unchanged
  "T17 progress" note). T20 and T21 each have their own note in BUILD-ORDER.md — read both
  before assuming §11/§8.3 are fully wired end-to-end (T22's verification isn't built yet, so
  nothing currently checks a redirect target actually resolves).
- A real `bin/cuniform build` now also writes `redirects.conf` (a `RedirectMatch 301` block)
  per release, combining every document's `aliases` front matter with manual entries from
  `content/redirects.map`.
- **`content/redirects.map` is a new, real, committed file** — not a template/example, actual
  content — carrying the one entry SPEC §7.11 gives verbatim: `/feed.xml` → `/de/feed.xml`.

## Decisions made this session that a future reader should know about

1. **Redirect compilation stayed a sibling of `ArtifactStage`, not folded into it.** SPEC
   §10.1 lists "redirects" as part of stage 7 (Emit) alongside feeds/sitemap/etc., which would
   argue for putting it in `ArtifactStage` (T20's class). Kept as separate classes
   (`RedirectMapParser`, `RedirectMapCompiler`, `RedirectMapGenerator`) instead, called
   directly from `BuildPipeline` alongside `ArtifactStage`, purely to keep the file boundary
   matching BUILD-ORDER's T20/T21 task split — `ArtifactStage`'s docblock specifically
   enumerates T20's own artifact list, and conflating T21's work into it would blur which task
   owns what. Functionally both still run as part of the same conceptual stage 7.
2. **Chose the `RedirectMatch` block format over `RewriteMap txt:`** (§8.3 offers either). A
   `RedirectMatch` block is self-contained — no companion map file, no `RewriteMap` directive
   to keep in sync — which matters because *how* it gets `Include`-d into the vhost, and how
   Apache picks up a changed one on deploy, is T27's job and doesn't exist yet. Revisit if T27
   turns out to want the `RewriteMap` form instead (e.g. for very large redirect sets, where a
   single `RewriteMap` lookup is cheaper than many `RedirectMatch` regex evaluations per
   request) — nothing here is hard to swap, `RedirectMapGenerator` is the only place that'd
   need to change.
3. **A manual `content/redirects.map` entry for `/` is dropped with a warning, not a build
   error.** §8.3 says such an entry "should be omitted rather than allowed to compete" — read
   as authoring guidance rather than a hard failure, since the entry itself isn't *wrong*, just
   redundant with the root's own 302 (§7.4.1). Verified with a fixture entry
   (`tests/fixtures/Build/content/redirects.map` has one) that BuildPipelineTest asserts never
   reaches the compiled `redirects.conf`.
4. **Redirect *target* resolution is explicitly out of scope here.** §10.3's bullet "any entry
   in redirects.map points at a path that does not exist in the new release" is T22's job
   (Verify runs after the full release tree exists to check paths against); T21 only combines
   and de-duplicates entries. Don't be surprised that a redirect to a nonexistent page compiles
   without complaint right now — that's intentional, not an oversight.
5. **Where the compiled `redirects.conf` actually gets picked up by Apache is still an open
   question**, deliberately left for T27. It's written into the release tree like any other
   artifact (so it rotates with each deploy the same way `sitemap.xml` does), but unlike
   sitemap.xml it's not meant to be *served*, it's meant to be `Include`-d into the vhost
   config — which needs either a stable path through the `public/` symlink plus an Apache
   reload on every deploy, or something T27 will need to actually work out.

## What's still deferred and why

Unchanged from last session's list:

- T17's remaining templates (`index.php`, `tag.php`, `series.php`, `archive.php`, `search.php`,
  `404.php`, `feed.xml.php`, `post-card`/`pagination` partials).
- `lang-switcher.php`'s disabled/home-link state for an untranslated document (SPEC §7.7).
- Tag/series indexes and prev/next (SPEC §10.1's Resolve stage lists them; still no consumer).

## Recommended next step

**T22** (build verification, §10.3) is the natural next step — it now has both T20's artifacts
and T21's redirect map to actually verify (every internal link resolves, every referenced media
file exists, hreflang symmetry — already checked at build time by `HreflangSymmetryChecker`,
worth confirming T22 doesn't just re-do that redundantly — reserved slugs, and the redirect
target check T21 explicitly deferred). It also includes the URL-scheme-change guard, which
needs to compare the current release's URL scheme against the *previous* one — worth checking
whether that requires reading state T19/T20/T21 don't currently persist anywhere (nothing
today records what the last successful build's `url_prefix`/`default_language`/`languages` were).
