# Session Handover — 2026-09-10 (T28: Admin auth)

Point-in-time snapshot for picking this work back up. This session implemented T28 (SPEC §13.1)
— the admin authentication engine and its login/logout screens — the first M5 work done. The
prior session's README/deployment-guide work (2026-09-09) is unchanged and still accurate.
`docs/SPEC.md` and `docs/BUILD-ORDER.md` remain authoritative for the engine itself; this file
explains the *why* behind decisions those documents don't fully capture, and what to do next.
Safe to delete once it goes stale.

## Build status (see BUILD-ORDER.md for task-level detail)

M1-M3 fully done. M4: T25 done, T26 not applicable (live site is down, nothing to snapshot),
T27 deliverables built but not yet verified against a live host (checkbox left open on
purpose). M6 (Import, P3) done except T36 (media downloader — skipped at the operator's own
direction; the real export has no media to validate a downloader against). **M5 (Admin): T28
done** (this session — see BUILD-ORDER.md's T28 note for the full design writeup); T29-33 not
started.

## Important operational context for future sessions

**The prior budget concern that parked M5 has been lifted.** A 2026-09-09 session recorded that
the operator didn't have budget for M5 and should not be started without confirming first (see
`[[budget_constraint_admin_panel]]` in memory). This session opened by asking directly, and the
operator said to start M5 now — that memory has been updated accordingly. Future sessions can
treat M5 as normally in-progress; no need to re-ask before continuing T29 unless a similar
constraint resurfaces.

**T29 (Editor) is next in M5's own numeric order**, and now has a real session/CSRF layer to
build on: `admin_require_session()` (admin/bootstrap.php) protects a page, and
`admin_csrf_value()`/`admin_csrf_valid()` (same file) are the CSRF primitives every
state-changing form should use, the same way login.php/logout.php already do. T29's own SHA-256
conflict detection and `proc_open`-based git commit are unrelated to T28 and start from scratch.

## Current state

- `make check` is green: 765 tests, 1524 assertions (up from 658/1321 before this session),
  PHPStan level 8 clean across `src`, `bin`, `tests`, and `admin` (the last of these newly
  added to `phpstan.neon.dist` — `admin/` had no PHP files to analyse before T28), PSR-12 +
  escaping lint clean.
- `README.md` (from the prior session) still documents what's built, but its status table now
  undersells things slightly — M5/T28 is done — worth a small update next time README is
  touched; not done this session to keep the diff focused on T28 itself.

## What's still deferred and why

- **T29-33 (the rest of M5)** — not started, no blocker beyond sequencing. T29 (editor) is
  next in BUILD-ORDER's own order and depends on T28, now satisfied.
- T36 (media downloader) — skipped at the operator's own direction; still needed as a general
  capability (SPEC §A.3), but has nothing real to validate against in this export.
- Actually promoting a `keep`-decided imported document into `content/`, or deleting a
  `reject`-decided one from `var/import/` — both explicitly out of scope in T39; left as manual
  operator actions.
- Page import, the broader category/tag-archive/feed/date-archive redirect generation, and the
  bracket/Markdown-character escaping gap — all earlier decisions (T35/T37), unchanged.

## Recommended next step

**T29** (Editor with front matter form, SHA-256 conflict detection, git commit via
`proc_open`, SPEC §12) is the natural next task — it depends on T28, which is now done, and
BUILD-ORDER's own M5 table has nothing else unblocked ahead of it (T31/T32 also depend on T28
but are listed after T29). Two independent, smaller options remain live too if T29 isn't what's
wanted next:

1. **A content decision, not a coding task**: run `cuniform review-status` against the real
   WXR import, mark each of the six real documents `keep` or `reject` per SPEC §A.5's checklist,
   copy the kept ones into `content/posts/en/`, and run a real `bin/cuniform build` — this is
   the first point imported content actually goes live, and P1's build/deploy pipeline is fully
   ready for it today, with no admin app required (authoring is git-push only, per SPEC §12
   Path A, until P2 exists).
2. **T36** (media downloader) if there turns out to be real media to import after all, or if
   it's worth building ahead of need.
