# Session Handover — 2026-09-09 (README + deployment guide)

Point-in-time snapshot for picking this work back up. Code-wise, nothing changed since T39 —
this session's work was entirely documentation: the stale scaffold `README.md` (written before
T1 existed, describing the repo as containing "no implementation code by design") was replaced
with one that actually describes the built project, then expanded into a full step-by-step
deployment guide (system users, directory bootstrap, git-push wiring, a generalized sample
Apache vhost, systemd unit installation, first build). Both are pushed to `origin/main`.
`docs/SPEC.md` and `docs/BUILD-ORDER.md` remain authoritative for the engine itself; this file
explains the *why* behind decisions those documents don't fully capture, and what to do next.
Safe to delete once it goes stale.

## Build status (unchanged since T39 — see BUILD-ORDER.md for task-level detail)

M1-M3 fully done. M4: T25 done, T26 not applicable (live site is down, nothing to snapshot),
T27 deliverables built but not yet verified against a live host (checkbox left open on
purpose). **M6 (Import, P3) done except T36** (media downloader — skipped at the operator's own
direction; the real export has no media to validate a downloader against). **M5 (Admin) not
started** — see the important note below on why that's expected to stay true for a while.

## Important operational context for future sessions

**The operator does not have enough remaining usage budget to build the admin panel (M5:
T28-33) right now.** This was stated explicitly this session ("We do not have enough usage left
for the admin panel, so I want you to replace the current readme..."). M5 is by far the largest
remaining milestone — auth with TOTP, a browser editor, preview rendering, a media library, an
audit log — and building it properly (with the same test coverage and real-corpus validation
discipline every other milestone in this project got) is a substantial effort.

**Do not start M5 tasks (T28-33) without confirming the operator wants to spend the budget on
it.** If asked to "continue" without a specific task named, the safer assumption is doc/content
work, small fixes, or T36, not launching into T28. If genuinely unsure which task to pick up,
ask rather than guessing — this is exactly the kind of budget-sensitive call this project's own
standing rules already say to stop and check on.

## Current state

- `make check` is green: 658 tests, 1321 assertions, PHPStan level 8, PSR-12 + escaping lint —
  unchanged this session, since no source files were touched.
- `README.md` now documents: what's actually built (with an honest status table, not
  overclaiming M5), requirements, local dev setup, the full CLI reference (kept in sync with
  `Application::usage()`'s literal text), `make check`, project layout, a 5-step deployment
  walkthrough, and the WordPress import workflow.
- The sample vhost in the README's Deployment section is a **generalized, placeholder-domain
  version** of `deploy/apache/blog.silverday.de.conf` (this project's own real, hostname-specific
  file) — written so it reads as a copy-adaptable template rather than duplicating the real file
  verbatim (drift risk if kept byte-identical). The README explicitly points back to the real
  file as the authoritative one to copy from for this actual deployment.
- One thing caught and fixed while drafting: an early version of the deployment steps included
  `composer install --no-dev` in production — removed, since it contradicted this project's own
  "zero runtime dependencies, vendor/ never deployed" claim (stated at the top of the same
  README). `bin/cuniform` never touches `vendor/autoload.php`; only the hand-rolled
  `src/autoload.php` matters at runtime.

## What's still deferred and why

- **M5 (Admin, T28-33)** — not a technical deferral, a budget one (see the note above). Nothing
  about the engine blocks starting it; SPEC §13 and BUILD-ORDER's own T28-33 rows are ready to
  work from whenever the operator decides to spend on it.
- T36 (media downloader) — skipped at the operator's own direction; still needed as a general
  capability (SPEC §A.3), but has nothing real to validate against in this export.
- Actually promoting a `keep`-decided imported document into `content/`, or deleting a
  `reject`-decided one from `var/import/` — both explicitly out of scope in T39; left as manual
  operator actions.
- Page import, the broader category/tag-archive/feed/date-archive redirect generation, and the
  bracket/Markdown-character escaping gap — all earlier decisions (T35/T37), unchanged.

## Recommended next step

No coding task is queued. The two live options, in order of what actually gets the site
publishing sooner:

1. **A content decision, not a coding task**: run `cuniform review-status` against the real
   WXR import, mark each of the six real documents `keep` or `reject` per SPEC §A.5's checklist,
   copy the kept ones into `content/posts/en/`, and run a real `bin/cuniform build` — this is
   the first point imported content actually goes live, and P1's build/deploy pipeline is fully
   ready for it today, with no admin app required (authoring is git-push only, per SPEC §12
   Path A, until P2 exists).
2. **T36** (media downloader) if there turns out to be real media to import after all, or if
   it's worth building ahead of need.

M5 (Admin) stays parked until the operator explicitly says to spend budget on it.
