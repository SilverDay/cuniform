# Session Handover — 2026-09-09 (T27)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T27 (Apache vhost config, systemd units, one-time `public` symlink setup) on top
of T1-T25 from earlier sessions (M1-M3 fully done; M4 in progress). `docs/SPEC.md` and
`docs/BUILD-ORDER.md` remain authoritative — this file explains the *why* behind decisions
those documents don't fully capture, and what to do next. Safe to delete once it goes stale.

## Current state

- `make check` is green: 518 tests, 1023 assertions, PHPStan level 8, PSR-12 + escaping lint.
- BUILD-ORDER.md status: M1-M3 all `[x]`. M4: T25 `[x]`, **T27's deliverables are complete but
  its checkbox is deliberately left `[ ]`** — see below, this is not an oversight. T26 is still
  `[ ]` and still blocked on the same open decision as last session (SPEC §15.5's three-option
  cutover choice).
- New top-level directory, not PHP: `deploy/` — `deploy/apache/blog.silverday.de.conf`,
  `deploy/systemd/{cuniform-build.service,cuniform-build.path,cuniform-scheduled-build.timer}`,
  `deploy/git/post-receive`. None of it is wired into `make check` (correctly — php-cs-fixer
  and phpstan are both scoped away from `deploy/`, confirmed by reading their own configs).
- New CLI command: `bin/cuniform setup-public` — the one-time `public/` provisioning step.

## Decisions made this session that a future reader should know about

1. **T27's checkbox is left unchecked on purpose, not by mistake.** Its acceptance criterion
   ("ACME renewal succeeds across a deploy — verify by forcing a renewal...") requires a real
   ACME certificate authority round-trip against a live, DNS-resolvable host — categorically
   impossible to produce from this environment (no live domain, no running Apache/systemd
   bound to `blog.silverday.de`, no certbot session). This is different from, say, T23's
   atomicity claim, which a local `proc_open` loop against a real filesystem could faithfully
   reproduce. Per this project's own rule ("do not mark a task done until its acceptance
   criteria pass"), the checkbox stays `[ ]` until someone actually deploys this and verifies
   it against the real host — read BUILD-ORDER's T27 status note for exactly what to check and
   how (`curl -I` against a couple of 404 paths, in addition to the ACME renewal itself).
2. **Everything mechanically verifiable in this environment, was.** This host happens to have
   both Apache and systemd tooling installed (not guaranteed for a coding sandbox, but true
   here), which was unexpectedly useful: `apache2ctl -t` against a scratch config pulling in
   the vhost file returned `Syntax OK` (with the real system's mod_rewrite/mod_headers/
   mod_alias/mod_proxy_fcgi/mod_ssl all loaded), and `systemd-analyze verify` against all three
   unit files returned clean — the latter also confirms `ExecStart`'s binaries genuinely
   resolve on this host (`/usr/bin/php`, `/bin/rm`). Worth doing this again if the deploy/
   files change later; both tools are available and it costs nothing.
3. **A port-80 vhost was added beyond SPEC §3.2's own literal block.** That block only shows
   `<VirtualHost *:443>`, with the ACME alias inside it — but Let's Encrypt's standard HTTP-01
   challenge validates over plain HTTP. Without a port-80 vhost carrying the same
   `/.well-known/acme-challenge/` alias, "ACME renewal succeeds" isn't achievable via the
   standard method at all. Documented explicitly as an addition, not a literal transcription,
   in the vhost file's own header comment.
4. **`var/build-requested` is a convention this session invented, not something already
   defined anywhere.** SPEC §10.5 says "Admin writes a request file; a systemd path unit runs
   the build" but never names the file, because the admin app that would write it doesn't
   exist yet (T29-33, M5). `cuniform-build.path` watches this exact path; T32 ("Build enqueue
   via request file, consumed by the systemd unit" — its own BUILD-ORDER line already names
   T27 as a dependency) is what will make the admin app actually write to it. Whoever picks up
   T32 needs this path, not a different one.
5. **`PublicDirectorySetup` moves a non-empty `public/` aside — it does not decide what to do
   with the content.** This mirrors T23's `ReleaseDeployer` refusal for the same case (that
   refusal's own message already pointed at "T27 owns this"). Deciding whether the moved-aside
   content becomes T26's freeze-and-serve source, gets archived, or gets discarded is exactly
   the still-unresolved §15.5 decision — not something a one-time setup script should guess.
   Directly relevant, not hypothetical: this checkout's own `/srv/vhosts/blog.silverday.de/
   public/` is *right now* a real, non-empty, differently-owned directory (hosting
   provisioning's default `index.html`, owned by `php-blog-silverday-de:www-data`, not the
   session's own user) — exactly the case this tool exists for. It was left untouched this
   session; running `setup-public` for real against it is a live operational decision for you,
   not something to do unasked from inside a coding session.
6. **The git-push trigger relies on `receive.denyCurrentBranch updateInstead`
   (git >= 2.4), not a bare repo + `git checkout -f` in the hook.** Considered and rejected:
   P2's admin app (§12, Path B) also commits directly to the *same* working tree later —
   a separate bare repo would mean reconciling two working trees (the bare repo's checkout
   target and the admin app's live one) instead of just one. `updateInstead` lets `git push`
   update the checked-out branch directly, so there's only ever one working tree, and the
   `post-receive` hook's whole job reduces to "run the build and let its own output relay to
   the pushing client" — no checkout logic of its own.

## What's still deferred and why

- T27's live-server ACME verification (point 1 above) — the actual remaining work, once this
  is deployed for real.
- T26 (legacy snapshot support) — still blocked on SPEC §15.5's open cutover-option decision,
  same as last session.
- An FPM pool config for `/admin` (referenced by the vhost's own `SetHandler` line pointing at
  `cuniform-admin.sock`) was deliberately not built this session — the admin app it would serve
  doesn't exist yet (T28+), and a pool config tightly coupled to a nonexistent application's
  actual needs (PHP version, chroot, user) would just be guessing ahead of a consumer, the same
  reasoning this project has applied repeatedly to other tasks (e.g. ResolvedSite deferring
  listing aggregation until T17 existed).

## Recommended next step

Two independent paths, neither blocking the other:

- **Deploy T27 for real and close its checkbox** — install the vhost, the systemd units
  (`daemon-reload`, enable `cuniform-build.path` and `cuniform-scheduled-build.timer`), the
  git hook (symlink + `receive.denyCurrentBranch updateInstead`), run `bin/cuniform
  setup-public` against the real `public/` (after deciding what its current content means for
  §15.5 — see point 5 above), then force an ACME renewal during a real build to verify the
  actual acceptance criterion.
- **T26** still needs the same §15.5 decision as before to make sense to start. **M5 — Admin**
  (T28 onward) remains independent of that decision and is the other reasonable place to pick
  up next.
