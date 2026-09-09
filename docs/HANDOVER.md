# Session Handover — 2026-09-09 (T38)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T38 (verification: count reconciliation, URL diff, word-count tolerance, migration
report) on top of T1-T37 — T36 (media downloader) remains deliberately skipped in sequence, at
the operator's own direction, since the real export has no media to validate a downloader
against. M1-M3 fully done; M4: T25 done, T26 not applicable, T27 deliverables done (checkbox
open pending live verification); M6: T34, T35, T37, T38 done, T36/T39 still open. `docs/SPEC.md`
and `docs/BUILD-ORDER.md` remain authoritative — this file explains the *why* behind decisions
those documents don't fully capture, and what to do next. Safe to delete once it goes stale.

## Current state

- `make check` is green: 632 tests, 1271 assertions, PHPStan level 8, PSR-12 + escaping lint.
- New in `src/Import/`: `ImportVerifier`, `ImportReport`, `LegacyPermalink`. `WxrImporter`
  itself is unchanged except that its `buildAliases()` now calls `LegacyPermalink::realPathOf()`
  instead of inlining the same `parse_url()` logic — a pure refactor, same behavior, confirmed
  by the full existing `WxrImporterTest` suite still passing unmodified.
- `bin/cuniform import-wxr` now runs `ImportVerifier::verify()` between import and write. A hard
  failure (see decision 1) blocks the write entirely — nothing partial ever lands in the staging
  directory — and a migration-report summary prints after the existing warning lines.
- Validated against the operator's real six-item export: zero hard failures, `countsByStatus`
  = 4 `publish` + 2 `draft` (matches T34's pre-work), every §A.4-named report bucket empty
  except `otherNotices` holding exactly the one already-known item (T37's `post_id=180`
  empty-slug fallback) — T38 surfaced nothing new the earlier tasks hadn't already found.

## Decisions made this session that a future reader should know about

1. **"Count reconciliation... any delta is a hard failure" (§A.4) is scoped to output-path
   collisions, not a re-derivation of `WxrImporter`'s own partition logic.** SPEC's wording
   assumes a second, external system to reconcile against — this migration doesn't have one;
   the WXR file is the only source of truth, and `WxrImporter` already accounts for every item
   it reads by construction (each loop iteration either appends one document or takes an early
   `continue`). Re-deriving that accounting a second time in `ImportVerifier` would only risk a
   second copy of the same status/slug/date rules silently drifting from the first. The one
   place a genuine count delta can still occur is two *different* items resolving to the same
   output file path (a slug+date collision) — `ImportedDocumentWriter` writes by path, so a
   collision silently overwrites one document with another. `ImportVerifier` catches exactly
   this and throws (`ImportException`) before anything is written; `tests/fixtures/Import/
   colliding.xml` exercises it end-to-end through the CLI.
2. **"URL diff between the old sitemap and the built site" is adapted to this migration's own
   cutover reality** — there's no old sitemap (the live site is down, §19 item 4) and no built
   site yet either (imports are staged, not shipped). The adapted check: every `post`/`page`
   item with a real pretty-permalink that did *not* get imported is surfaced as an unresolved
   legacy URL — worth a human decision before it's silently lost, rather than nothing. An
   imported item is out of scope here; it already carries its own alias (T37), and whether that
   alias actually resolves is `BuildVerifier`'s (T22) job once a real build runs, not this one's.
3. **Word-count tolerance is 20%, a documented judgment call** (SPEC names no number). Original
   word count comes from `content:encoded` stripped of tags; converted word count comes from
   the imported document's own body, recovered via the real `FrontMatterParser` (T4/T37) rather
   than re-deriving front-matter-stripping logic. Wide enough to absorb normal conversion noise,
   tight enough to catch a post that lost most of its content.
4. **`footnotes` and `failedMediaDownloads` are real, always-empty fields on `ImportReport`, not
   omitted** — SPEC §A.4 names both explicitly, so the fields exist for forward compatibility:
   no footnote-specific detection exists (a footnote plugin's shortcode would still surface
   generically under `unknownShortcodes`), and T36 (media downloader) isn't built, so there's
   nothing to attempt a download with, let alone fail one.
5. **`LegacyPermalink` was pulled out of `WxrImporter::buildAliases()`** once `ImportVerifier`
   needed the identical "is this a real pretty-permalink path, not a `?p=123` query string" rule
   for its own URL diff. Kept single-sourced rather than duplicated — the two checks silently
   drifting apart on what counts as a real old URL would be exactly the kind of bug T38 exists
   to catch in the first place.
6. **Idempotency needed no new code**, only a test proving it: nothing in `src/Import/` reads
   the clock or generates randomness (confirmed by `grep`, not assumed).
   `ImportVerifierTest::testRunningTheImportTwiceOnTheSameExportProducesByteIdenticalDocuments`
   runs `WxrImporter` twice against the same in-memory export and asserts byte-identical output.

## What's still deferred and why

- T36 (media downloader) — skipped in task order at the operator's own direction; still needed
  as a general capability (SPEC §A.3), but has nothing real to validate against in this export.
- T39 (manual review tracking file and checklist workflow) — not built yet; it's the natural
  next step now that T38 gives it a clean report to work from.
- Page import, the broader category/tag-archive/feed/date-archive redirect generation, and the
  bracket/Markdown-character escaping gap — all T35/T37 decisions, unchanged this session.

## Recommended next step

**T39** (manual review tracking file and checklist workflow, SPEC §A.5) is the natural next
step — it's the last M6 task before the import pipeline is genuinely done, and it has a
concrete, real target to build against: six real `source_id`s (10, 11, 15, 35, 66, 180) that a
review-tracking file needs to carry checkboxes for. §A.5 itself gives the checklist contents
almost verbatim (shortcode attribute provenance, external link targets, leftover verbatim
shortcodes, tracking pixels, "worth keeping" — five per-document checks), so this is mostly a
translation task, not a design one. **T36** (media downloader) remains the one task in this
milestone with no real data to validate against — worth building whenever convenient, not
blocking anything else in M6.
