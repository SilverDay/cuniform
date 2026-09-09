# Session Handover — 2026-09-09 (T25)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T25 (legacy URL enumeration tooling) — the first M4 task, on top of T1-T24 from
earlier sessions (M1-M3 fully done). `docs/SPEC.md` and `docs/BUILD-ORDER.md` remain
authoritative — this file explains the *why* behind decisions those documents don't fully
capture, and what to do next. Safe to delete once it goes stale.

## Current state

- `make check` is green: 511 tests, 1005 assertions, PHPStan level 8, PSR-12 + escaping lint.
- BUILD-ORDER.md status: M1-M3 all `[x]`. M4: T25 is `[x]`; T26 (legacy snapshot support) and
  T27 (Apache vhost + systemd + one-time `public` symlink setup) are still `[ ]`.
- New standalone CLI command, unrelated to the build pipeline: `bin/cuniform legacy-urls
  <base-url> [--output=<path>] [--max-pages=<n>]`. Tries `{base-url}/sitemap.xml` first
  (recursing a sitemap index if that's what it finds), falls back to a same-host,
  robots.txt-respecting spider otherwise. Writes a JSON `UrlInventory` to
  `var/legacy-urls.json` by default.
- New namespace: `src/Cutover/` — M4's cutover tooling. Nothing in it is referenced from
  `BuildPipeline` or any other build-pipeline class; it's entirely separate machinery.

## Decisions made this session that a future reader should know about

1. **T25 is enumeration only, not a content mirror.** SPEC §15.5 names two different `wget`
   invocations for two different purposes: `--spider --recursive` (§19 item 5, T25 — "the
   complete list of live URLs") vs. `--mirror` (§15.5's freeze-and-serve option, T26 — actually
   downloading page content to serve as a frozen snapshot). This session built only the
   former. `LegacyUrlEntry` reflects this directly: a sitemap-sourced entry carries no HTTP
   status or content-type at all, since sitemap URLs are taken on trust rather than verified —
   verifying every one would be a second full pass this task doesn't need.
2. **Sitemap first, spider only as a fallback — never both.** `LegacyUrlCrawler::crawl()`
   tries `{base-url}/sitemap.xml`; if that resolves to a real `<urlset>` or `<sitemapindex>`,
   the spider path is never reached at all. This matches SPEC §19 item 5's own either/or
   framing ("if the current site has no sitemap, a `wget --spider --recursive` run produces
   one") rather than merging both sources.
3. **Everything that touches the network sits behind one interface (`HttpFetcher`), so
   `LegacyUrlCrawler`'s actual logic is fully unit-tested with zero real network calls.** This
   was the main design constraint all session — php-style.md's testing rule ("No network ...
   in tests") applies just as much to a tool that crawls an *external* site as to anything
   else. `StreamHttpFetcher` (PHP streams, not ext-curl — SPEC names no HTTP-client extension
   even as a soft requirement) is the one class that actually opens a socket, and it's
   deliberately thin and untested directly; the one piece of real logic inside it (parsing
   `$http_response_header`'s shape after `file_get_contents` followed 0+ redirects) is pulled
   out into `HttpResponseHeaderParser`, a pure function, which *is* tested. `ApplicationTest`'s
   new `legacy-urls` cases only exercise the argument-parsing and config-loading failure paths
   — both happen before any network access — for the same reason; the crawler's own behaviour
   is `LegacyUrlCrawlerTest`'s job.
4. **robots.txt handling is a courtesy, not a SPEC requirement, and is deliberately a
   simplified reader** — no wildcards, no `$` end-anchors, and a `Disallow` line only applies
   to whichever `User-agent` line most recently preceded it rather than full per-record
   grouping across multiple consecutive `User-agent` lines. Documented as a known
   simplification in `RobotsTxt`'s own docblock. A missing or unparseable robots.txt is always
   allow-all, never a reason to fail the crawl.
5. **`LinkExtractor` uses a regex scan, not a DOM parser, deliberately.** The legacy site's
   markup is unknown and unvalidated — unlike this engine's own renderer output (SPEC §4.4), a
   strict parser would be just as likely to choke on real-world legacy HTML as to catch a
   genuine problem, and a link is a link either way.

## What's still deferred and why

- T26 (legacy snapshot support) — the actual content mirror/freeze, and carrying it through
  builds without colliding with the new site's own routes. This session's `UrlInventory`
  (`var/legacy-urls.json`) is what T26 would read to know which paths need to exist in the
  frozen tree.
- T27 (Apache vhost config, systemd units, one-time `public` symlink setup).
- §15.5's own open item — which of the three cutover options (freeze-and-serve, delayed
  cutover, accept-the-gap) is actually chosen — is still unanswered. T26 specifically only
  makes sense once that's decided; T27 is more independent of it.

## Recommended next step

**T26** is the natural next step given T25 just landed and it's the direct next link in the
same chain — but it has a real dependency the code can't resolve on its own: SPEC §15.5's
three-option decision (freeze-and-serve is the spec author's stated preference, but it's
described as a choice, not a default). Confirm which option before starting T26's
implementation — building "legacy snapshot support" only makes sense for freeze-and-serve;
the other two options need little to no engine code at all (delayed cutover is a deployment
decision, accept-the-gap needs nothing). If that decision isn't available, **T27** (Apache
vhost config, systemd units, one-time `public` symlink setup) is independent of it and could
be picked up instead.
