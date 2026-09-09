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

`deploy/` holds the artifacts a real deployment needs (`docs/SPEC.md` §3, §10.5, §15.1). None
of it is wired up automatically — the steps below take a host from nothing to serving traffic.
The commands assume `/srv/vhosts/example.com` as the vhost root; substitute your own domain and
path throughout, and see `docs/SPEC.md` §3 for the full directory rationale.

### 1. Users and directory layout

```bash
useradd --system --home-dir /srv/vhosts/example.com --shell /usr/sbin/nologin cuniform-build
useradd --system --home-dir /srv/vhosts/example.com --shell /usr/sbin/nologin cuniform-web

mkdir -p /srv/vhosts/example.com
cd /srv/vhosts/example.com
git clone https://github.com/SilverDay/cuniform.git .
cp config/site.example.php config/site.php   # then edit it

mkdir -p var/log acme content/media
chown -R cuniform-build:cuniform-build /srv/vhosts/example.com
sudo -u cuniform-build php bin/cuniform setup-public   # turns a fresh public/ into the deploy symlink
```

`cuniform-build` owns `content/`, `releases/`, `var/`, and the `public` symlink and is who the
build actually runs as (below); `cuniform-web` is reserved for the future admin app (P2, not
built yet) and needs no privileges today.

### 2. Git-push authoring (`deploy/git/post-receive`)

```bash
git config receive.denyCurrentBranch updateInstead
ln -s ../../deploy/git/post-receive .git/hooks/post-receive
chmod +x deploy/git/post-receive
```

A normal (non-bare) checkout with `updateInstead` is what lets a plain `git push` update this
same working tree directly — the hook's only remaining job is running `bin/cuniform build` and
relaying its output.

### 3. Apache vhost

`deploy/apache/blog.silverday.de.conf` is the real config this project runs — copy and adapt
it directly rather than retyping it. The shape, generalized:

```apache
# :80 exists only for Let's Encrypt's HTTP-01 challenge.
<VirtualHost *:80>
    ServerName example.com
    Alias /.well-known/acme-challenge/ /srv/vhosts/example.com/acme/
    <Directory /srv/vhosts/example.com/acme>
        Options None
        AllowOverride None
        Require all granted
    </Directory>
    RedirectMatch 301 ^(?!/\.well-known/acme-challenge/)(.*)$ https://example.com$1
</VirtualHost>

<VirtualHost *:443>
    ServerName example.com
    DocumentRoot /srv/vhosts/example.com/public   # a symlink, not a real directory (SPEC §3)

    Alias /.well-known/acme-challenge/ /srv/vhosts/example.com/acme/
    <Directory /srv/vhosts/example.com/acme>
        Options None
        AllowOverride None
        Require all granted
    </Directory>

    # Both blocks declared — Apache follows symlinks only when the
    # *relevant* directory's Options permit it, and DocumentRoot itself
    # being a symlink makes that ambiguous otherwise.
    <Directory /srv/vhosts/example.com/public>
        Options FollowSymLinks
        AllowOverride None
        Require all granted
        <FilesMatch "\.(php|phtml|phar)$">
            Require all denied
        </FilesMatch>
    </Directory>
    <Directory /srv/vhosts/example.com/releases>
        Options FollowSymLinks
        AllowOverride None
        Require all granted
        <FilesMatch "\.(php|phtml|phar)$">
            Require all denied
        </FilesMatch>
    </Directory>

    RedirectMatch 302 ^/$ /en/            # your default_language; SPEC §7.4.1 — 302, not 301
    ErrorDocument 404 /404.html
    <Location "/en/">
        ErrorDocument 404 /en/404.html    # one per configured language
    </Location>

    Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'; object-src 'none'"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
    Header always set Cross-Origin-Opener-Policy "same-origin"
    Header always set X-Frame-Options "DENY"

    <FilesMatch "\.[0-9a-f]{8}\.(css|js)$">
        Header always set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
    <FilesMatch "\.html$">
        Header always set Cache-Control "public, max-age=600, must-revalidate"
    </FilesMatch>
</VirtualHost>
```

Requires `mod_rewrite`, `mod_headers`, `mod_alias`, `mod_ssl`. Install with
`a2ensite`/`apache2ctl configtest`/`systemctl reload apache2` (or your distro's equivalents),
then run `certbot --apache -d example.com` against the `:80` vhost — it inserts the certificate
directives and `certbot renew` keeps them current without touching anything above. The `/admin`
`Alias`/proxy block from `deploy/apache/blog.silverday.de.conf` is omitted above since it's
inert until the admin app (P2) exists; add it back when that's built.

### 4. systemd units

```bash
for unit in cuniform-build.service cuniform-build.path cuniform-scheduled-build.timer; do
    ln -s /srv/vhosts/example.com/deploy/systemd/$unit /etc/systemd/system/$unit
done
systemctl daemon-reload
systemctl enable --now cuniform-scheduled-build.timer   # every 15 min — publishes `status: scheduled` posts
systemctl enable --now cuniform-build.path               # idle until the admin app (T32) writes to it; harmless to enable now
```

`cuniform-build.service` (the shared consumer of both triggers above) runs as the
`cuniform-build` user, writes to `var/log/build.log`, and is sandboxed with
`ProtectSystem=strict` / `NoNewPrivileges=true`, scoped to the vhost root only.

### 5. First build

```bash
sudo -u cuniform-build php bin/cuniform build --dry-run   # validates everything, writes nothing
sudo -u cuniform-build php bin/cuniform build              # writes releases/<ts>/ and deploys it
```

From here on, `git push` to this checkout triggers a build automatically (step 2); manual and
scheduled builds both work the same way without it.

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
