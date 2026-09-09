# Session Handover — 2026-09-09 (T24)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T24 (incremental build cache and invalidation) on top of T1-T23 from earlier
sessions. `docs/SPEC.md` and `docs/BUILD-ORDER.md` remain authoritative — this file explains
the *why* behind decisions those documents don't fully capture, and what to do next. Safe to
delete once it goes stale.

## Current state

- `make check` is green: 472 tests, 933 assertions, PHPStan level 8, PSR-12 + escaping lint.
- BUILD-ORDER.md status: **M1 and M2 are fully done. M3 is now fully done too** — T16 through
  T24 are all `[x]`. The only remaining phases are M4 (cutover: T25-27), M5 (admin: T28-33),
  and M6 (import: T34-39).
- `bin/cuniform build` now actually skips work on an unchanged document instead of just
  saying every build is a full build: `--full` (previously accepted and silently ignored) now
  bypasses the cache entirely. A plain build's success line reports how many documents were
  reused, e.g. `cuniform: built 5 documents (3 reused from cache), 42 routes -> ...`.
- New: `var/build-cache.json` (build-internal bookkeeping — never served, never part of a
  release, same category as `var/last-build-meta.json` from T22).

## Decisions made this session that a future reader should know about

1. **Only Render (stage 5) and a document's own Template step (stage 6) are actually cached —
   Discover/Parse/Resolve/Listing always run in full, every build.** This was the central
   design decision, and it's why the acceptance criterion ("editing one post rebuilds ... its
   list pages") didn't need any pagination-boundary-aware dependency tracking: listing pages
   (index/tag/series/archive) are always re-templated from freshly-parsed front matter every
   build — cheap, since post cards only need title/summary/date/tags, never the rendered body
   — so they're always correct regardless of which documents' *Render* step got skipped.
   Verify (stage 8) is the same story: always runs against the complete page set, cached pages
   included, since a cached page's own bytes being unchanged says nothing about whether
   something it links to still exists in *this* release.
2. **SPEC §10.2's cache-key formula's four "environment" components (templates hash, UI
   strings hash, engine version, renderer hash) are folded into one shared `$globalSuffix`,
   combined with each document's own file hash.** This is what makes "a changed template, UI
   string file, or renderer invalidates everything" fall out for free — no separate rule was
   needed, since changing any of those changes *every* document's key. Also folded in, beyond
   SPEC's literal formula: the parts of `Config` embedded directly into every cached page
   (`base_url`, `title`, `languages`, `default_language`, `url_prefix`, `permalink`) — a config
   edit with none of those covered could otherwise produce stale cached output with nothing to
   catch it. `templates/` is hashed as a whole directory, not just `.php` files, specifically
   because `style.css`/`search.js` live there too and a cached page's `<head>` embeds their
   *fingerprinted* filename — missing that would leave cached pages pointing at an asset
   filename the new release doesn't contain.
3. **The nav tree needed its own separate invalidation signal, not derivable from the cache
   key.** It isn't a static file and isn't any one document's own hash — it's derived from
   every page's front matter collectively. `BuildCacheKey::navHash()` serializes and hashes it;
   `IncrementalPlanner` compares build-to-build and, if it differs, skips per-document diffing
   entirely (every document dirty). This is the literal reading of SPEC §10.2's "a
   nav-affecting page invalidates everything" — deliberately not trying to figure out which
   specific nav-relevant field on which specific page changed.
4. **Translation-group propagation is the one rule that genuinely needs its own logic** — a
   document's own key can be unchanged while its hreflang alternates list is stale because a
   *sibling* changed. `IncrementalPlanner` compares each `translation_key` group's member set
   (this build vs. the previous manifest's cached entries) and checks whether any current
   member is already directly dirty; if either is true, every *current* member is marked dirty,
   even one whose own content is completely unchanged. This deliberately doesn't check whether
   the actual change would affect the sibling's rendered output — matches SPEC's own "any
   ambiguity resolves toward a full rebuild." Covers all three cases (a sibling edited, added,
   or removed) with one code path — tested individually in `IncrementalPlannerTest`.
5. **`[include]` (SPEC §6.5) dependencies are NOT tracked, and this is flagged prominently
   rather than silently shipped as a latent bug.** A page reached only via `[include]`, not
   edited directly, does not propagate to documents that include it —
   `IncludeResolvingPageRepository` always resolves include targets fresh regardless of this
   cache, but the *including* document's own top-level render can still be served stale from
   cache. `--full` is the documented workaround. Closing this for real would mean
   `RenderedDocument` reporting which pages it included — a reasonable follow-up, out of this
   task's scope. Documented in `IncrementalPlanner`'s own docblock and BUILD-ORDER's T24 note.
6. **Cache storage duplicates content rather than reaching into a previous release
   directory.** `CachedDocument` stores `bodyHtml`, `headings`, and the fully-assembled
   `pageHtml` directly in `var/build-cache.json`, rather than pointing at a byte range in some
   prior `releases/<timestamp>/` and reading it back. Considered and rejected: coupling to a
   specific previous release is fragile — that release might already be pruned (T23's
   `retain_releases`) or might not exist at all (fresh checkout) — and the cache is the single
   source of truth this way, with no timing dependency on deploy/prune/rollback state.

## What's still deferred and why

- The `[include]` dependency gap (point 5 above).
- Cache storage is a single JSON file with full HTML bodies inline — fine at this project's
  scale, but a very large corpus would eventually want per-document files instead of one
  growing JSON blob. Not addressed; nothing in SPEC asks for it explicitly.
- No duration/timing is reported anywhere yet (NFR-1's "reports its own duration") — the CLI
  reports document/route counts and now cache-reuse counts, but not wall-clock time.

## Recommended next step

M3 is done. The next phase is **M4 — Cutover** (T25: legacy URL enumeration tooling, T26:
legacy snapshot support, T27: Apache vhost config + systemd units + one-time `public` symlink
setup) — SPEC §15.5 and §19 item 5 are the relevant sections, and this phase is explicitly
about `blog.silverday.de` already serving content that P1 needs to not silently 404 during the
gap before P3 (import) exists. Alternatively, **M5 — Admin** (T28 onward) doesn't depend on M4
and could be picked up first if cutover planning needs more input (§15.5's own open item: which
of the three options — freeze-and-serve, delayed cutover, accept-the-gap — is chosen) before
T25-27 make sense to start.
