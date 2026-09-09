# Cuniform — Build Order

Task list for implementation. `docs/SPEC.md` is authoritative; the "Spec" column is an index
into it, not a substitute. Work one task at a time. A task is done only when every acceptance
criterion passes and `make check` is clean.

Mark completion by changing `[ ]` to `[x]` in the Status column, in the same commit as the work.

---

## M1 — Core render chain

| # | Status | Task | Deps | Spec |
|---|--------|------|------|------|
| T1 | [x] | Project skeleton: `src/` PSR-4 autoloader (hand-rolled, no Composer runtime), `bin/cuniform` entry point, Makefile, PHPUnit + PHPStan config | — | §3.5, NFR-6 |
| T2 | [x] | Config loader and validator: `config/site.php`, language set, `url_prefix` validation, permalink pattern | T1 | §7.1, §8.1 |
| T3 | [x] | Filesystem gateway with `realpath()` containment, extension allow-list, size cap | T1 | §4.6 |
| T4 | [x] | Front matter parser: restricted YAML subset, typed result object, error collection | T3 | §5.2, §5.5 |
| T5 | [x] | Slugifier: UTF-8, German transliteration, collision suffixes, verbatim passthrough | T1 | §5.4 |
| T6 | [x] | Renderer fork: import `Md2Html.php` into `src/Render/`, namespace it, write `CHANGELOG-FORK.md`, port the known bug fixes and the fork features of §4.3 | T1 | §4.1, §4.2, §4.3 |
| T7 | [x] | Render adapter: front matter strip, guards | T4, T5, T6 | §4.6 |
| T8 | [x] | Shortcode layer: code masking, placeholder tokens, pre/post passes, handler interface | T7 | §4.5 |
| T9 | [x] | Shortcode handlers: figure, video, embed, details, note, toc, include | T8 | §4.5, §6.5 |

**T1 acceptance:** `make check` runs and passes on an empty project. No Composer package is
required at runtime — deleting `vendor/` leaves `bin/cuniform` working.

**T2 acceptance:** every required key in `config/site.example.php`'s shape is validated for
presence and type; `default_language` not in `languages`, an invalid `url_prefix`, `url_prefix:
never` with more than one language, and a permalink using an undocumented `{token}` are each
build-blocking errors (§7.1, §8.1). Multiple bad keys in one config produce one report listing
all of them, not an exception on the first. `config/site.example.php` itself loads without
error.

**T3 acceptance:** a path outside the content root — via `../` traversal, a symlink resolving
outside it, or a sibling directory sharing a string prefix with the root (e.g. `content-other/`
next to `content/`) — is rejected, not just a naive prefix check. A disallowed extension, a
missing/unreadable file, and a file over the size cap are each a distinct, identifiable error.
The extension check is case-insensitive.

**T4 acceptance:** every build-blocking condition in §5.5 that concerns front matter has a
failing-case test. Multiple bad documents produce one report listing all of them, not an
exception on the first. Front matter is never passed to the renderer.

**T5 acceptance:** `Sicherheitsprüfung` → `sicherheitspruefung` (not `sicherheitsprfung`).
A slug supplied in front matter survives untouched, including one that the slugifier would
otherwise rewrite.

**T6 acceptance:** `src/Render/Md2Html.php` is namespaced `Cuniform\Render\Md2Html`, autoloads
like any other engine class (no bare `require`, no global namespace), passes PHPStan level 8,
and has unit tests for each ported bug fix. `src/Render/CHANGELOG-FORK.md` records the
starting commit (`SilverDay/md2html-php@f1e0162`) and every change made since. No DOM
post-processing stage exists anywhere in C4 for the §4.3 features — they are built into the
fork directly.

**T7 acceptance:** the adapter reads a file only through the filesystem gateway (T3) — never
`file_get_contents()` directly — and calls the renderer with `convert(string)` in headless
mode, never `convertFile()`. Front matter never reaches the rendered output (verified
end-to-end, not just at the parser). Headings collected during rendering are exposed for the
future `[toc]` shortcode (T9). A disallowed path or invalid front matter surfaces as
`ContentException`, propagated rather than swallowed.

**T8 acceptance:** a shortcode written inside a fenced code block renders as literal text.
A block-level shortcode does not leave a stray `<p>` wrapper. A placeholder token is never
visible in output.

**T9 acceptance:** every handler escapes its attributes; a `src` with a `javascript:` scheme
is rejected, not rendered. `[include]` across languages is a build error. Include depth > 2
and include cycles are build errors.

---

## M2 — Languages

| # | Status | Task | Deps | Spec |
|---|--------|------|------|------|
| T10 | [x] | Language resolver: derive language from tree, validate against config, reject unknown dirs | T2, T4 | §7.2 |
| T11 | [x] | Translation grouping by `translation_key`, duplicate detection | T10 | §7.3 |
| T12 | [x] | Route builder: `{L}` substitution for all three `url_prefix` modes, reserved slugs, namespace collision detection | T2, T5, T10 | §7.4, §8.1, §8.2 |
| T13 | [x] | UI string catalogue and `t()`, missing-key detection | T2 | §7.8 |
| T14 | [x] | hreflang set construction, `x-default`, self-reference, published-only filtering | T11, T12 | §7.5 |
| T15 | [x] | Date/number formatting via `IntlDateFormatter` with documented fallback | T13 | §7.9 |

**T10 acceptance:** a document's language is read from its path, never from front matter (no
`lang` key exists to read — SPEC §5.2). A `posts/` or `pages/` subdirectory whose name is not
in the configured `languages` is a build error, not a silently skipped folder — including on
a single-language site, where the language segment is still required (§7.2).

**T11 acceptance:** two documents in different languages sharing a `translation_key` group
together; a key used by only one language is normal, not a warning; the same key used twice
within one language is a build error naming both documents (§7.3).

**T12 acceptance:** the same corpus builds correctly under `always`, `auto`, and `never`.
Under `never` with two configured languages, config validation fails. A post claiming `/en/`,
`/tag/`, `/admin`, or a legal-page slug is a build error.

**T13 acceptance:** a missing key for a configured language fails the build. It does not fall
back to the default language.

**T14 acceptance:** a single-language site emits no `alternate` links, no `x-default`, and no
`og:locale:alternate`. An asymmetric hreflang set fails the build.

**T15 acceptance:** `2026-03-14` formats as `14. März 2026` for `de` and `14 March 2026` for
`en`, both via `IntlDateFormatter` and via the fallback path with ext-intl unavailable — the
fallback reads month names from the UI string catalogue (`month_01`..`month_12`, T13) rather
than `strftime()` or system locale data (§7.9).

---

## M3 — Templates and build

| # | Status | Task | Deps | Spec |
|---|--------|------|------|------|
| T16 | [x] | ViewModel objects and escaping helpers `e`/`eAttr`/`eUrl`/`eJs`; escaping lint in `make lint` | T7 | §9 |
| T17 | [ ] | Template set: layout, post, page, index, tag, series, archive, search, 404, feed | T14, T16 | §9 |
| T18 | [x] | Page hierarchy and nav trees per language, `nav_*` handling, three-level cap | T12 | §6.2, §6.3 |
| T19 | [x] | Build pipeline stages 1–6: lock, discover, parse, resolve, render, template | T17, T18 | §10.1 |
| T20 | [x] | Artifacts: per-language feeds, sitemap with alternates, search index with threshold warning, robots.txt, security.txt, asset fingerprinting | T19 | §11 |
| T21 | [x] | Redirect map compilation and the hand-written feed redirect entry | T12 | §7.11, §8.3 |
| T22 | [x] | Build verification (§10.3), including the URL-scheme-change guard | T20, T21 | §10.3 |
| T23 | [ ] | Atomic deploy, release pruning, `--rollback` | T22 | §10.4 |
| T24 | [ ] | Incremental build cache and invalidation, including translation-group invalidation | T19 | §10.2 |

**T16 acceptance:** `e`/`eAttr`/`eUrl`/`eJs` are the only four names `make lint`'s
escaping-lint accepts after a bare `<?=` (plus `t`, already true from T13's design). `eUrl`
rejects a `javascript:`/`data:` scheme the same way the shortcode handlers' own URL
sanitizer does — reused, not reimplemented a second time. `ViewModel` exposes `language`,
`canonicalUrl`, and a nullable `hreflang`, and `t()` delegates to the UI string catalogue for
its own language.

**T17 progress (not yet done):** `layout.php`, `post.php`, `page.php`, and the partials that
don't need aggregated data (`head`, `nav-primary`, `nav-footer`, `lang-switcher`, `toc`) are
built and tested, along with the rendering mechanism (`TemplateRenderer`, `TemplateResolver`).
Not started: `index.php`, `tag.php`, `series.php`, `archive.php`, `search.php`, `404.php`,
`feed.xml.php`, and the `post-card`/`pagination` partials — all need aggregated corpus data
(post listings, pagination state, tag/series indexes, a search index) that doesn't exist until
T18/T19 build it. `nav-primary`/`nav-footer` currently render whatever `NavItem` list they're
given but nothing builds that list yet (T18); `lang-switcher` doesn't yet render a disabled/
home-link state for a language with no translation (§7.7) — both to revisit once T18 lands.

**T18 acceptance:** a page nested more than three levels below the language segment is a
build error (§6.3). A page needs `nav_order` set to appear in a nav tree at all — "reachable
but not in nav" otherwise (§6.2); `nav_group: none` excludes it even with `nav_order` set. A
directory referenced by another page's position but with no `index.md` of its own produces a
warning, not a build failure. `nav_parent` overrides directory position when given.

**T19 acceptance:** a second concurrent build is rejected by `flock`, not queued. A template
error aborts the build with no output written.

**T19 scope note:** stages 1-6 run for real, including from `bin/cuniform build` — both plain
and `--dry-run` — but only produce the routes T17's current template set can render (posts and
pages; T17's remaining templates are still unbuilt, so no index/tag/series/archive/search/404
route is generated yet). Deliberately not built in the Resolve stage, matching this project's
"don't build ahead of a consumer" convention (see `ResolvedSite`'s docblock): tag/series
indexes and prev/next (nothing renders them until T17 finishes and T20 exists), and compiling
`aliases` into `redirects.map` (that's T21's own task — the `aliases`-collides-with-a-route
*validation* SPEC §5.5 requires is still enforced now, since it doesn't need the map itself).
A plain (non-`--dry-run`) build writes a complete release tree under `releases/<timestamp>/`
but never touches `public/` — Verify/Deploy are T22-T23, so `--rollback` still reports
not implemented. `config/lang/{de,en}.php` are now populated for real (`updated_on` plus the
`month_01`..`month_12` fallback table T15 needs).

**T20 note:** BUILD-ORDER lists no explicit acceptance criteria for T20 (or T21); scope was
derived directly from §11, which is unambiguous about what each artifact must contain. `noindex`
excludes a document from the sitemap only (§5.2/§11.2) — the page itself still builds and now
also emits `<meta name="robots" content="noindex">` (a gap in T17's `head.php`, fixed here since
this task is what first needed `noindex` to actually do something beyond being carried in the
ViewModel). `security.txt`'s `Contact` reuses `config.mail.notify` rather than a new config key.
`style.css` — required by §9's Styling section but never written by any earlier task — is
authored at `templates/style.css` and fingerprinted from there; `LayoutContext` gained a
`stylesheetUrl` field so `head.php` links the actual fingerprinted file instead of a hardcoded
`/style.css`. `ViewModel` gained `noindex`/`toc`/`headings` (previously duplicated identically on
`PostViewModel` and `PageViewModel`) so `head.php` can read `noindex` without an `instanceof`
check — existing constructor call sites are unaffected, since `PostViewModel`/`PageViewModel`'s
own public constructor signatures didn't change, only what they forward to `parent::__construct()`.
Also fixed in this task: `SiteResolver` was building hreflang sets from route-relative paths
instead of absolute URLs — SPEC §7.5's own example, and the existing T17 template tests, both
expect an absolute `href`. Caught here because the sitemap needs the same URLs and a
substring-only assertion in T19's own test hadn't caught the missing scheme/host.

**T21 note:** compiled to a `RedirectMatch 301` block (`redirects.conf`, one of the two formats
§8.3 offers — chosen over a `RewriteMap txt:` file since it needs no companion map file to wire
up later) written into the release tree like any other artifact; actually `Include`-ing it from
the vhost config and reloading Apache on deploy is T27's job, not this one's. Combines each
document's own `aliases` (SPEC §5.2) with manual entries from `content/redirects.map` (now a
real, committed file — see below), rejects two entries claiming the same old-path, and drops a
manual entry for `/` with a warning per §8.3's own ordering caveat. Deliberately does **not**
check that a redirect's target resolves — that's explicitly a §10.3/T22 concern, which runs
after the full release tree exists to check against; this stage only has stage 4's URLs and the
raw manual entries. `content/redirects.map` now carries the one entry SPEC's own prose gives
verbatim (§7.11): `/feed.xml` → `/de/feed.xml`, preserving the legacy (German) feed subscribers
through the relaunch even though `en` is now `default_language`.

**T22 acceptance:** each verification condition in §10.3 has a test that makes it fire.
A redirect pointing at a non-existent path blocks the deploy. Changing `url_prefix` without
`--allow-url-scheme-change` blocks the deploy and prints the vhost directives that must change.

**T22 note:** two conditions §10.3 lists were already enforced earlier in the pipeline before
this task (alias-shadows-a-route and reserved-slug, both in `SiteResolver`/T19; hreflang
asymmetry, `HreflangSymmetryChecker`/T14; missing UI string key, `UiStringCatalogue`/T13) — not
re-implemented, only confirmed each still has a firing test. This task built the rest: well-
formedness, internal-link/media resolution, the page-count-drop guard, the URL-scheme-change
guard, and redirect-target resolution — all in `BuildVerifier`, run as stage 8 whether or not
`--dry-run` was given (a dry run's whole point is telling you whether a real build *would*
succeed).

Two things had to be built to make verification meaningful rather than vacuous, neither owned
by an earlier task: `MediaCopier` actually copies `content/media/` into the release (SPEC §7.10
lists it as a shared artifact, but nothing before this ever wrote it — without it, "any
referenced media file is missing" had nothing to check against) and `WellFormednessChecker`
does *not* use `DOMDocument`'s HTML parser directly, because that parser silently repairs
almost anything short of a null byte and would never actually fire; it self-closes void
elements and expands bare boolean attributes (`<video controls>` → `controls="controls"` —
valid HTML5, invalid XML, and something `VideoHandler` genuinely emits) before parsing as
strict XML, which reliably catches real tag-mismatch bugs.

One deliberate, documented carve-out: `InternalLinkChecker` treats a link to a bare language
home path (`/en/`, `/de/`) as a warning, not a build-blocking error. Every current template
that builds a `HreflangSet` points `x-default` at exactly that path (SPEC §7.5), but no route
generates it yet — T17's `index.php` remains unbuilt — so on any multi-language site it can
never resolve *by construction* today. Treating it as fatal would make full verification
permanently unpassable rather than catching a real mistake. Remove the carve-out once T17
builds the home route; a genuinely broken authored link still fails the build today.

The URL-scheme-change guard and the page-count-drop guard both need something to compare
against; deploy (`public/`'s symlink) doesn't exist yet (T23), so `UrlSchemeGuard` persists a
snapshot to `var/last-build-meta.json` (never served — build-internal bookkeeping, not a
release artifact) after every successful non-dry-run build, and `PageCountGuard` reads the
most recent existing directory under `paths.releases`. Both are written to switch to whatever
T23 actually deploys without changing their own contracts.

**T23 acceptance:** the swap is atomic — a loop requesting a page during a deploy never sees a
404 or a partial page. Rollback restores the previous release and is verified by a request.

**T24 acceptance:** editing one post rebuilds that post, its list pages, and every page in its
translation group. Editing a UI string file or a template rebuilds everything.

---

## M4 — Cutover

| # | Status | Task | Deps | Spec |
|---|--------|------|------|------|
| T25 | [ ] | Legacy URL enumeration tooling: crawl the existing site, emit a URL inventory | — | §15.5, §19 item 5 |
| T26 | [ ] | Legacy snapshot support: carry a frozen static tree through builds without collision | T23, T25 | §15.5 |
| T27 | [ ] | Apache vhost config, systemd units (build consumer, scheduled-post timer), one-time `public` symlink setup | T23 | §3.2, §10.5, §15.1 |

**T27 acceptance:** ACME renewal succeeds across a deploy — verify by forcing a renewal and
running a build during the challenge window. The per-language 404 fires inside each prefix and
the neutral 404 fires outside them.

---

## M5 — Admin

| # | Status | Task | Deps | Spec |
|---|--------|------|------|------|
| T28 | [ ] | Auth: Argon2id, TOTP, recovery codes, sessions, rate limiting | T23 | §13.1 |
| T29 | [ ] | Editor with front matter form, SHA-256 conflict detection, git commit via `proc_open` | T28 | §12 |
| T30 | [ ] | Preview rendering through the identical C3/C4/C5 chain | T29 | §12 |
| T31 | [ ] | Media library with upload validation and re-encoding | T28 | §13.2 |
| T32 | [ ] | Build enqueue via request file, consumed by the systemd unit | T27, T28 | §10.5 |
| T33 | [ ] | Dashboard, lists, translation-status visibility, build log, rollback, audit log | T29 | §13.3 |

**T30 acceptance:** preview output is byte-identical to build output for the same document,
apart from the injected banner. A divergence is a failing test, not a note.

**T32 acceptance:** the admin process cannot write to `releases/` or `public` — verified by
file permissions, not by convention.

---

## M6 — Import (P3)

| # | Status | Task | Deps | Spec |
|---|--------|------|------|------|
| T34 | [ ] | WXR streaming parser with external entities disabled | T33 | §A.1 |
| T35 | [ ] | HTML→Markdown converter constrained to supported constructs; unknown shortcodes preserved and reported | T34 | §A.3 |
| T36 | [ ] | Media downloader with host allow-list and checksums | T34 | §A.3 |
| T37 | [ ] | Front matter emission, verbatim slugs, redirect generation for every document | T35 | §A.3 |
| T38 | [ ] | Verification: count reconciliation, URL diff, word-count tolerance, migration report | T37 | §A.4 |
| T39 | [ ] | Manual review tracking file and checklist workflow | T38 | §A.5 |

**T35 acceptance:** the converter never emits raw HTML into a `.md` file. An unknown
shortcode appears in both the output file and the report — never dropped silently.

**T38 acceptance:** the import is idempotent — running it twice against the same export
produces identical output. Any count delta between export and generated files is a hard failure.

---

## Standing rules

- A task that turns out to need a dependency, a framework, or a database is a task that needs
  a conversation first. Stop and say so.
- If the spec is silent or ambiguous, ask. Do not decide in code and document it in a comment.
- Tests ship with the code. A task with passing acceptance criteria but no tests is not done.
