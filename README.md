# Cuniform

A database-less, optionally multilingual blog and page engine in PHP 8.3. Markdown files on
disk, in git, are rendered to static HTML by a build step; Apache serves the result with no PHP
in the public request path. No framework, no database, no runtime dependencies — Composer is
dev tooling only, and `vendor/` is never deployed.

The name is deliberate — one `e`, not *Cuneiform* (that's a different, unrelated project). See
`docs/SPEC.md` §16 if you're wondering whether it's a typo. It isn't.

The full design rationale lives in `docs/SPEC.md`; this file is the practical "how do I run
this" companion.

## Status

Core engine, languages, templates, the build pipeline, and the WordPress importer are built and
covered by an automated quality gate (see [Development](#development) below). The admin web UI
(P2 — auth, browser-based editor, media library) is **not implemented**; for now, all authoring
goes through git (`docs/SPEC.md` §12, "Path A"). `docs/BUILD-ORDER.md` tracks task-by-task
status against `docs/SPEC.md` if you want the specifics of what's done and what isn't.

| Area | State |
|---|---|
| Content model, renderer, shortcodes | Done |
| Languages (multilingual routing, hreflang, UI strings) | Done |
| Templates, build pipeline, atomic deploy, rollback, incremental builds | Done |
| Apache vhost / systemd units / one-time `public` setup | Built, not yet verified against a live host |
| Admin web UI (auth, editor, preview, media library) | Not started |
| WordPress import (WXR parse, convert, verify, review workflow) | Done |
| Media downloader for imported WordPress attachments | Not started |

## Requirements

- PHP 8.3+ (CLI), with `ext-intl` recommended (a documented fallback exists if it's absent)
- Composer, for development only
- Apache with `mod_rewrite`, `mod_headers`, `mod_alias` for deployment
- git

## Getting started

```bash
git clone https://github.com/SilverDay/cuniform.git
cd cuniform
composer install                       # dev tooling only: PHPUnit, PHPStan, php-cs-fixer
cp config/site.example.php config/site.php
```

Edit `config/site.php` — every key is validated at build time (`docs/SPEC.md` §7.1, §8.1), so a
bad value fails loudly rather than silently. At minimum, set `base_url` and decide `languages`
(one entry is a valid, fully-supported single-language site).

```bash
php bin/cuniform build --dry-run       # validates everything without writing output
php bin/cuniform build                 # writes releases/<timestamp>/ and deploys it to public/
```

A fresh checkout has no content yet beyond the example legal-page templates
(`content/pages/*/*.example.md` — copy one, drop the `.example` suffix, fill in the real
details; `docs/SPEC.md` §6.4). Add a post under `content/posts/<lang>/<year>/`, or a page under
`content/pages/<lang>/`, then build again.

## CLI reference

```
cuniform build [--full] [--dry-run] [--allow-url-scheme-change]
cuniform build --rollback
cuniform legacy-urls <base-url> [--output=<path>] [--max-pages=<n>]
cuniform setup-public
cuniform import-wxr <path-to-export.xml> [--output-dir=<path>] [--review-file=<path>]
cuniform review-status [--file=<path>]
cuniform review-mark <source-id> <pending|keep|reject> [--file=<path>]
```

- **`build`** runs the full pipeline (lock → discover → parse → resolve → render → template →
  emit → verify → deploy). `--dry-run` does everything except write and deploy — useful in CI or
  before a push. `--full` bypasses the incremental cache. `--rollback` re-points `public/` at
  the previous release.
- **`legacy-urls`** crawls a live site (its sitemap if it has one, a same-host spider otherwise)
  and writes a URL inventory — one input to planning a cutover from an existing site
  (`docs/SPEC.md` §15.5).
- **`setup-public`** is the one-time step that turns a freshly provisioned `public/` directory
  into the symlink the build's atomic deploy expects.
- **`import-wxr`** reads a WordPress eXtended RSS export, converts each eligible post to a
  Cuniform document, and stages the result under `var/import/` — **never directly into
  `content/`**. It also writes a migration report and a review checklist.
- **`review-status`** / **`review-mark`** work that checklist: every imported document is
  reviewed before it ships (`docs/SPEC.md` §A.5), and the checklist survives across sessions —
  mark documents `keep` or `reject` as you go, then copy the ones you kept into `content/`
  yourself once satisfied.

## Development

```bash
make check   # lint (PSR-12 + escaping lint) + PHPStan level 8 + PHPUnit — run before every commit
make test    # PHPUnit only
make stan    # PHPStan only
make lint    # php-cs-fixer (dry-run) + templates/ escaping lint
```

`vendor/` is never required at runtime — `bin/cuniform` works against a hand-rolled PSR-4
autoloader (`src/autoload.php`) even with `vendor/` deleted; only `make check` and Composer
itself need it.

## Layout

```
src/            engine (PSR-4 Cuniform\), hand-rolled autoloader, no Composer autoload at runtime
src/Render/     render adapter + the owned Md2Html fork (CHANGELOG-FORK.md tracks provenance)
templates/      plain-PHP templates, escaped by default
config/         site.php (gitignored — copy from site.example.php), lang/<code>.php
content/        posts/<lang>/<year>/, pages/<lang>/, media/, redirects.map
bin/cuniform    CLI entry point
deploy/         Apache vhost, systemd units, git post-receive hook (see below)
tests/          PHPUnit, mirrors src/
docs/           SPEC.md (authoritative) and BUILD-ORDER.md (task-by-task status)
```

## Deployment

`deploy/` holds the artifacts a real deployment needs, none of them wired up automatically:

- `deploy/apache/blog.silverday.de.conf` — the vhost (`docs/SPEC.md` §3.2): the ACME
  challenge webroot kept outside the release tree, per-language and neutral 404 handling, the
  response headers §14 requires, and the `/admin` proxy block (inert until P2 exists).
- `deploy/git/post-receive` — the git-push authoring path (`docs/SPEC.md` §12, Path A) and, for
  now, the *only* wired-up build trigger: pushing to the server's own checkout runs
  `bin/cuniform build` synchronously and relays its output back to the pushing client.
- `deploy/systemd/cuniform-build.{service,path,timer}` — the shared build consumer for the
  *other* two triggers §10.5 describes. `.timer` fires every 15 minutes and is useful today, git
  push alone included: it's what actually publishes a `status: scheduled` post once its date
  arrives, with no admin app required. `.path` watches for `var/build-requested`, a request file
  only the not-yet-built admin app (T32) will ever write — install it now if you like, but it
  stays permanently idle until that piece exists.

On a fresh host: run `cuniform setup-public` once (a freshly provisioned `public/` is usually a
real directory; this turns it into the symlink the deploy step swaps), install the vhost and
systemd units, then push content or run `cuniform build` directly.

## Importing from WordPress

If you're migrating an existing WordPress site, export via *Tools → Export → All content*
(WXR/XML), then:

```bash
cuniform import-wxr export.xml
cuniform review-status
cuniform review-mark <source-id> keep      # or reject
# copy the ones you kept into content/posts/<lang>/, then build
```

Nothing imported ships automatically — `docs/SPEC.md` §A.5 requires each document to be
reviewed (link targets, leftover shortcodes, tracking pixels, and whether it's still worth
keeping) before it's promoted into `content/`. See `docs/SPEC.md` Appendix A for the full
pipeline and `docs/BUILD-ORDER.md`'s T34–T39 notes for implementation-level detail.

## License

MIT — see `LICENSE`.
