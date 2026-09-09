# Session Handover — 2026-09-09 (T19)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T19 (build pipeline stages 1-6) on top of the previous session's T1-T16/T18.
`docs/SPEC.md` and `docs/BUILD-ORDER.md` remain authoritative — this file explains the *why*
behind decisions those documents don't fully capture, and what to do next. Safe to delete once
it goes stale.

## Current state

- `make check` is green: 332 tests, 623 assertions, PHPStan level 8, PSR-12 + escaping lint.
- BUILD-ORDER.md status: T1–T16, T18, T19 are `[x]`. T17 is still `[ ]` with its "T17 progress"
  note unchanged from last session (posts/pages templates exist; index/tag/series/archive/
  search/404/feed.xml don't). T19 has its own "T19 scope note" — read that before assuming the
  pipeline does everything §10.1 describes; it explains what was deliberately deferred and why.
- `bin/cuniform build` and `bin/cuniform build --dry-run` both actually run now (stages 1-6).
  A plain build writes a full release tree under `releases/<timestamp>/` but never touches
  `public/` — no deploy exists yet (T23). `--rollback` still reports not implemented.
- `config/lang/de.php` and `config/lang/en.php` are now real, committed files (`updated_on`
  plus `month_01`..`month_12` for T15's ext-intl fallback) — previously undecided per last
  session's handover.

## Decisions made this session that a future reader should know about

1. **Page route paths: directory tree for parents, front-matter `slug` for the leaf**
   (`src/Content/PagePathResolver.php`, SPEC §6.3). SPEC's own examples
   (`pages/de/vortraege/index.md` → `/vortraege/`) don't disambiguate whether the leaf segment
   comes from the filename or from `slug` — both happen to match in the examples. Resolved by
   consistency with §5.4 (a slug is used verbatim, never re-derived from the filename), the
   same rule posts already follow via `RouteBuilder::postRoute()`. An `index.md` contributes no
   leaf of its own — its route *is* the directory it sits in.
2. **A scheduled *page* (not post) is treated as excluded, same as draft**
   (`SiteResolver::isIncluded()`). §5.3's `date` key is post-only, so a page has nothing to be
   "due" against. SPEC doesn't cover this case at all. Worth raising if a real use for it shows up.
3. **`ParsedDocument::identifier()` is path-based, not slug-based.** A first attempt used
   `"{language}:{slug}"`, which silently broke `RouteTable`'s collision detection: two
   *different* documents that both (wrongly) claim the same slug got the *same* identifier, so
   the "different identifier claiming the same path" collision check never fired. Caught by
   `SiteResolverTest::testRouteCollisionIsABuildError`. Fixed to `"{language}:{relativePath}"`,
   which is unique per file by construction.
4. **`[include]` resolution (`IncludeResolvingPageRepository`) carries `$depth` and
   `$chainPrefix` as constructor state, not as `find()` parameters** — the interface (T9) only
   takes `(slug, language)`. Each successful `find()` builds the *next* level's repository
   instance (`depth+1`, chain+[slug]) and hands it to a fresh `IncludeHandler`/`RenderAdapter`
   pair for the found page's own body, so nested includes are checked against the correct
   depth/chain rather than the top-level one. See the class docblock for the worked-through
   recursion; `BuildPipelineTest::testFullBuildWritesEveryExpectedRouteWithCorrectContent`
   exercises one level of it end-to-end (no fixture yet exercises two levels or a cycle —
   `IncludeHandler`'s own unit tests from T9 already cover those against a fake repository).
5. **`RenderAdapter`/`Md2Html`/shortcode-handler-registry are rebuilt fresh per rendering
   position**, never cached or reused across documents — consistent with why T7 made the
   renderer instance injectable in the first place (shared state between adapter and `[toc]`
   handler only makes sense *within* one document's render). No memoization of rendered
   `[include]` targets either: correctness over performance given the corpus size static site
   generation deals in.
6. **Stage 6 (Template) builds every route's HTML entirely in memory before writing anything**
   (`BuildPipeline::runLocked()`), specifically so a template error partway through leaves zero
   filesystem trace — required by T19's own acceptance criterion. Verified by
   `BuildPipelineTest::testATemplateErrorAbortsTheBuildWithNoOutputWritten`, which points
   `paths.templates` at an empty directory so the very first template resolution fails.
7. **`src/autoload.php` now also `require_once`s `src/Template/helpers.php`.** The global
   escaping helpers (`e`/`eAttr`/`eUrl`/`eJs`) were previously loaded only by individual test
   files (`require_once __DIR__ . '/../../src/Template/helpers.php';`) — `bin/cuniform` itself
   never loaded them, so any real template render would have fataled on an undefined function.
   Latent gap from T16/T17, closed as part of wiring the CLI to a real pipeline.
8. **`Cli\Application` now takes `$projectRoot` via constructor** instead of deriving it from
   `__DIR__`. Needed once `build` started doing real filesystem work — a test invoking the old
   zero-arg constructor would have run a real build against *this checkout's* `config/site.php`
   and `content/`, writing an actual `releases/<timestamp>/` directory as a side effect of
   `vendor/bin/phpunit`. `bin/cuniform` passes `dirname(__DIR__)`; tests pass an isolated temp
   directory. `tests/Cli/ApplicationTest.php` was rewritten around this — the old version
   asserted every `build` invocation returned "not implemented", which is no longer true.
9. **Tag/series indexes, prev/next, and `aliases` → `redirects.map` compilation are
   deliberately not built in the Resolve stage**, despite SPEC §10.1 listing them there — see
   `ResolvedSite`'s docblock. Nothing consumes them yet (T17's remaining templates, T20, T21
   respectively), and building the data ahead of a consumer is exactly the kind of speculative
   structure this project's conventions ask not to add. The `aliases`-collides-with-a-route
   *validation* SPEC §5.5 requires is still enforced (`SiteResolver`), since it only needs the
   route table, not the compiled map.

## What's still deferred and why

Same shape as last time: needs either a consumer that doesn't exist yet, or a later-numbered
task's own explicit scope.

- T17's remaining templates (`index.php`, `tag.php`, `series.php`, `archive.php`, `search.php`,
  `404.php`, `feed.xml.php`, `post-card`/`pagination` partials) — still need aggregated data
  T19 doesn't produce (see point 9 above) and, for the search index specifically, T20.
- `lang-switcher.php`'s disabled/home-link state for an untranslated document (SPEC §7.7) —
  flagged in both this and last session's handover, not addressed either time.
- Tag/series indexes, prev/next, `redirects.map` compilation — see decision 9.

## Recommended next step

**T20** (Artifacts: feeds, sitemap, search index, robots.txt, security.txt, asset
fingerprinting) is the natural next task per BUILD-ORDER's ordering, and would finally give the
deferred tag/series data a consumer. Alternatively, finishing T17's remaining templates first
would let a *second* pass at T19-shaped work (index/tag/archive pages) happen without
speculating about their ViewModel shapes — the same trade-off the previous session flagged for
T17 vs T19 itself. Worth deciding with the user which order they'd rather work in; both are
legitimate given how BUILD-ORDER's dependency graph is actually shaped (T20 depends on T19,
not on the rest of T17).
