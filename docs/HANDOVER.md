# Session Handover — 2026-09-09 (T23)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T23 (atomic deploy, release pruning, `--rollback`) on top of T1-T22 from earlier
sessions. `docs/SPEC.md` and `docs/BUILD-ORDER.md` remain authoritative — this file explains
the *why* behind decisions those documents don't fully capture, and what to do next. Safe to
delete once it goes stale.

## Current state

- `make check` is green: 413 tests, 813 assertions, PHPStan level 8, PSR-12 + escaping lint.
- BUILD-ORDER.md status: T1–T16, T18–T23 are `[x]`. T17 is still `[ ]` (unchanged "T17
  progress" note — this is the oldest open gap in the tree and worth picking up next, see
  below). T23 has its own note in BUILD-ORDER.md — read it before assuming the design matches
  an early guess; one thing changed shape mid-session (see decision 2 below).
- `bin/cuniform build` (no `--dry-run`) now actually deploys: it writes the release, verifies
  it (unchanged from T22), atomically swaps `public/` onto it via `ReleaseDeployer`, and prunes
  releases beyond `build.retain_releases`. `bin/cuniform build --rollback` is real too — it
  re-points `public/` at the release before the current one, under the same `BuildLock` a build
  itself would hold.
- New file: `src/Build/ReleaseDeployer.php`, with its own test suite
  `tests/Build/ReleaseDeployerTest.php`.

## Decisions made this session that a future reader should know about

1. **The swap uses PHP's `symlink()` + `rename()` directly, not a shelled-out `mv -T`.**
   SPEC §10.4 shows `ln -sfn "releases/$TS" public.new && mv -T public.new public` as the
   *reference* atomicity mechanism; `rename()` onto an existing path is already the single
   filesystem operation that gives `mv -T` its atomicity, so there's no reason to `proc_open` a
   shell command for it. Verified this actually holds under concurrent access, not just assumed
   it: `ReleaseDeployerTest::testConcurrentReadsDuringRepeatedDeploysNeverSeeAMissingOrPartialPage`
   spawns a separate PHP process via `proc_open` that reads through the live `public/` symlink
   in a tight loop while the test process alternates deploying two different releases for a
   full second. Zero failures recorded is the assertion — about as close to "a loop requesting
   a page during a deploy never sees a 404 or a partial page" (T23's literal acceptance
   wording) as a single-host unit test gets without standing up Apache.
2. **`prune()`'s first draft had a "never delete whatever `public/` currently points at" branch
   for rollback's sake — removed, because it was dead code.** `prune()` only ever runs inside
   `deploy()`, immediately after `swap()`. By the time it runs, `public/` already points at the
   release `deploy()` was just called with — which is always the newest release on disk by
   construction (its directory name is `date('YmdHis')` at write time) — so it's always inside
   the retained window regardless of any special-casing. This was caught by writing the test
   for the scenario first (rollback to an old release, then deploy a new one, assert the old
   one survives) and watching it fail for the *opposite* reason expected: the old release *did*
   get pruned, correctly, because by the time `prune()` ran it was no longer live — the new
   deploy had already superseded it. Kept the corrected test
   (`testRollbackNeverPrunesAndCanReachAReleaseOutsideTheRetainWindow`) as documentation of the
   actual ordering guarantee: rollback itself never prunes, but a *later* deploy will still
   prune whatever the rollback pointed at once something newer supersedes it.
3. **An empty real directory at `public/` is replaced silently; a non-empty one is refused, not
   deleted.** SPEC §10.4's "one-time setup" paragraph covers a real directory left by
   provisioning, and separately (§3.3, §15.5) a real directory holding an intentionally frozen
   legacy site during cutover. This session's read: an *empty* directory has nothing to lose,
   so `ReleaseDeployer` clears it out of the way itself (this is also what let every existing
   test fixture's pre-created empty `public/` directory keep working unmodified — see decision
   4). A *non-empty* directory might be exactly the cutover freeze content SPEC §15.5 describes,
   and deciding what to do with it is explicitly T27's job ("one-time `public` symlink setup"),
   not something to guess at inside a deploy call — `ReleaseDeployer` throws instead, naming
   §10.4/§3.3/§15.5 in the message.
4. **Two existing test helper `removeDirectory()` methods (in `BuildPipelineTest` and
   `ApplicationTest`) had a latent bug that this task's symlinks exposed.** Both followed
   `is_dir()` without checking `is_link()` first, so once `public/` became a real symlink to a
   release directory, teardown would recurse *through* the symlink and delete the release
   directory's actual contents, then fail to `rmdir()` the symlink itself (leaving PHP
   warnings, though tests still passed). Fixed both to check `is_link()` before `is_dir()` and
   `unlink()` a symlink rather than recursing into its target — same fix in both files, not
   factored into a shared helper (matches this codebase's existing pattern of each test class
   carrying its own private copy rather than a shared test-support trait).
5. **CLI messaging changed:** a plain build's second line went from `cuniform: deploy is not
   implemented yet (...) — public/ was not updated` to `cuniform: deployed -> <public path>`.
   `--rollback` no longer prints "not implemented" and returns 1 unconditionally — it now does
   the real thing and returns 0 on success, 1 with a message naming the reason on failure (no
   symlink yet, or no release before the current one).

## What's still deferred and why

- T17's remaining templates (`index.php`, `tag.php`, `series.php`, `archive.php`, `search.php`,
  `404.php`, `feed.xml.php`, `post-card`/`pagination` partials) — untouched this session.
  `InternalLinkChecker`'s bare-language-home-link carve-out (documented in the T22 note) still
  applies and still can't be removed until `index.php` exists.
- `lang-switcher.php`'s disabled/home-link state for an untranslated document (SPEC §7.7).
- Tag/series indexes and prev/next (SPEC §10.1's Resolve stage lists them; still no consumer).
- T24 (incremental build cache) — every build is still a full build. `retainReleases` was the
  only piece of `BuildSettings` left unused before this session; nothing else in config is
  sitting idle waiting for a task the way that was.

## Recommended next step

T17's remaining templates are the natural next step — they're the oldest gap in the tree (open
since M3 started), they're what several other tasks' notes are waiting on (the
`InternalLinkChecker` carve-out from T22, real nav-list construction referenced in T17's own
"progress" note), and T18/T19/T20 already built the aggregated corpus data (nav trees, tag/series
indexes are the one exception — still not built, see above) those templates need. After that,
T24 (incremental builds) and T25-27 (cutover/hosting) are the remaining M3/M4 work; neither
blocks on the other.
