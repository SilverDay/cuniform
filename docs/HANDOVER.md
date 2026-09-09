# Session Handover — 2026-09-09 (T22)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T22 (build verification) on top of T1-T16/T18/T19/T20/T21 from earlier sessions.
`docs/SPEC.md` and `docs/BUILD-ORDER.md` remain authoritative — this file explains the *why*
behind decisions those documents don't fully capture, and what to do next. Safe to delete once
it goes stale.

## Current state

- `make check` is green: 402 tests, 780 assertions, PHPStan level 8, PSR-12 + escaping lint.
- BUILD-ORDER.md status: T1–T16, T18, T19, T20, T21, T22 are `[x]`. T17 is still `[ ]`
  (unchanged "T17 progress" note). T22 has its own note — read it before assuming every §10.3
  condition is a *new* check; two predate this session and were only confirmed, not rebuilt.
- `bin/cuniform build` now runs stages 1-8 for real (both `--dry-run` and a plain build — Verify
  always runs, since a dry run's point is telling you whether a real build *would* succeed). A
  new `--allow-url-scheme-change` flag exists alongside `--full`/`--dry-run`.
- `content/media/` is now actually copied into the release tree — it never was before this
  session, despite SPEC §7.10 listing it as a shared artifact.

## Decisions made this session that a future reader should know about

1. **`WellFormednessChecker` doesn't use `DOMDocument::loadHTML()` for HTML pages.** Verified
   empirically before committing to the design: PHP's HTML parser only ever reports
   `LIBXML_ERR_WARNING` for broken markup, never `LIBXML_ERR_FATAL`, even for deliberately
   mismatched tags — filtering for fatal-only (the obvious first approach) would make the check
   permanently silent. Instead: self-close void elements, expand bare boolean attributes
   (`<video controls>` → `controls="controls"` — valid HTML5, invalid XML, and something
   `VideoHandler` genuinely emits, caught as a real false-positive while building this), then
   parse as strict XML. Confirmed both that this doesn't false-positive on real generated
   output and that it does catch a genuinely broken tag structure — see
   `WellFormednessCheckerTest`.
2. **`InternalLinkChecker` carves out a warning (not an error) for a bare language-home link**
   (`/en/`, `/de/`). Every `HreflangSet` a multi-language build produces points `x-default` at
   exactly that path (SPEC §7.5), but no template generates that route yet (T17's `index.php`
   is still unbuilt) — so strict enforcement would make full verification permanently
   unpassable on any multi-language site, not catch a real mistake. This was discovered by
   wiring the checker in and watching every existing multi-language `BuildPipelineTest` fixture
   fail on it. A genuinely broken authored link (tested with a real dangling markdown link in
   `BuildPipelineTest::testDryRunStillRunsVerificationAndCanFail`) still fails the build.
3. **`MediaCopier` had to be built from scratch — no earlier task copied `content/media/`
   anywhere.** SPEC §7.10 lists the media tree as a "shared across languages" artifact
   alongside `sitemap.xml`/`search-index.json` (both of which T20 already emits), but nothing
   before this session ever wrote a single media file into a release. Without it, "any
   referenced media file is missing" would have had nothing to check against. `list()` (cheap,
   filenames only) feeds the Verify stage; `copyInto()` (streams bytes, not loaded through the
   `ArtifactFile` in-memory pattern — a media tree can be many megabytes) runs during the write
   phase.
4. **The URL-scheme-change and page-count-drop guards compare against state that has to live
   somewhere, and deploy (`public/`) doesn't exist yet (T23).** `UrlSchemeGuard` persists a
   snapshot to `var/last-build-meta.json` — deliberately in `var/`, not the release tree, since
   nothing about it should ever be served publicly, and only after a successful *non-dry-run*
   build (a failed or dry-run build must never move the baseline). `PageCountGuard` reads the
   most recent existing directory under `paths.releases` directly. Both are written so T23 can
   later switch them to whatever `public/`'s symlink target actually is without changing either
   class's contract.
5. **`PageCountGuard`/`UrlSchemeGuard` expose both a throwing `assert()` and a non-throwing
   `check(): ?string`.** First implementation had `BuildVerifier` catch each guard's own
   `BuildException` and push `$e->getMessage()` into its own error list, then wrap the whole
   thing in a second `BuildException::fromErrors()` — produced a visibly double-wrapped "Build
   validation failed:\n- Build validation failed:\n- ..." message, caught by actually running a
   real scheme-change build end-to-end rather than trusting the design on paper. `check()` is
   what `BuildVerifier` uses now; `assert()` stays for direct/standalone use.
6. **Two of §10.3's ten conditions were already enforced before this session** (alias-shadows-
   route and reserved-slug in `SiteResolver`/T19; hreflang asymmetry in
   `HreflangSymmetryChecker`/T14) **and a third at load time** (missing UI string key in
   `UiStringCatalogue`/T13) — not rebuilt, just confirmed each still has a test that makes it
   fire (`SiteResolverTest`, `HreflangSymmetryCheckerTest`, `UiStringCatalogueTest`). `T22`'s
   acceptance criterion ("each verification condition... has a test that makes it fire") is
   about coverage existing, not about where in the pipeline it lives.

## What's still deferred and why

- T17's remaining templates (`index.php`, `tag.php`, `series.php`, `archive.php`, `search.php`,
  `404.php`, `feed.xml.php`, `post-card`/`pagination` partials) — and per decision #2 above,
  finishing `index.php` specifically is what lets the `InternalLinkChecker` carve-out be
  removed.
- `lang-switcher.php`'s disabled/home-link state for an untranslated document (SPEC §7.7).
- Tag/series indexes and prev/next (SPEC §10.1's Resolve stage lists them; still no consumer).

## Recommended next step

**T23** (atomic deploy, release pruning, `--rollback`) is the natural next step — it's what
`UrlSchemeGuard`/`PageCountGuard` are already written to eventually read from (`public/`'s
symlink target) instead of their current stand-ins, and it's what turns a real `cuniform build`
into something that actually publishes. After that, T17's remaining templates are worth
revisiting specifically to close the `InternalLinkChecker` carve-out documented above.
