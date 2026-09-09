# Session Handover — 2026-09-09 (T20)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T20 (Artifacts) on top of T1-T16/T18/T19 from earlier sessions. `docs/SPEC.md` and
`docs/BUILD-ORDER.md` remain authoritative — this file explains the *why* behind decisions
those documents don't fully capture, and what to do next. Safe to delete once it goes stale.

## Current state

- `make check` is green: 351 tests, 691 assertions, PHPStan level 8, PSR-12 + escaping lint.
- BUILD-ORDER.md status: T1–T16, T18, T19, T20 are `[x]`. T17 is still `[ ]` (its "T17
  progress" note is unchanged). T20 has its own "T20 note" — read it before assuming every
  §11 requirement is fully wired end-to-end.
- A real `bin/cuniform build` now writes, per release: every post/page route, per-language
  `feed.xml`/`atom.xml`, one site-wide `sitemap.xml`, `search-index.json`,
  `robots.txt`, `.well-known/security.txt`, and a fingerprinted `style.<hash8>.css`. Still no
  deploy (T23) — nothing touches `public/`.
- `templates/style.css` is new and real (SPEC §9's required 7 highlight classes + base
  palette + dark mode) — previously didn't exist anywhere in the repo, which would have made
  "asset fingerprinting" meaningless without it.

## Decisions made this session that a future reader should know about

1. **Found and fixed a real T19 bug: hreflang URLs were route-relative, not absolute.**
   `SiteResolver` was passing bare route paths (`/de/x/`) into `TranslatedDocument`/
   `HreflangSetBuilder`, so every `<link rel="alternate" hreflang="...">` in the previous
   session's output was missing its scheme and host — invalid per SPEC §7.5's own example, and
   contradicting what the T17-era `TemplateRendererTest` fixtures already assumed (they
   construct `HreflangEntry` with absolute URLs). Not caught earlier because
   `BuildPipelineTest` only checked `hreflang="en"` substrings, never the `href` value. Fixed
   by making `SiteResolver::absoluteUrl()` prefix `config.baseUrl` before building
   `TranslatedDocument`/keying `$hreflangByUrl` — and the regression test now asserts the full
   `href` value, not just the `hreflang` code, on both `SiteResolverTest` and
   `BuildPipelineTest`.
2. **`noindex` reaches `head.php` by promoting it (and `toc`/`headings`) onto the abstract
   `ViewModel` base class**, rather than an `instanceof` check in the template. Both
   `PostViewModel` and `PageViewModel` already declared these three fields identically; T20 is
   the first task that actually needed to *read* `noindex` from code that only has a `ViewModel`
   (not knowing which concrete subtype), so the duplication became a real problem rather than
   a style nit. The refactor is behavior-preserving for every existing caller — `PostViewModel`/
   `PageViewModel`'s own public constructor *signatures* are unchanged (same params, same
   order), only their bodies now forward `noindex`/`toc`/`headings` to `parent::__construct()`
   instead of promoting them a second time.
3. **`templates/style.css` didn't exist anywhere before this session**, despite SPEC §9
   requiring it (7 highlight classes, a specific base palette, dark mode). No earlier task had
   claimed it as a deliverable. Written now because "asset fingerprinting" (explicitly T20's
   own line item) is meaningless without a source file to fingerprint. `LayoutContext` gained a
   `stylesheetUrl` field (default `/style.css`, so existing tests that don't pass one still
   work) and `head.php` now links it via `eUrl()` instead of a hardcoded path.
4. **`security.txt`'s `Contact` field reuses `config.mail.notify`** rather than adding a new
   config key — it's already the operator's own notification address (SPEC §15.2), and RFC
   9116 only requires *a* contact method, not a dedicated security-specific one. `Expires` is
   computed as build-time + 1 year, per the RFC's own recommendation against a longer window.
5. **Tag/series indexes and `redirects.map` compilation are still not built** — same reasoning
   as last session (`ResolvedSite`'s docblock): nothing consumes tag/series data yet (T17's
   remaining templates), and redirect compilation is explicitly T21's own task. T20 only reads
   what already exists on `ResolvedSite`/`RenderedDocument`.
6. **`GeneratedFile` (route HTML) and `ArtifactFile` (everything else T20 writes) stayed
   separate types** rather than unifying them — a route's file path is *derived* from its route
   (`<route>/index.html`), while an artifact's path is given directly (`sitemap.xml`,
   `.well-known/security.txt`). `BuildPipeline::writeRelease()` takes both lists and writes them
   through one shared `writeFile()` helper, which is the only place that needed to know both
   shapes exist.

## What's still deferred and why

Unchanged from last session's list, still accurate:

- T17's remaining templates (`index.php`, `tag.php`, `series.php`, `archive.php`, `search.php`,
  `404.php`, `feed.xml.php`, `post-card`/`pagination` partials).
- `lang-switcher.php`'s disabled/home-link state for an untranslated document (SPEC §7.7).
- Tag/series indexes, prev/next, `redirects.map` compilation.

## Recommended next step

**T21** (redirect map compilation and the hand-written feed redirect entry, §7.11/§8.3) is a
natural next step — small, well-specified, and would give `redirects.map` an actual consumer.
**T22** (build verification, §10.3) depends on both T20 and T21 and is the other realistic next
step; it's the point where things like "every internal link resolves," "no alias points at a
missing path," and the URL-scheme-change guard actually get enforced, which matters more now
that T20 produces several more cross-referencing artifacts (sitemap ↔ hreflang, feeds ↔ posts)
worth verifying automatically rather than only by reading test assertions.
