# Session Handover — 2026-09-09 (T39)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T39 (manual review tracking file and checklist workflow, SPEC §A.5) on top of
T1-T38. **M6 (Import, P3) is now done except T36** (media downloader), skipped at the
operator's own direction since the real export has no media to validate a downloader against.
M1-M3 fully done; M4: T25 done, T26 not applicable, T27 deliverables done (checkbox open
pending live verification); M6: T34, T35, T37, T38, T39 done, T36 still open; M5 (Admin) not
yet started, per this milestone's own reprioritization note. `docs/SPEC.md` and
`docs/BUILD-ORDER.md` remain authoritative — this file explains the *why* behind decisions
those documents don't fully capture, and what to do next. Safe to delete once it goes stale.

## Current state

- `make check` is green: 658 tests, 1321 assertions, PHPStan level 8, PSR-12 + escaping lint.
- New in `src/Import/`: `ReviewDecision` (enum: `pending`/`keep`/`reject`), `ReviewEntry`
  (immutable per-document review state), `ReviewChecklistBuilder` (pure: build + merge +
  display ordering), `ReviewChecklistStore` (the only I/O — JSON read/write).
- `bin/cuniform import-wxr` now also builds, merges, and saves a review tracking file
  (`var/import-review.json` by default, `--review-file=<path>` to override) on every run.
- Two new commands: `review-status [--file=<path>]` (prints SPEC §A.5's 5-point checklist once,
  then every pending document — flagged ones first) and `review-mark <source-id>
  <pending|keep|reject> [--file=<path>]`.
- Validated end-to-end against the operator's real six-item export, through the actual
  `bin/cuniform` CLI dispatch path (not just the underlying classes): 6 documents staged, 1
  flagged, marked one `keep` and one `reject`, re-ran `import-wxr` a second time, and confirmed
  both decisions survived the re-import intact.

## Decisions made this session that a future reader should know about

1. **"A checkbox per source_id" (SPEC §A.5's own words) became one `decision` field —
   `pending`/`keep`/`reject` — not a bare boolean.** SPEC's own fifth review point ("content
   genuinely worth keeping... the cheapest moment to delete posts that have not aged well") is
   itself a decision with two outcomes, not just a yes/no on whether someone looked at the
   document. There is no separate "reviewed" flag that could disagree with the decision —
   `Pending` *is* "not yet reviewed."
2. **Re-running `import-wxr` never discards review work already done.**
   `ReviewChecklistBuilder::merge()` carries a prior run's recorded decision forward onto a
   fresh build, matched by source_id — this is what makes SPEC's "an interrupted review can
   resume rather than restart" actually true across the normal fix-and-retry cycle of getting
   T38's migration report clean. Two things handled deliberately: a source_id no longer present
   in the fresh build is dropped (nothing left to review), and flags are always taken fresh,
   never carried over stale — the whole point of a re-run is often checking whether a fix
   actually cleared a flag.
3. **Ordering ("documents with flags first, clean ones batched," SPEC §A.4) is a display-time
   concern, not a stored one.** The tracking file itself stays keyed by source_id,
   order-independent; `review-status` sorts a copy only when printing, so the file's layout
   never needs to be re-derived from an order that would otherwise go stale as decisions
   accumulate across sessions.
4. **`review-mark reject` only records the decision — it does not delete the staged file**, and
   `keep` does not copy the document into `content/`. Both stay manual actions the operator
   takes themselves, the same boundary T37's CLI output already states. T39's job is tracking
   the decision, not acting on it.
5. **`review-status` prints SPEC §A.5's own five-point checklist as a header, once**, before
   listing pending documents — this is what makes it a checklist workflow rather than a bare
   data dump ("it needs a checklist rather than a skim," SPEC's own words).

## What's still deferred and why

- T36 (media downloader) — skipped in task order at the operator's own direction; still needed
  as a general capability (SPEC §A.3), but has nothing real to validate against in this export.
- Actually promoting a `keep`-decided document into `content/`, or deleting a `reject`-decided
  one from `var/import/` — both explicitly out of scope this session (decision 4 above); left
  as manual operator actions, small and separate to automate later if it becomes worth it.
- Page import, the broader category/tag-archive/feed/date-archive redirect generation, and the
  bracket/Markdown-character escaping gap — all earlier decisions (T35/T37), unchanged.

## Recommended next step

**M6 is functionally done for this operator's real corpus** — every real post has run the full
pipeline (parse → convert → emit front matter → verify → track for review) at least once, with
zero hard failures. The honest next step is a **content decision, not a coding task**: go
through `cuniform review-status`, mark each of the six real documents `keep` or `reject` per
SPEC §A.5's checklist, then manually copy the kept ones into `content/posts/en/` and run a real
`bin/cuniform build` against them — this is the first point where imported content actually
becomes visible on the site, and it's deliberately not something either T37 or T39 automated
(SPEC §A.5's manual-review control is the whole point). If more coding work is wanted first
instead: **T36** (media downloader) is the only open M6 task, with no real data to validate
against; **M5** (Admin: T28-33) is next in BUILD-ORDER's original order and hasn't been touched
since before the cutover reprioritization.
