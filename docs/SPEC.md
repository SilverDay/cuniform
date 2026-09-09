# Cuniform — Specification

*Markdown-based blog and page engine without a database — optionally multilingual*

**Status:** Draft v0.8 — supersedes v0.7 (renamed from Stele)
**Name:** Cuniform (decided — note the spelling, §16)
**Repository:** `github.com/SilverDay/wordless` → rename to `SilverDay/cuniform`
**Host:** `blog.silverday.de` at `/srv/vhosts/blog.silverday.de/`
**Platform:** PHP 8.3 (`strict_types=1`), Apache, single vhost, no database, no framework, no runtime dependencies

---

## 1. Scope and Phasing

Cuniform publishes a personal site — optionally multilingual — consisting of **posts** (dated, chronological) and **static pages** (undated, navigational) from Markdown files on disk. Output is static HTML, produced by a build step and served by Apache with no PHP in the public request path.

### 1.1 Phases

| Phase | Contents | Ships when |
|-------|----------|------------|
| **P1 — Engine** | Content model, languages, renderer chain, templates, static pages, build pipeline, feeds, sitemap, search, CLI. Authoring via git push. | Site is publishable end-to-end from a terminal |
| **P2 — Admin** | Web UI: auth, editor, preview, media, build trigger | P1 stable |
| **P3 — Import** | WordPress migration (Appendix A) | P1+P2 stable |

Multi-language is an **optional capability, built in P1**. A Cuniform site may run with one language and look nothing like a multilingual site; what P1 must contain is the *capability*, because retrofitting it would re-cut the URL scheme, the template layer, the nav model, and every generated artifact. `blog.silverday.de` uses it with `de` and `en` (§7.1).

The WordPress import stays P3, but §15.5 (cutover) now has a dependency on it that did not exist in v0.4: the hostname is already serving content.

### 1.2 Forward-compatibility hooks reserved for P3

Implemented in P1 even though nothing uses them yet:

- `aliases` front matter key → generated redirect entries (§5.3)
- `content/redirects.map` and its Apache compilation (§8.3) — now P1, see below
- Verbatim-slug policy: a slug supplied in front matter is never re-slugified (§5.4)
- `source_id` front matter key (opaque provenance; the importer writes WordPress post IDs here)
- Configurable permalink pattern (§8.1)
- Symmetric language-prefixed URLs (§7.4), which means every legacy URL is a redirect entry rather than a preserved path — so `redirects.map` moves from a P3 convenience to a P1 requirement

### 1.3 Non-goals

Multi-tenancy, plugin architecture, comments (§14.4), e-commerce, newsletters (§15.2), and a content REST API.

---

## 2. Rendering Model

**Static publishing.** Every public URL is a pre-rendered file on disk. PHP runs only in the build pipeline (CLI) and the admin application (§13), never on a public request.

A static public surface means anonymous requests never reach the front matter parser, the shortcode layer, the template engine, or the renderer's regex machinery. A render-on-request cache would keep all of that live for anonymous traffic in exchange for identical output.

**Accepted costs:** content is live only after a build (§10); no server-side dynamic features, so search is a pre-built client-side index (§11.3); deploy must be atomic (§10.4). Multi-language adds one more: language selection is explicit rather than negotiated (§7.6).

---

## 3. Architecture — Single vhost

One hostname — `blog.silverday.de` — one vhost, following the standard vhost layout:

```
/srv/vhosts/blog.silverday.de/
├── content/        source of truth (git)   ─ not web-accessible
├── src/            engine + renderer (owned fork, §4) ─ not web-accessible
├── templates/      PHP templates            ─ not web-accessible
├── config/         site config, UI strings  ─ not web-accessible
├── var/            cache, logs, sessions    ─ not web-accessible
├── acme/           ACME challenge webroot   ─ Alias, outside releases (§3.3)
├── admin/          admin app (PHP)          ─ Alias /admin, PHP enabled
├── releases/<ts>/  build output             ─ swapped
└── public ->       releases/<ts>            ─ DocumentRoot, static only
```

`public` is the standard DocumentRoot path and is therefore the swap target: it is a **symlink**, not a directory. Everything that must survive a swap lives beside it, never inside it.

### 3.1 Why the admin app lives outside the release tree

`admin/` is aliased into the URL space, not placed inside the swapped `public` tree. If it lived in the release tree, every build would copy the running application, and the atomic swap (§10.4) would replace the admin app underneath active sessions. Keeping it outside means builds touch only content output, and the admin app is upgraded independently.

### 3.2 Apache skeleton

```apache
<VirtualHost *:443>
    ServerName blog.silverday.de
    DocumentRoot /srv/vhosts/blog.silverday.de/public

    # ACME webroot must NOT live inside the swapped tree — see §3.3
    Alias /.well-known/acme-challenge/ /srv/vhosts/blog.silverday.de/acme/
    <Directory /srv/vhosts/blog.silverday.de/acme>
        Options None
        AllowOverride None
        Require all granted
    </Directory>

    # Declare both the symlink path and its target (see note below)
    <Directory /srv/vhosts/blog.silverday.de/public>
        Options FollowSymLinks
        AllowOverride None
        Require all granted
        <FilesMatch "\.(php|phtml|phar)$">
            Require all denied
        </FilesMatch>
    </Directory>
    <Directory /srv/vhosts/blog.silverday.de/releases>
        Options FollowSymLinks
        AllowOverride None
        Require all granted
        <FilesMatch "\.(php|phtml|phar)$">
            Require all denied
        </FilesMatch>
    </Directory>

    # Root holds no content (§7.4.1) — 302, not 301
    RedirectMatch 302 ^/$ /en/

    # Neutral 404 for non-prefixed paths, per-language inside each prefix (§7.12)
    ErrorDocument 404 /404.html
    <Location "/de/">
        ErrorDocument 404 /de/404.html
    </Location>
    <Location "/en/">
        ErrorDocument 404 /en/404.html
    </Location>

    Alias /admin /srv/vhosts/blog.silverday.de/admin
    <Directory /srv/vhosts/blog.silverday.de/admin>
        Options None
        AllowOverride None
        Require all granted            # or Require ip <allow-list>
        <FilesMatch "\.php$">
            SetHandler "proxy:unix:/run/php/cuniform-admin.sock|fcgi://localhost"
        </FilesMatch>
    </Directory>
</VirtualHost>
```

`Options FollowSymLinks` (not `SymLinksIfOwnerMatch`) is required, since `public` is a symlink whose owner will not generally match the target's. `AllowOverride None` throughout — no `.htaccess` parsing, and no risk of an uploaded file becoming configuration.

**Both `<Directory>` blocks are declared deliberately.** Apache follows symlinks only when the *relevant* directory's `Options` permit it; which path counts as "relevant" when the DocumentRoot itself is a symlink is not worth taking on faith. Declaring both makes the configuration correct either way. Verify once after the first swap — request a page, confirm 200.

`ErrorDocument` inside `<Location>` is documented as valid in directory context; confirm the per-language 404 actually fires before relying on it, and fall back to a single bilingual 404 page if it does not.

### 3.3 ACME and the swap

`/.well-known/acme-challenge/` is aliased to `acme/`, **outside** the release tree. If it resolved through `public`, certificate renewal would write challenge files into whichever release is current, and a build landing during the validation window would swap them away — a failure that surfaces sixty days later as an expired certificate, not at deploy time.

Scoped to the `acme-challenge/` subpath only, so the build can still emit `/.well-known/security.txt` (RFC 9116) into the release tree as ordinary content.

### 3.4 Same-origin consequence

Admin and site share an origin, so **any XSS in a rendered page is an admin compromise**, not a defacement. Path-scoped cookies do not mitigate this: same-origin script can `fetch('/admin/')` with credentials and read the response, including CSRF tokens.

This is acceptable because every byte of content is authored or reviewed by you, and the renderer escapes all text. Two consequences follow, and both are load-bearing:

1. **§14.1 (strict CSP on public pages) is mandatory, not defence in depth.** On a two-vhost design it would be a second layer; here it is the primary control.
2. **P3 imported content is manually reviewed post-by-post before it ships** (your decision, recorded in Appendix B). The review is not a formality — it is the control that keeps the single-origin design sound once externally-influenced HTML enters the corpus. §A.5 specifies what the reviewer is actually looking for.

### 3.5 Components

| ID | Component | Runtime | Responsibility |
|----|-----------|---------|----------------|
| C1 | Content store | — | Markdown + media on disk, in git |
| C2 | Front matter parser | PHP | Split, validate, type-check metadata |
| C3 | Shortcode processor | PHP | Pre/post passes around the renderer |
| C4 | Render adapter | PHP | Wraps the renderer (§4) |
| C5 | Template layer | PHP | Plain-PHP templates, escaped by default |
| C6 | Language resolver | PHP | Language sets, translation groups, UI strings |
| C7 | Build pipeline | PHP CLI | Orchestration, incremental builds, deploy |
| C8 | Admin application | PHP-FPM | Auth, editor, preview, media, git, build enqueue |

---

## 4. Markdown Renderer

`md2html-php` was vendored as a byte-identical upstream copy through v0.8 of this spec. That
model is retired (Appendix B, #6): serious bugs surfaced upstream, found while using the
library on another project, and waiting on an upstream release cycle to fix them no longer
makes sense. The renderer is absorbed as ordinary engine code instead.

### 4.1 Ownership

The renderer lives at `src/Render/Md2Html.php`, namespaced `Cuniform\Render\Md2Html` and
PSR-4 autoloaded like every other class in `src/` — no separate `require` step, no
global-namespace exception. It is edited directly, held to the same PHPStan level 8 and
PHPUnit bar as the rest of the engine, and reviewed like any other change to `src/`.

`src/Render/CHANGELOG-FORK.md` replaces `UPSTREAM.md`. It records the upstream repository
and commit the fork started from, and a running log of every change made since: each bug fix,
with what was wrong and what changed, plus anything this spec previously described as a
proposed upstream contribution (§4.3). There is no hash check in `make check` for this file —
now that it's owned code, ordinary tests and review are the control, the same as for any
other file in `src/`.

Site-specific wiring still doesn't belong inside the renderer itself: front matter handling
and shortcode placeholder substitution stay in the adapter (C4). Owning the file didn't move
that line — it was never about vendored-vs-local, it's about general-purpose-renderer-vs-
Cuniform-specific. A bug fix or a feature any consumer of the renderer would want belongs in
`Md2Html.php`; Cuniform-only behaviour belongs in the adapter.

### 4.2 What this buys, and what it costs

Still true: no Composer at runtime, no runtime dependencies. `Cuniform\Render\Md2Html` is
autoloaded exactly like any other engine class. Composer remains a dev-only tool for PHPUnit
and PHPStan, and `vendor/` is never deployed.

What changed: there is no longer a byte-for-byte link to an upstream release, and no CI check
enforcing one. That link bought discipline — "we'll fix it in the library" stayed true
because it had to. Losing it makes `src/Render/CHANGELOG-FORK.md` load-bearing: if it stops
being kept current, this is indistinguishable from an abandoned, undocumented fork. Keeping
it current is a code-review item, not a nice-to-have.

### 4.3 Fork changes

Tracked in `src/Render/CHANGELOG-FORK.md`, not duplicated here — the spec describes
architecture, not a changelog. At minimum that file must record the upstream commit the fork
started from, every bug fix applied and what it corrects, and the features previously
described here as proposed upstream changes, now built directly into the fork instead of
waited on:

| Feature | Effect here |
|---------|-------------|
| UTF-8-aware `slugify()`, plus an injectable `slugify` callable option. The unfixed behaviour has no `/u` flag on `preg_replace('/[^\w\s\-]/')`, so `Sicherheitsprüfung` → `sicherheitsprfung` | Correctness fix for every German document; no DOM post-processing pass needed for it |
| `getHeadings(): array` returning `(level, id, text)` collected during conversion | Enables `[toc]` without re-parsing output HTML |
| `imageAttributes` option injecting `loading="lazy" decoding="async"` | No DOM post-processing pass needed for it either |
| `stripFrontMatter` option (default off) discarding a leading `---` block, exposing it via `getFrontMatter(): string` | The delimiter is a Markdown-level concern; parsing the contents stays the caller's job |

Because these are built into the fork rather than waited on, **C4 does not need a DOM
post-processing stage at all** — the "temporary until upstream lands" scaffolding that an
earlier draft of T7 called for is now unnecessary and should not be built.

**Open:** the upstream commit to fork from, and the specific bug list to port from the other
project, are not yet recorded here — see Open Items, item 6. `docs/BUILD-ORDER.md` T6 should
not start until they are.

### 4.4 One gap deliberately left open: raw HTML

Do **not** add raw HTML passthrough. Escaping everything is the property that makes the renderer safe to point at content of uncertain provenance — exactly the situation in P3, and the reason the manual review (§3.4) is a review of *shortcodes and links* rather than of arbitrary markup. Authors get controlled HTML through shortcodes.

### 4.5 Shortcode layer (C3, engine-side)

**Pre-pass:** mask fenced code blocks and inline code spans (so shortcode syntax inside examples is never interpreted) → match `[name attr="value"]` and paired `[name]…[/name]` → replace with an opaque placeholder token. Tokens contain no Markdown-significant characters (`%%CFSC0001%%`); block-level tokens sit alone on a line between blank lines so the renderer emits them as a standalone `<p>` the post-pass can unwrap.

**Post-pass:** substitute generated HTML, unwrap block-level wrappers, restore masked code.

| Shortcode | Renders |
|-----------|---------|
| `[figure src alt caption width height]` | `<figure><img …><figcaption>…</figcaption></figure>` |
| `[video src poster]` | Self-hosted `<video>` |
| `[embed provider id]` | Click-to-load facade; no third-party request until clicked |
| `[details summary]…[/details]` | Collapsible block |
| `[note type=info\|warn\|danger]…[/note]` | Callout |
| `[toc]` | Table of contents from the renderer's heading tree (§4.3) |
| `[include page="slug"]` | Inlines a page fragment from the *same language* (§7.7) |

Handlers declare whether their body is `raw`, `text`, or `markdown`; `markdown` bodies recurse through C4. All attribute values are escaped by the handler (`htmlspecialchars` for text, scheme allow-list for URLs) — the handler is a trust boundary, and after P3 it is a trust boundary handling imported values.

### 4.6 Guard requirement

Because C4 strips front matter and calls `convert(string)`, `convertFile()`'s protections are not inherited. C4 reimplements them before reading any file: `realpath()` containment under `content/`, extension ∈ {`md`, `markdown`}, size ≤ 2 MiB, `is_file()` and readable.

---

## 5. Content Model

### 5.1 Layout

```
content/
├── posts/
│   ├── de/2026/2026-03-14-sicherheitskultur.md
│   └── en/2026/2026-03-14-security-culture.md
├── pages/
│   ├── de/impressum.md
│   ├── de/vortraege/index.md
│   └── en/legal-notice.md
├── media/2026/03/image.jpg          ← language-neutral, shared
└── redirects.map
```

The first path segment under `posts/` and `pages/` is the **language code** (§7.2). Media is shared across languages; only `alt` text and captions are translated, and those live in the referencing document.

Post filenames follow `YYYY-MM-DD-slug.md` for sortability; the authoritative date and slug come from front matter. A mismatch is a build warning.

### 5.2 Shared front matter

Delimited by `---` at byte offset 0. **A restricted YAML subset only:** scalars, quoted strings, flat sequences, ISO-8601 dates. No anchors, aliases, custom tags, or merge keys. Use a strict hand-written parser or `symfony/yaml` in a constrained mode — never a permissive parser with type-coercion behaviour. (If `symfony/yaml` is used it becomes the one runtime dependency, which argues for the hand-written parser given how small the accepted subset is.)

| Key | Type | Req. | Notes |
|-----|------|:----:|-------|
| `title` | string | ✔ | Also the `<h1>`; the body should not repeat it |
| `slug` | string | ✔ | `^[a-z0-9-]{1,96}$`; per-language, need not match across translations |
| `status` | enum | ✔ | `draft` \| `published` \| `scheduled` |
| `summary` | string | ✔ | Meta description and card text, ≤ 200 chars |
| `translation_key` | string | | Groups translations (§7.3); absent means "no translations" |
| `updated` | ISO-8601 | | Drives `dateModified` and sitemap `lastmod` |
| `image` / `image_alt` | path / string | | `image_alt` required when `image` is set, and is per-language |
| `canonical` | URL | | For cross-posted content |
| `noindex` | bool | | Emits `robots: noindex`, excluded from sitemap |
| `aliases` | list | | Old paths → 301 here (P3 hook) |
| `toc` | bool | | Render a table of contents |
| `source_id` | string | | Opaque provenance (P3 hook); never displayed |

**`lang` is no longer a front matter key.** Language is derived from the file's location in the tree (§7.2). A derived value cannot disagree with the file's position, and one fewer key is one fewer thing to get wrong on every new document.

### 5.3 Post-only keys

| Key | Type | Req. | Notes |
|-----|------|:----:|-------|
| `date` | ISO-8601 | ✔ | Timezone-qualified; `Europe/Berlin` assumed if bare |
| `tags` | list | | Free-form, slugified; **language-scoped** (§7.10) |
| `series` | string | | Groups posts within one language |

### 5.4 Slug policy

Slugs are engine-owned, never renderer-derived. Rules: UTF-8 aware; German transliteration `ä→ae ö→oe ü→ue ß→ss` before stripping; lowercase; non-alphanumerics collapsed to `-`; trimmed; ≤ 96 chars.

**A slug present in front matter is used verbatim and never re-slugified.** This is what makes P3's URL preservation possible, and it means renaming a file never silently changes a published URL.

Slugs are per-language by design: `/sicherheitskultur/` and `/en/security-culture/` are the same document. Forcing a shared slug would produce German URLs on English pages or vice versa.

### 5.5 Validation — build-blocking

Missing required key · invalid slug pattern · slug collision within a language's route namespace (§8.2) · language directory not in the configured set · unparseable date · `image` that does not resolve · `image` without `image_alt` · `aliases` entry colliding with a real route · unknown front matter key (typo guard) · `translation_key` appearing twice within one language (§7.3) · missing UI string for a configured language (§7.8).

All errors are collected and reported in one run; the build then exits non-zero without deploying.

### 5.6 Status semantics

`draft` — never rendered into a release; visible only in admin preview. `scheduled` — excluded until a build runs at or after `date`; **requires the periodic build timer** (§10.5). `published` — included.

Status is per-document, not per-translation-group: a German post can be published while its English translation is still a draft. The language switcher accounts for this (§7.7).

---

## 6. Static Pages

Pages are a first-class content type, not posts with the date hidden.

### 6.1 How pages differ from posts

| | Posts | Pages |
|---|---|---|
| Dated | Yes, required | No |
| In feeds | Yes | No |
| In chronological index | Yes | No |
| Tags / series | Yes | No |
| Navigation | By date, tag, series | By explicit hierarchy |
| URL | Per permalink pattern (§8.1) | Path mirrors the directory tree |
| Ordering | Reverse chronological | Explicit `nav_order` |

### 6.2 Page-only front matter

| Key | Type | Notes |
|-----|------|-------|
| `template` | string | Template name; defaults to `page.php`. Allow-listed against `templates/` — never a path from input |
| `nav_label` | string | Short navigation label; falls back to `title` |
| `nav_order` | int | Sort order within the parent; absent = reachable but not in nav |
| `nav_parent` | string | Parent page slug; defaults to the directory position |
| `nav_group` | enum | `primary` \| `footer` \| `none` |
| `sitemap_priority` | float | Optional override |
| `legal` | enum | `impressum` \| `privacy` — marks this page as satisfying the corresponding legal-page requirement (§6.4). Reserves whatever slug this page uses and exempts it from `noindex`, footer omission, and pagination |

### 6.3 Hierarchy and URLs

The directory tree below the language segment defines structure. `pages/de/vortraege/index.md` → `/vortraege/`; `pages/en/talks/coffee-factor.md` → `/en/talks/coffee-factor/`. A directory without an `index.md` produces no landing page — the build warns, because a nav parent with no page is usually a mistake.

Nesting is capped at three levels below the language segment.

### 6.4 Legal pages

The build **warns** if a page marked `legal: impressum` or `legal: privacy` (§6.2) is absent
in the default language, and the templates reserve footer slots for them:

| `legal:` value | Basis |
|------|-------|
| `impressum` | Anbieterkennzeichnung under **§ 5 DDG** — the Digitale-Dienste-Gesetz replaced the TMG on 14 May 2024, so boilerplate citing "§ 5 TMG" is out of date. Requires name, ladungsfähige Anschrift, and a means of rapid electronic contact. This applies because the operator is established in Germany — it follows the operator's jurisdiction, not whichever language the site treats as `default_language` |
| `privacy` | GDPR Art. 13 information duties, reflecting what the site actually does — with no comments, no analytics, and self-hosted assets, this is short, and server access logs plus their retention are the substantive part |

Colophon (optional, not legal — how the site is built) stays a normal page. The build warns
if it's absent from the default language too, but it carries no `legal:` marker and none of
the protections below.

**Language handling follows `default_language`, not a hardcoded language.** An earlier draft
of this spec named German as authoritative and reserved fixed German/English slug strings —
specific to this one site's original configuration, and not something a Cuniform deployment
running different `languages` should have baked into the engine. The rule is now: **the
`default_language` version is authoritative.** Every other language's legal page is a
courtesy translation and must say so in a line of body text; a translation does not
substitute for the authoritative version, and one that drifts from it is worse than none. For
`blog.silverday.de` specifically: `default_language` is `en`, so the English Impressum and
Datenschutzerklärung/Privacy page are the authoritative content — but Anbieterkennzeichnung
under German law is about the *operator's* jurisdiction, not the UI language, so the required
information must still be present and accurate regardless of which language it's written in.
Not legal advice; confirm with someone qualified if in doubt.

**Reserved slugs are derived from the `legal:` marker, not a fixed word list.** The engine
looks for the front matter key, not a specific slug string — whatever slug that page actually
uses (§5.4: verbatim, per-language) becomes reserved automatically. This is what makes the
mechanism portable to a language this spec's authors never anticipated: a deployment's
Impressum-equivalent page reserves whatever slug it's given in whatever language, with no
engine change required. The build fails if a non-legal document claims a slug a
`legal:`-marked page is using, and refuses a redirect that shadows one.

**Legal-page content is not committed to the repository.** These pages carry the operator's
real name, address, and contact details — not something that belongs in git history,
especially once a repository might be shared or made public. The real files are gitignored
(`content/pages/*/impressum.md`, `content/pages/*/privacy.md`); what ships in the repo is
placeholder example content instead (`*.example.md` alongside them, the same convention as
`config/site.example.php`) showing the required structure with obviously-fake values. Copy
the example, drop the `.example` suffix, fill in the real information. The build's
warn-rather-than-fail behavior on a missing legal page is exactly what lets "not yet filled
in" survive a fresh checkout without blocking `make check` or a `--dry-run` build.

I am not a lawyer and this is not legal advice. The engine requirement is that these pages
are structurally first-class — once assigned, a fixed slug; always in the footer; never
`noindex`; never paginated away. Whether the address is a home or c/o arrangement is yours to
decide (Appendix B).

### 6.5 Shared fragments

`[include page="slug"]` inlines another page's rendered body **from the same language**. Cross-language includes are a build error — they are always a mistake, and silently producing a German block inside an English page is the kind of bug nobody notices for months. Include depth is capped at 2; cycles are a build error.

---

## 7. Languages

### 7.1 Configuration

```php
'languages'        => ['de', 'en'],   // BCP-47 primary subtags; one entry = single-language site
'default_language' => 'en',
'url_prefix'       => 'always',       // 'always' | 'auto' | 'never'
```

**Multi-language is optional.** A site with one configured language runs without a switcher, without hreflang, without per-language duplication of any artifact, and — depending on `url_prefix` — without language segments in its URLs. Nothing about the single-language case should feel like a multilingual site with one language switched off.

**`url_prefix` is a separate knob from `languages`, and that separation is the point.** Whether URLs carry a language segment is not the same question as how many languages exist today:

| Value | Behaviour |
|-------|-----------|
| `always` | Every URL is prefixed, even with one language: `/de/slug/` |
| `auto` | Prefixed when `languages` has more than one entry, unprefixed when it has one |
| `never` | Never prefixed; rejected at config validation if `languages` has more than one entry |

`auto` is the intuitive default and the one that will bite. A site launched single-language on `auto` publishes `/slug/`; adding a second language later flips it to `/de/slug/` and **moves every URL on the site** — the exact migration the symmetric scheme was chosen to avoid. `always` costs one redundant-looking path segment today and makes adding a language a non-event.

For `blog.silverday.de`: `languages: ['de','en']`, `url_prefix: always`. For any single-language deployment that might ever grow a second language, `always` as well. `auto` and `never` exist for sites that are genuinely, permanently monolingual — a documentation site, a landing page — where `/de/` in every URL is noise for no benefit.

### 7.2 Language is derived from the tree

A document's language is the first path segment under `posts/` or `pages/`. It is never declared in front matter (§5.2), so it cannot disagree with the file's location. A directory whose name is not in `languages` is a build error, not a silently ignored folder.

**The language segment is required even for a single-language site** — `content/posts/de/2026/…`, not `content/posts/2026/…`. It looks redundant on day one and it is the reason adding a second language later never requires moving a single file. Content layout stays stable across every configuration change; only URLs and templates react to the mode.

### 7.3 Translation linking

Translations are linked by `translation_key` — a stable, opaque, language-neutral identifier (e.g. `2026-security-culture`), **not** by matching filenames or slugs. Slugs must differ across languages (§5.4), so linking by slug would be self-defeating.

Rules: a key appearing twice within one language is a build error. A key present in only one language is normal, not a warning — most posts will be. Keys are never displayed and never appear in URLs.

### 7.4 URL strategy — symmetric prefixes

With `url_prefix: always` (the configuration for this site):

| Language | URL |
|----------|-----|
| `de` | `/de/sicherheitskultur/`, `/de/vortraege/`, `/de/tag/awareness/` |
| `en` | `/en/security-culture/`, `/en/talks/`, `/en/tag/awareness/` |

Every language is prefixed, including the default. One path-construction case in the router, no special-casing anywhere in the build, and a URL that always states its own language.

With `url_prefix: never` (or `auto` with one language), the same routes lose the segment: `/sicherheitskultur/`, `/tag/awareness/`, `/feed.xml`. The route table (§8.1) is written with `{L}` precisely so this is one substitution rather than two code paths.

**What it costs, and where that cost lands.** Every URL currently published on `blog.silverday.de` moves. Nothing under the old scheme survives as a live path, so `redirects.map` stops being a P3 convenience and becomes a P1 requirement (§1.2) — the legacy URL set has to be enumerated and mapped before cutover, not after. §15.5 covers how.

**What it buys, beyond tidiness.** Changing the default language later becomes a one-line change to a redirect instead of a site-wide URL migration — with unprefixed defaults, promoting English to default would move every German URL. It also keeps the legacy snapshot and the new site in disjoint URL space during cutover, which makes §15.5's freeze-and-serve option strictly simpler: the frozen tree lives at the old root paths and cannot collide with anything the build emits.

### 7.4.1 The site root

When URLs are prefixed, `/` holds no content and must resolve. Apache handles it:

```apache
RedirectMatch 302 ^/$ /en/
```

**302, not 301, deliberately.** A permanent redirect on the site root is cached by browsers effectively forever and is painful to unwind; a temporary one costs nothing measurable and leaves the default language changeable. This is the one place where the symmetric scheme's flexibility is actually realized, so it should not be given away to a cached 301.

When URLs are unprefixed, the root *is* the index page and this directive is absent.

`robots.txt`, `sitemap.xml`, `/.well-known/`, `/media/`, `/admin`, and the neutral `404.html` stay at the root and are never language-prefixed.

Language codes in `languages` become **reserved top-level segments**, and under this scheme they are the *only* content segments at the root — anything else appearing at the top level is either a site-wide file or a legacy redirect.

### 7.5 hreflang and canonical

Every page emits, for each published translation in its group:

```html
<link rel="alternate" hreflang="de" href="https://blog.silverday.de/de/sicherheitskultur/">
<link rel="alternate" hreflang="en" href="https://blog.silverday.de/en/security-culture/">
<link rel="alternate" hreflang="x-default" href="https://blog.silverday.de/en/">
<link rel="canonical" href="https://blog.silverday.de/en/security-culture/">
```

Including a **self-referencing** alternate, which is required for the annotation to be valid. `x-default` points at the default language's home rather than at a specific translation, since the root itself only redirects. Alternates list only `published` translations — a draft translation must not be advertised.

**Single-language sites emit none of this** — no alternates, no `x-default`, no `og:locale:alternate`. An hreflang set of one is meaningless markup, and `x-default` pointing at the only version is worse than absent.

### 7.6 No content negotiation

`/` serves the default language. There is no `Accept-Language` redirect and no `mod_negotiation`.

This is deliberate, not a limitation of static hosting: negotiation forces `Vary: Accept-Language` on every response, which fragments caching, and it surprises people who deliberately opened a link in a language they wanted. Language selection is an explicit, visible switcher in the header. A visitor's choice can be remembered client-side for the switcher's convenience, but it never redirects them automatically.

### 7.7 Untranslated documents and the switcher

The language switcher is rendered only when more than one language is configured; with one, the partial produces nothing and leaves no empty container behind in the markup.

A document without a translation in language *L*:

- does not appear in *L*'s index, feeds, tag archives, series, or search index;
- is not advertised in *L*'s hreflang set;
- causes the language switcher on the document's own page to render *disabled for L*, or to link to *L*'s home page — clearly labelled as such.

The switcher never links to a differently-languaged page as though it were a translation. That produces the worst version of a multilingual site: a reader clicks "EN" and lands on German text.

### 7.8 UI strings

Template chrome ("Weiterlesen", "Veröffentlicht am", "Schlagwörter") lives in `config/lang/de.php` and `config/lang/en.php`, each returning a flat `key => string` array, accessed via `t('key')`.

**A missing key for a configured language is a build error, not a fallback to the default language.** A silent fallback ships a page with German buttons and English content, and nobody notices until a reader mentions it.

No gettext: `.po`/`.mo` compilation is a build dependency and a toolchain for a site with two languages and perhaps forty strings.

Single-language sites still use `t()` and still have a string file. Hard-coding chrome into templates would mean that adding a language later requires editing every template — the one thing this design is trying to avoid.

### 7.9 Dates and numbers

Formatted with `IntlDateFormatter` using the document's language, so German posts read *14. März 2026* and English ones *14 March 2026*.

`ext-intl` is therefore a soft requirement (§15.1). If it is unavailable, the fallback is an explicit per-language month-name table in the UI string files — not `strftime()`, which is deprecated as of PHP 8.1 and locale-dependent in a way that fails silently on a server with no locales generated.

### 7.10 Per-language artifacts

Generated per language: home index and pagination, tag archives, series indexes, year archives, feeds, search page, 404. With one language this is simply one set, at prefixed or unprefixed paths depending on `url_prefix`.

**Tags are language-scoped.** `awareness` on a German post and `awareness` on an English post are different tags with different archives, even when the string matches. Merging them would produce a mixed-language listing, which is exactly what a language-scoped site is trying to avoid.

Single, shared across languages: `sitemap.xml` (one file, with `xhtml:link` alternates per URL), `search-index.json` (one file with a `lang` field per entry, filtered client-side), `robots.txt`, `/.well-known/security.txt`, and the media tree.

### 7.11 Feeds

`/de/feed.xml`, `/de/atom.xml`, `/en/feed.xml`, `/en/atom.xml` under `url_prefix: always`; `/feed.xml` and `/atom.xml` when unprefixed. Each feed declares its own `<language>` and contains only that language's posts. A mixed-language feed is unusable in a reader.

**Existing subscribers move.** The current `/feed.xml` must 301 to `/de/feed.xml` and stay there permanently — feed readers follow a 301 and update their stored URL, which is exactly the case where a permanent redirect is right and the root's 302 (§7.4.1) is not. Losing this redirect silently unsubscribes everyone, so it is a named entry in `redirects.map` rather than something generated.

### 7.12 Error pages

Per-language 404 via a `<Location>` override (§3.2), each rendered from the same `404.php` template with that language's strings and navigation.

A **neutral root `/404.html`** is also required whenever URLs are prefixed, because a mistyped or stale URL frequently lands outside any language prefix (`/sicherheitskultur/` under the old scheme, `/typo`, a bare `/tag/awareness/`). That page carries no language assumption: both languages' home links, a short bilingual line, and the search page. It is the page most legacy visitors will see if a redirect is ever missed, which makes it worth designing rather than defaulting.

If the `<Location>` override proves unreliable, the fallback is a single bilingual 404 for all cases — acceptable, but worth one verification step to avoid.

Unprefixed single-language sites need only the root `404.html`, and the `<Location>` blocks are absent.

---

## 8. URLs and Routing

Routing is a build-time concern: each route is a generated file. `{L}` below is the language code plus a slash — `de/` or `en/` — when URLs are prefixed, and the empty string when they are not (§7.4). Every route in the table is defined once, in terms of `{L}`; the mode substitutes it.

### 8.1 Permalink pattern

Configurable in `config/site.php`, default `/{slug}/`. Tokens: `{slug}`, `{year}`, `{month}`, `{day}`. Configurable rather than hardcoded so P3 can match whatever the existing site emits.

| Route | Output |
|-------|--------|
| `/{L}` | `[{L}]index.html` (post list, page 1) |
| `/{L}page/2/` | Pagination |
| `/{L}<pattern>` | Posts |
| `/{L}<path>/` | Pages, mirroring the tree |
| `/{L}tag/<tag>/` | Tag archive, paginated |
| `/{L}series/<series>/` | Series index |
| `/{L}archive/<yyyy>/` | Year archive |
| `/{L}feed.xml`, `/{L}atom.xml` | Feeds |
| `/{L}search/` | Client-side search page |
| `/{L}404.html` | Per-language error document |
| `/404.html` | Neutral error document for non-prefixed paths |
| `/sitemap.xml`, `/robots.txt`, `/.well-known/security.txt` | Site-wide |

Trailing slash canonical, `DirectoryIndex index.html`, absolute `<link rel="canonical">` on every page.

### 8.2 Route namespace

Posts and pages share their language's namespace, so a post `/about/` and a page `/about/` collide. The build validates the **entire** route set for uniqueness, including generated routes, and fails on any collision.

Reserved slugs: every language code (§7.4); `tag`, `series`, `archive`, `page`, `search`, `feed.xml`, `atom.xml`, `sitemap.xml`, `robots.txt`, `.well-known`, `admin`, `media`; and whatever slug each `legal:`-marked page (§6.2, §6.4) is actually using, discovered at build time rather than fixed here.

`/admin` deserves emphasis: it is an `Alias`, so a page that generated `/admin/index.html` in the release tree would be shadowed by the alias and quietly never served. Reserving it turns a confusing non-failure into a build error.

### 8.3 Redirects

`content/redirects.map` holds `old-path new-path` pairs, compiled at build time into a `RewriteMap txt:` or generated `RedirectMatch` block, 301 by default. Populated from `aliases` keys plus manual entries.

**This is P1 infrastructure, not a P3 hook.** Under symmetric prefixes every currently-published URL moves, so the map is populated and exercised from the first cutover — including the hand-written `/feed.xml → /de/feed.xml` entry (§7.11). The build verifies every target resolves (§10.3).

One ordering caveat: the root's 302 (§7.4.1) and any legacy entry for `/` must not both match. `RedirectMatch 302 ^/$` is anchored to exactly the root, so a legacy map entry for `/` is redundant and should be omitted rather than allowed to compete.

---

## 9. Templating

Plain PHP templates — no Twig, no compilation step.

1. Templates receive one immutable `ViewModel`; no superglobals, no filesystem access from a template.
2. **Escape by default:** `e()` (`htmlspecialchars`, `ENT_QUOTES | ENT_SUBSTITUTE`, UTF-8), `eAttr()`, `eUrl()` (scheme allow-list), `eJs()`. Exactly one unescaped sink exists — `$doc->bodyHtml`, which is renderer output. CI lints for any `<?=` not routed through a helper.
3. `layout.php` owns `<html lang>` (from the resolved language, §7.2), `<head>`, meta and OG tags (`og:locale` and `og:locale:alternate`), hreflang set, CSP hashes, primary nav, language switcher, and footer nav.
4. `template` from page front matter is resolved against an allow-list of files in `templates/`, never used as a path.
5. Templates are shared across languages — there is one `post.php`, not one per language. Language enters through `t()` and the ViewModel, never through duplicated templates, because duplicated templates drift. The same templates serve a single-language site unchanged; the switcher and hreflang partials simply emit nothing (§7.7, §7.5).
6. A template error aborts the build; a partial page is never emitted.

Template set: `layout.php`, `post.php`, `page.php`, `index.php`, `tag.php`, `series.php`, `archive.php`, `search.php`, `404.php`, `feed.xml.php`, plus `partials/{head,nav-primary,nav-footer,lang-switcher,post-card,pagination,toc}.php`.

**Styling:** the renderer's bundled `md2html.css` is not injected in headless mode, so Cuniform ships its own stylesheet. It must define all seven highlight classes the renderer emits (`hl-keyword`, `hl-string`, `hl-comment`, `hl-number`, `hl-variable`, `hl-attribute`, `hl-tag`). Base palette per SilverDay conventions — bg `#ffffff`, card `#f8f9fa`, text `#1a1a1a`, secondary `#4a4a4a`, accent `#2c3e50`, link `#3498db`, border `#e0e0e0` — with dark mode via `prefers-color-scheme`.

---

## 10. Build Pipeline

### 10.1 Stages

1. **Lock** — `flock` on `var/build.lock`; concurrent builds rejected, not queued.
2. **Discover** — recursive scan, skipping any `*.example.md` file (§6.4 — these are inert
   templates, never content, so an unfilled one sitting in the tree never becomes a real
   page); manifest of path, mtime, SHA-256; language assigned from the tree.
3. **Parse** — front matter split and validate; collect all errors before failing.
4. **Resolve** — route table per language, namespace collision check, translation groups from `translation_key`, tag and series indexes per language, nav tree per language, prev/next, alias map.
5. **Render** — shortcode pre-pass → convert → shortcode post-pass.
6. **Template** — render every route with its language context.
7. **Emit** — feeds per language, shared sitemap with alternates, search index, redirects, `robots.txt`, `security.txt`, fingerprinted assets.
8. **Verify** — §10.3.
9. **Deploy** — atomic swap, prune to 5 retained releases.

### 10.2 Incremental builds

Cache key: `SHA-256(file) + SHA-256(templates) + SHA-256(UI strings) + engine version + SHA-256(src/Render/Md2Html.php)`. A changed document re-renders itself, every list page it appears on, **and every page in its translation group** (its hreflang set changed). A changed template, UI string file, or nav-affecting page invalidates everything. `--full` forces a rebuild. Any ambiguity resolves toward a full rebuild.

### 10.3 Verification — blocking

No deploy if: any internal link resolves outside the new release tree · any referenced media file is missing · any output fails a well-formedness parse · the page count drops more than 10% versus the current release · any alias shadows a real route · a reserved slug is claimed · **any hreflang set is asymmetric** (A advertises B, B does not advertise A) · any UI string key is missing for a configured language · **the URL scheme changed since the current release** (a different `url_prefix` result, a changed `default_language`, or a language added or removed) **without `--allow-url-scheme-change`** — every such change silently moves URLs site-wide, so it requires an explicit statement of intent and prints the vhost directives that must change alongside it · **any entry in `redirects.map` points at a path that does not exist in the new release** (a redirect to a 404 is worse than no redirect, and with every legacy URL now redirected, this check is what keeps the cutover honest).

### 10.4 Atomic deploy

```bash
cd /srv/vhosts/blog.silverday.de
ln -sfn "releases/$TS" public.new && mv -T public.new public
```

`mv -T` is atomic; no request observes a partial tree. `releases/` and `public` sit under the same vhost directory, so the same-filesystem requirement is satisfied by the layout. Rollback re-points the symlink: `bin/cuniform --rollback`.

**One-time setup:** if `public` exists as a real directory from provisioning, it is replaced by the symlink before the first deploy, and anything currently in it moves out first (§3.3, §15.5).

### 10.5 Triggers

| Trigger | Mechanism |
|---------|-----------|
| Git push | `post-receive` hook on the server |
| Admin publish | Admin writes a request file; a systemd path unit runs the build as the build user |
| Scheduled posts | systemd timer, every 15 min, no-op when nothing is due |
| Manual | `bin/cuniform build [--full] [--dry-run]` |

**Privilege boundary:** the admin PHP process never executes the build directly. It enqueues; a separate unit consumes. "Handles web requests" and "writes the public tree" stay in different security contexts — which matters more here than on a split-vhost design.

---

## 11. Generated Artifacts

**11.1 Feeds.** Per language (§7.11). RSS 2.0 and Atom, latest 20 posts, full content, relative URLs rewritten to absolute, `<language>` declared. GUIDs derived from the permalink, never the file path. Pages never appear in feeds.

**11.2 Sitemap.** One `sitemap.xml` covering all languages, each URL carrying `xhtml:link` alternates for its translations. `lastmod` from `updated ?? date`; excludes drafts, `noindex`, and pagination beyond page 1.

**11.3 Search index.** One `search-index.json` of `{path, lang, type, title, date, tags, summary, body_plain}`, filtered client-side to the current language. The build **warns above a configurable byte threshold** (default 750 KB); the documented response is to drop `body_plain` first, then split per language, then shard. Two languages roughly double the index, so the threshold matters sooner than it would monolingually.

**11.4 Assets.** `style.<hash8>.css` with `Cache-Control: immutable`; HTML `public, max-age=600, must-revalidate` plus `ETag`.

**11.5 Counts.** Taxonomy listings show tag and series names without post counts — counts are churn that dirties incremental builds for no reader benefit.

---

## 12. Authoring

All paths converge on a git commit in `content/`. Nothing writes to the public tree except the build.

**Path A — git push.** Local edit, commit, push; `post-receive` validates front matter, runs the build, reports status. Sole authoring path in P1.

**Path B — admin editor** (P2). Markdown editor plus a front matter form, media picker, and shortcode inserter. Language is chosen at document creation and determines the file's location; changing it later is a move, and the editor performs it as one rather than editing a field. On save: write to the working copy, `git commit` with the session identity, optionally push. On publish: flip `status`, commit, enqueue a build. **Conflict handling:** compare the file's current SHA-256 against the one the editor loaded; on mismatch refuse the write and show a diff — never auto-merge.

The editor surfaces the translation group: when editing a document with a `translation_key`, its sibling translations and their statuses are visible, so a published German post with a forgotten English draft is obvious rather than discovered by a reader.

**Path C — preview** (P2). Renders through the identical C3/C4/C5 chain at runtime, for the authenticated session only. Works for drafts, scheduled posts, and unsaved buffers. Byte-identical to build output except for an injected preview banner — any divergence is a bug. Served `X-Robots-Tag: noindex, nofollow` and `Cache-Control: no-store`. Never writes into `releases/` or `public`.

Preview is the only runtime rendering in the system, and it sits behind authentication. That is what lets the public site stay static.

---

## 13. Admin Application (P2)

Served from `/admin` on the single vhost, own PHP-FPM pool running as a dedicated user with write access to `content/` and the git working copy only.

### 13.1 Authentication

- Argon2id (`PASSWORD_ARGON2ID`), memory cost ≥ 64 MiB.
- NIST SP 800-63B baseline: ≥ 12 characters, breached-password check on set, no composition rules, no forced rotation.
- TOTP (RFC 6238) mandatory — AAL2.
- **Recovery is by code, not by email:** single-use recovery codes generated at enrolment, displayed once, stored hashed. The host can send mail (§15.2), so an email reset is technically available and is declined deliberately — an email reset that restores access on its own reduces the account to the security of the mailbox and bypasses TOTP. If an email step is ever added, it is an *additional* factor, never a substitute.
- Sessions: `Secure`, `HttpOnly`, `SameSite=Strict`, `__Secure-` prefix, `Path=/admin`, regenerated on privilege change, 30 min idle / 12 h absolute, server-side store in `var/`.

  `__Host-` cannot be used, since it mandates `Path=/`. `Path=/admin` is tidiness only — it does not isolate the cookie from same-origin script (§3.4).
- Login rate limiting with exponential backoff per account and per source address.
- Optional `Require ip` allow-list on the `/admin` `<Directory>`.

### 13.2 Application security

CSRF synchronizer token on every state-changing request · path validation on every content operation, with the editor taking a document **identifier**, never a path from the request · media upload validated on extension *and* content type *and* magic bytes, re-encoded through GD/Imagick to strip EXIF and any embedded payload, stored under a randomized name · git invoked via `proc_open` with an argument array, never a shell string · append-only JSONL audit log in `var/log/` recording actor, action, timestamp, and resulting commit SHA.

### 13.3 Screens

Dashboard (recent documents, last build status, draft count, **untranslated-document count**) · post list filterable by language and translation status · page list with per-language nav tree · editor · media library · tags and series per language · redirects · build log with rollback · settings.

---

## 14. Security Requirements

### 14.1 CSP — mandatory, not defence in depth

Because admin and site share an origin (§3.4), the public CSP is the primary control against an XSS becoming an admin takeover.

```
default-src 'self';
script-src 'self' 'sha256-<hash>';
style-src 'self';
img-src 'self' data:;
font-src 'self';
connect-src 'self';
frame-ancestors 'none';
form-action 'self';
base-uri 'none';
object-src 'none'
```

Static HTML cannot emit a per-request nonce, so **public pages use hashed inline scripts or, preferably, none at all** — search and the language switcher ship as external files. Nonces apply to admin responses, which are dynamic.

### 14.2 Response headers (site-wide)

HSTS `max-age=31536000; includeSubDomains` (add `preload` only once you are certain about every subdomain of the apex) · `X-Content-Type-Options: nosniff` · `Referrer-Policy: strict-origin-when-cross-origin` · `Permissions-Policy` denying camera, microphone, geolocation · `Cross-Origin-Opener-Policy: same-origin` · `X-Frame-Options: DENY`.

### 14.3 Privacy posture

Zero third-party requests from public pages: self-hosted fonts, no analytics, no CDN, embeds behind click-to-load facades. This is what keeps the Datenschutzerklärung short and honest.

### 14.4 No comments

The site accepts no user-submitted content of any kind — no comment form, no contact form posting to the server. This removes the entire untrusted-input surface, which is what makes §3.4's single-origin trade-off defensible.

---

## 15. Hosting and Operations

### 15.1 Target

The SilverDay web server, at `/srv/vhosts/blog.silverday.de/`. Requirements: Apache with `mod_rewrite`, `mod_headers`, `mod_alias`, `mod_proxy_fcgi`; PHP 8.3 CLI plus an FPM pool for `/admin`; `ext-intl` preferred (§7.9), with a documented fallback; `git`; systemd for timers and the build-consumer unit.

Users: `cuniform-build` (owns `content/`, `releases/`, `var/`, and the `public` symlink) and `cuniform-web` (the FPM pool user, write access to `content/` and the git working copy, **no** write access to `releases/` or `public`).

**Housekeeping:** `releases/` accumulates a full site copy per build. Five retained releases plus incremental builds during an editing session is a real disk pattern — the prune step runs on every build, and the build log records tree size so growth is visible before it matters.

### 15.2 Mail — outbound only

The host provides a local `sendmail` interface for sending, with no full MTA and no inbound mail.

**Available.** Build-failure and build-success notifications, login alerts, certificate-expiry warnings, sent through PHP's `mail()` or `proc_open` on `sendmail_path`. Failures also surface in `var/log/` and on the admin dashboard — mail is an additional channel, never the only one.

**Deliverability.** Envelope sender set explicitly to an address on a domain you control, never `www-data@<hostname>` · SPF for the sending domain must authorize the relay, and the `From:` domain must align for DMARC · DKIM signing happens at the relay · relay credentials live in system configuration, unreadable by the FPM pool user · **bounces go nowhere**, so delivery failures are invisible from this host and notification mail is best-effort by design.

**Injection guard.** Any value reaching a mail header is validated against CRLF injection; headers are assembled by a helper, never by concatenation.

**Newsletters remain out of scope** — not for lack of a mail path, but because subscriber lists, double opt-in records, unsubscribe state, and consent evidence are a database-shaped problem, and not having a database is the premise.

**Contact.** Contact addresses appear as page content only. No server-side form target (§14.4).

### 15.3 Backup and recovery

Content and configuration in git with an off-host remote; `content/media/` syncs to off-host storage separately. RPO ≤ 24 h. Recovery: clone, restore media, `bin/cuniform build --full`. The five retained releases are a convenience, not the backup.

### 15.4 Logging

Apache access and error logs with a documented retention period (which the Datenschutzerklärung must state) · build log in `var/log/build.jsonl` with duration, document count per language, and outcome · admin audit log per §13.2 · notification mail logged as handed-off or failed at the `sendmail` boundary, which is the only delivery signal available.

### 15.5 Cutover from the existing site

`blog.silverday.de` is currently serving content, which is exported and reused (Appendix B). This creates a gap the phasing does not otherwise cover: **P1 takes the hostname, but the imported content does not arrive until P3.**

Between those points, every existing URL 404s. For a site with inbound links and search-engine presence, that is not a cosmetic problem — indexed URLs returning 404 get dropped, and the rankings do not come back when the content does.

Three ways to close it, in my order of preference:

1. **Freeze the old site as static HTML and ship it inside the first release.** Crawl the existing site before cutover (`wget --mirror` or equivalent), place the result under `releases/<ts>/` at its original paths, and let the build carry it forward until P3 replaces each path with a 301 to `/de/<slug>/`. Old URLs keep returning 200 with the original content throughout. Symmetric prefixes make this cleaner than it would otherwise be: the frozen tree occupies the old root paths, the new site lives entirely under `/de/` and `/en/`, and the two cannot collide — so "reserved paths" is just "whatever the snapshot contains", checked once at build time.
2. **Delay the cutover to P3.** Develop against a staging hostname, switch DNS only when the import is done. Clean, but it means P1 and P2 ship to nobody, which removes the feedback that makes early phases worth having.
3. **Accept the gap** with a holding page and a redirect map to nothing. Cheapest, and the most expensive later.

Whichever is chosen, **export the existing content before anything touches the vhost**, and keep the export immutable — it is the only copy of the source material once `public` becomes a symlink. Verify the export is complete and readable before the first `rmdir public`.

Feed continuity deserves separate attention: `/feed.xml` subscribers persist for years, and under symmetric prefixes that path moves to `/de/feed.xml`. The 301 for it (§7.11) is not optional and not generated — it is written by hand into `redirects.map` and verified after cutover with an actual request.

---

## 16. Name

**Cuniform**, spelled with one `e` — not *Cuneiform*.

The reference is to the writing system: wedge-shaped marks pressed into clay, the oldest
durable text format we have, and one that survived because the medium was fired and left
alone rather than maintained. That is the argument for a flat-file engine in one word.

**The spelling is a deliberate divergence and must be applied consistently.** `Cuneiform` is
taken: an open-source OCR system originally developed by Cognitive Technologies, released
under BSD in 2008, still mirrored and packaged. Dropping the `e` clears that collision, and
`silverday/cuniform` is free on Packagist.

The cost is that the name reads as a misspelling to anyone who knows the word — which, given
the audience for a security blog, is most of them. Two mitigations, both cheap:

- The repository description and the site colophon state the spelling is intentional. One line
  each, and it turns a perceived typo into a deliberate mark.
- Never write `Cuneiform` anywhere in the project. A single stray `e` in a README or a slide
  makes the divergence look accidental rather than chosen, and invites correction.

Identifiers derived from the name, all one-`e`: PHP namespace `Cuniform\`, CLI `bin/cuniform`,
Composer package `silverday/cuniform`, system users `cuniform-build` and `cuniform-web`,
FPM socket `cuniform-admin`, shortcode placeholder prefix `%%CFSC…%%`.

---

## 17. Non-Functional Requirements

| ID | Requirement |
|----|-------------|
| NFR-1 | Incremental single-document build ≤ 2 s; full build scales linearly and reports its own duration |
| NFR-2 | No PHP in the public request path |
| NFR-3 | Public HTML ≤ 100 KB excluding images; no render-blocking third-party requests |
| NFR-4 | WCAG 2.1 AA: heading order, contrast, visible focus, skip link, `alt` enforced by schema validation, `lang` correct on every page and on the language switcher's links (`hreflang` + `lang` on the anchor) |
| NFR-5 | Content survives the engine — plain Markdown plus a documented front matter schema, no lock-in |
| NFR-6 | Zero runtime dependencies; Composer is dev-only and `vendor/` is never deployed |
| NFR-7 | PHP 8.3 `strict_types=1`, PSR-12, PHPStan level 8, PHPUnit covering the parser, slugifier, shortcode layer, route resolver, and translation grouping |
| NFR-8 | Reproducible build: same inputs produce byte-identical output apart from an explicit build timestamp |
| NFR-9 | A single-language site produces no multilingual artefacts — no switcher markup, no hreflang, no empty containers — and, under `url_prefix: never`, no language segment anywhere in its output |
| NFR-10 | MIT licence |

---

## 18. Milestones

| # | Milestone | Exit criterion |
|---|-----------|----------------|
| M1 | Core render chain | Front matter parser, slugifier, shortcode layer, renderer adapter, unit tests green |
| M2 | Languages | Translation grouping, per-language routes, hreflang, UI strings, switcher — verified on a two-language corpus **and** on a single-language corpus in both `always` and `never` prefix modes, since the single-language path is the one that rots untested |
| M3 | Templates + build | Full static build, atomic deploy, rollback verified |
| M4 | Cutover | Existing content exported and verified; §15.5 option chosen and executed; DNS/vhost switched |
| M5 | Admin | Auth with TOTP, editor, preview, media, git commit, build enqueue |
| M6 | Import | Appendix A; migration report clean; manual review complete; URL reconciliation passes |

---

## 19. Open Items

1. ~~**Reading of "optional"**~~ — resolved 2026-09-09: the *engine-level* reading, confirmed. `blog.silverday.de` launches bilingual (`de` + `en`), not German-only-then-English-later, so `url_prefix: always` is in place as more than a precaution.
2. ~~**Language set and default**~~ — resolved 2026-09-09, with a change from the original assumption: `de` + `en`, but **English** (not German) is `default_language` — `/` redirects to `/en/`, and `x-default` hreflang points at the English home (§7.4.1, §7.5; the Apache skeleton in §3.2 was updated to match). §6.4 was also generalized on 2026-09-09: legal-page authority now follows `default_language` (English, here) rather than a hardcoded German rule, though the underlying German-law basis for the Impressum itself is unaffected — that follows the operator's jurisdiction, not the UI language. Still open: whether English is a full parallel site or a subset of the German content at launch — worth an answer soon, since with English now the *default* (what `/` shows first), a thin English index is a more visible first impression than it would have been as the secondary language.
3. **What the existing site runs on** — Appendix A assumes WordPress. If `blog.silverday.de` is something else, the importer's input format changes, though the pipeline and verification do not.
4. **§15.5 option** — freeze-and-serve, delayed cutover, or accept the gap.
5. **Legacy URL enumeration** — the complete list of live URLs on the current site is needed before cutover (§7.4, §15.5), not after. A crawl of the existing sitemap is the practical source; if the current site has no sitemap, a `wget --spider --recursive` run produces one.
6. ~~**Renderer fork provenance**~~ — resolved 2026-09-09. §4 treats `md2html-php` as an owned fork rather than a byte-identical vendor copy, forked from `SilverDay/md2html-php@f1e0162`, which already contained the bug fixes that prompted the change (a `parseList()` infinite-recursion hang and a numbering-reset regression in its own fix — see `src/Render/CHANGELOG-FORK.md` for both). T6 is done.

---

## Appendix A — WordPress Import (Phase 3)

Not a launch dependency. P1 reserves the hooks (§1.2).

**A.1 Input.** A WXR export (Tools → Export → All content) — RSS 2.0 with the `wp:`, `content:`, `excerpt:`, and `dc:` namespaces. Parse with `XMLReader` in streaming mode with external entity loading disabled. Media is referenced, not embedded: attachment items carry `wp:attachment_url`, so binaries are fetched separately over HTTPS against a fixed source-host allow-list.

**A.2 Pre-work.** The live permalink structure (fixes §8.1) · whether content is Gutenberg (`<!-- wp:… -->` block comments) or Classic (`wpautop` newlines) — a long-lived site has both · which plugin shortcodes appear in content, since every unrecognized `[shortcode]` is a data-loss risk · whether the existing site has any non-German content, which decides whether imported documents all land in `de/`.

**A.3 Pipeline.** Partition by `wp:post_type` and `wp:status` (`publish`→`published`, `draft`/`pending`→`draft`, `future`→`scheduled`, `private`→ manual decision; skip revisions, nav items, auto-drafts) → strip block comments → HTML→Markdown constrained to constructs the renderer supports, with anything else becoming a shortcode or a review flag → map WP shortcodes (`[caption]`→`[figure]`, `[gallery]`→ repeated `[figure]`, `[embed]`→`[embed]`); unknown shortcodes preserved verbatim **and** reported, never silently dropped → rewrite media URLs from `/wp-content/uploads/YYYY/MM/` to `/media/YYYY/MM/`, download, checksum → emit front matter with `slug` from `wp:post_name` **verbatim**, `date` from `wp:post_date_gmt`, `source_id` from `wp:post_id`, written into the default language tree → write `redirects.map` for every changed path, including attachment pages, category and tag archives, feeds, and date archives.

Under symmetric prefixes (§7.4) imported URLs do **not** land where they were: `/some-post/` becomes `/de/some-post/`. The importer therefore emits a redirect entry for every single imported document, not just for the ones whose slugs changed. `wp:post_name` is still taken verbatim, so the mapping is mechanical — `old-path → /de/<slug>/` — and fully generated rather than hand-maintained.

**The converter never emits raw HTML into a `.md` file** — it would be escaped and displayed as text (§4.4).

**A.4 Verification.** Count reconciliation per status (any delta is a hard failure) · URL diff between the old sitemap and the built site, every old URL resolving 200 or 301 · per-post word-count comparison with a tolerance, outliers to a manual review list · a migration report listing unknown shortcodes, unsupported constructs, footnotes, failed media downloads, and posts awaiting a decision. The import is idempotent and re-runnable until the report is clean.

**A.5 Manual review (your decision — §3.4).** Every imported document is reviewed before it ships. This is the control that keeps the single-origin architecture sound, so it needs a checklist rather than a skim. The reviewer confirms, per document:

- no shortcode with an attacker-influenced attribute — particularly `src`, `href`, and anything the handler passes through;
- every external link points where the text claims, since old posts accumulate expired domains that get re-registered;
- no leftover verbatim `[shortcode]` from a plugin that no longer exists;
- no embedded tracking pixel or third-party asset reference surviving as a Markdown image;
- content genuinely worth keeping — an import is the cheapest moment to delete posts that have not aged well.

The migration report drives the order: documents with flags first, clean ones batched. Recording the review (a checkbox per `source_id` in a file under `var/`) means an interrupted review can resume rather than restart.

**A.6 Comments** in the export are not imported (§14.4). The WXR file retains them if the archive is ever wanted.

**A.7 Anchors.** If the current site has non-ASCII heading anchors, deep links to `#fragment` change. Fragments are never sent to the server, so no redirect can fix them. Known, accepted loss — listed in the report.

---

## Appendix B — Decision Log

| # | Question | Decision |
|---|----------|----------|
| 1 | Rendering model | Static publishing; runtime rendering only in authenticated preview |
| 2 | Vhosts | Single vhost; admin at `/admin`, outside the release tree |
| 3 | Comments | None, and none imported |
| 4 | WordPress import | Deferred to P3; forward-compatibility hooks built in P1 |
| 5 | Static pages | First-class content type (§6) |
| 6 | Renderer | `md2html-php` as the base. **Revised 2026-09-09:** originally vendored byte-identical (v0.8 and earlier); retired after serious bugs surfaced upstream during use on another project. Now an owned fork at `src/Render/Md2Html.php`, edited directly — see §4 |
| 7 | Hosting | SilverDay web server, `/srv/vhosts/blog.silverday.de/`, outbound-only mail |
| 8 | Languages | Optional multi-language, capability built in P1; `de`+`en` here with symmetric prefixes (`url_prefix: always`) and root 302 to the default |
| 9 | Permalink pattern | `/{slug}/` default |
| 10 | Same-origin admin at P3 | Manual review of imported content, per §A.5 |
| 11 | Impressum address | Operator decision, made before launch; not an engine concern |
| 12 | Name | Cuniform (renamed from Stele; spelling deliberate, §16) |
| 13 | Existing site | Content exported and reused; see §15.5 for the cutover gap |

---

*Renderer behaviour in §4 was verified by reading `src/Md2Html.php` directly. The § 5 DDG reference in §6.4 reflects the TMG's repeal on 14 May 2024.*
