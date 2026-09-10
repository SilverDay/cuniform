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
| T17 | [x] | Template set: layout, post, page, index, tag, series, archive, search, 404, feed | T14, T16 | §9 |
| T18 | [x] | Page hierarchy and nav trees per language, `nav_*` handling, three-level cap | T12 | §6.2, §6.3 |
| T19 | [x] | Build pipeline stages 1–6: lock, discover, parse, resolve, render, template | T17, T18 | §10.1 |
| T20 | [x] | Artifacts: per-language feeds, sitemap with alternates, search index with threshold warning, robots.txt, security.txt, asset fingerprinting | T19 | §11 |
| T21 | [x] | Redirect map compilation and the hand-written feed redirect entry | T12 | §7.11, §8.3 |
| T22 | [x] | Build verification (§10.3), including the URL-scheme-change guard | T20, T21 | §10.3 |
| T23 | [x] | Atomic deploy, release pruning, `--rollback` | T22 | §10.4 |
| T24 | [x] | Incremental build cache and invalidation, including translation-group invalidation | T19 | §10.2 |

**T16 acceptance:** `e`/`eAttr`/`eUrl`/`eJs` are the only four names `make lint`'s
escaping-lint accepts after a bare `<?=` (plus `t`, already true from T13's design). `eUrl`
rejects a `javascript:`/`data:` scheme the same way the shortcode handlers' own URL
sanitizer does — reused, not reimplemented a second time. `ViewModel` exposes `language`,
`canonicalUrl`, and a nullable `hreflang`, and `t()` delegates to the UI string catalogue for
its own language.

**T17 note:** the remaining templates — `index.php`, `tag.php`, `series.php`, `archive.php`,
`search.php`, `404.php`, plus `partials/post-card.php` and `partials/pagination.php` — are
built, alongside the aggregation they needed and nothing before this task had a reason to
build: `ListingResolver` (a second, independent read of `ResolvedSite`, producing per-language
post lists, tag archives, series archives, and year archives — see `ListingSet`'s own
docblock for why this isn't folded into `SiteResolver`) and `ListingTemplateStage` (renders
all of it, parallel to `SiteTemplateStage`). `Paginator` splits any list into pages of
`posts_per_page`, always producing at least one — even empty — page, since the language home
route must resolve even for a brand-new site with zero posts (§7.4.1).

`feed.xml.php` is deliberately absent from the allow-list: `FeedGenerator` (T20) already
builds RSS 2.0 and Atom directly with `DOMDocument`, so no PHP template ever renders a feed —
see `BuildPipeline`'s and `TemplateResolver`'s own docblocks. `SitemapGenerator` (T20) now
also emits each listing type's *first* page — `/{L}`, each tag archive, each series index,
each year archive — since SPEC §11.2's "excludes ... pagination beyond page 1" only makes
sense once something has pagination beyond page 1; the search page and 404s are excluded, not
being content a search engine should index. `AssetFingerprinter` (T20) is generalized to
derive `<name>.<hash8>.<ext>` from the source file's own name rather than a hardcoded
`style.css`, so `search.js` (search.php's external script — SPEC §14.1 prefers no inline
script at all on public pages, since a static page carries no per-request CSP nonce) fingerprints
the same way.

`InternalLinkChecker`'s bare-language-home-link carve-out (added in T22) is removed: `/{L}/`
now really is generated, so a link to it is checked exactly like any other internal link — its
constructor dropped the `$languages` parameter it no longer needs, and `check()` returns a
plain `list<string>` instead of an `{errors, warnings}` shape that could now only ever produce
an empty `warnings` array.

The neutral root `/404.html` (§7.12) can't go through `layout.php` at all — that file requires
a single `<html lang>`, and this page deliberately carries every configured language's own
blurb and home link, each in its own `lang` attribute (NFR-4). It's rendered by a small
template of its own, `404-root.php` (via `NeutralErrorContext`, not a `ViewModel`), and is
**always** generated regardless of `url_prefix` — SPEC §3.2's vhost skeleton declares
`ErrorDocument 404 /404.html` unconditionally, only *adding* the per-language
`<Location>` overrides when prefixed, so the neutral page's own necessity doesn't depend on
how many languages are configured. The per-language `/{L}404.html` is generated only when that
language is actually prefixed (`RouteBuilder::prefixFor($language) !== ''`), matching §7.12's
"unprefixed single-language sites need only the root 404.html."

Two judgment calls made where SPEC is silent, both documented in code and worth confirming
rather than treated as settled:

1. **Series ordering.** §5.3 says series "groups posts within one language" but doesn't state
   an order. This implementation uses reverse-chronological, same as every other listing type
   (§6.1's convention for posts generally) — an alternative reading (publication order, i.e.
   ascending, so a series reads start-to-finish) is equally defensible and would be a one-line
   change in `ListingResolver::seriesArchives()` if that's the intended reading.
2. **Listing pages carry no hreflang / no language switcher.** A generated listing page
   (index, tag, series, archive, search, 404) isn't a translated document with a
   `translation_key`, so `ListingTemplateStage` gives every one of them a null `HreflangSet` —
   consistent with how an untranslated real document already behaves (§7.7), but it also means
   the switcher renders nothing on the home page and search page in a multi-language site,
   which reads more like a real gap than a deliberate simplification for those two specifically
   (their equivalent page always exists in every configured language, unlike a tag or series
   archive, which may not). Worth revisiting if this is worse than what stands.

`RouteBuilder` gained `indexRoute()`/`tagRoute()`/`seriesRoute()`/`archiveRoute()`/
`searchRoute()`/`errorRoute()` — none of them go through `RouteTable::register()`, since every
one starts with a word §8.2 already reserves (`tag`, `series`, `archive`, `page`, `search`),
so a real content document can never collide with one by construction. `ConfigFixture` (test
helper) gained an optional `postsPerPage` parameter, needed to test pagination boundaries
without a large fixture corpus.

**T18 acceptance:** a page nested more than three levels below the language segment is a
build error (§6.3). A page needs `nav_order` set to appear in a nav tree at all — "reachable
but not in nav" otherwise (§6.2); `nav_group: none` excludes it even with `nav_order` set. A
directory referenced by another page's position but with no `index.md` of its own produces a
warning, not a build failure. `nav_parent` overrides directory position when given.

**T19 acceptance:** a second concurrent build is rejected by `flock`, not queued. A template
error aborts the build with no output written.

**T19 scope note (as of this task; T17/T22/T23 later filled every gap named below):** stages
1-6 run for real, including from `bin/cuniform build` — both plain and `--dry-run` — but only
produce the routes T17's *then*-current template set could render (posts and pages; T17's
remaining templates were still unbuilt at this point, so no index/tag/series/archive/search/404
route was generated yet — T17 later closed this). Deliberately not built in the Resolve stage,
matching this project's "don't build ahead of a consumer" convention (see `ResolvedSite`'s
docblock): tag/series indexes and prev/next (nothing rendered them until T17 finished and T20
existed — T17 ultimately built this as `ListingResolver`, kept separate from `ResolvedSite`
rather than added to it; see T17's own note), and compiling `aliases` into `redirects.map`
(that was T21's own task — the `aliases`-collides-with-a-route *validation* SPEC §5.5 requires
was still enforced at this point, since it didn't need the map itself). A plain
(non-`--dry-run`) build writes a complete release tree under `releases/<timestamp>/` but at
this point never touched `public/` — Verify/Deploy were T22-T23, so `--rollback` still reported
not implemented until T23. `config/lang/{de,en}.php` are now populated for real (`updated_on`
plus the `month_01`..`month_12` fallback table T15 needs).

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

~~One deliberate, documented carve-out: `InternalLinkChecker` treats a link to a bare language
home path (`/en/`, `/de/`) as a warning, not a build-blocking error.~~ **Resolved in T17:**
`index.php` now generates the home route for every configured language, so the carve-out was
removed — see the T17 note below. A genuinely broken authored link still fails the build.

The URL-scheme-change guard and the page-count-drop guard both need something to compare
against; deploy (`public/`'s symlink) doesn't exist yet (T23), so `UrlSchemeGuard` persists a
snapshot to `var/last-build-meta.json` (never served — build-internal bookkeeping, not a
release artifact) after every successful non-dry-run build, and `PageCountGuard` reads the
most recent existing directory under `paths.releases`. Both are written to switch to whatever
T23 actually deploys without changing their own contracts.

**T23 acceptance:** the swap is atomic — a loop requesting a page during a deploy never sees a
404 or a partial page. Rollback restores the previous release and is verified by a request.

**T23 note:** `ReleaseDeployer` does the swap with `symlink()` + `rename()` directly rather than
shelling out to `mv -T` — `rename()` onto an existing path is the same single filesystem
operation SPEC §10.4's shown script gets from `mv`, so there's nothing `mv` buys that a syscall
doesn't already give. Atomicity is verified by
`ReleaseDeployerTest::testConcurrentReadsDuringRepeatedDeploysNeverSeeAMissingOrPartialPage`,
which runs a separate PHP process (`proc_open`) reading through the live `public/` symlink in a
tight loop while the test process deploys back and forth between two releases for a second —
the closest a single-host test gets to "a loop requesting a page during a deploy", short of an
actual Apache instance. `UrlSchemeGuard`/`PageCountGuard` (T22) already read from
`paths.releases` and `var/last-build-meta.json` rather than `public/`'s target, exactly as their
own T22 notes anticipated — neither needed to change.

One case handled deliberately rather than left to fail with a raw filesystem error: `public/`
existing as an empty real directory (true of every fixture in this test suite before its first
deploy, and — per SPEC §10.4's "one-time setup" — of a fresh checkout before its first real
deploy too) is silently replaced with the symlink, since there's nothing in it to lose. A
*non-empty* real directory is refused with a message pointing at §10.4/§3.3/§15.5 rather than
deleted — moving real content out is the cutover-freeze decision T27 (`one-time public symlink
setup`) actually owns, not something to do unasked at deploy time (this project's own "risky
action" convention: don't delete what you didn't create without being asked).

Release pruning (`build.retain_releases`, already validated by T2/ConfigLoader and unused until
now) only ever runs immediately after `deploy()`'s own swap, so the just-deployed release is
always the newest one and always inside the retained window by construction — an earlier draft
also special-cased "never prune whatever `public/` currently points at" for a rollback's sake,
but that branch could never fire (prune always observes the release it just swapped to, not
whatever was live a moment before) and was removed rather than kept as inert defensive code.
`rollback()` itself never prunes, so a rollback can still reach a release a *later* deploy will
go on to prune — `ReleaseDeployerTest::testRollbackNeverPrunesAndCanReachAReleaseOutsideTheRetainWindow`
covers exactly that ordering.

`bin/cuniform build --rollback` now does real work: it loads config, takes the same `BuildLock`
a build itself would hold (so a rollback and a concurrent build's deploy can't race), and calls
`ReleaseDeployer::rollback()`. The CLI's "deploy is not implemented" message is gone; a plain
build now reports `deployed -> <public path>` once the swap succeeds.

**T24 acceptance:** editing one post rebuilds that post, its list pages, and every page in its
translation group. Editing a UI string file or a template rebuilds everything.

**T24 note:** Discover/Parse/Resolve/Listing (stages 2-4, plus `ListingResolver`) run in full
for every build regardless of caching — they're cheap (front-matter parsing, not Markdown
conversion), and routes/nav/hreflang/listing aggregation are only meaningful computed for the
whole corpus at once anyway. What's actually cached, keyed by
`ParsedDocument::identifier()`, is the expensive per-document work SPEC §10.2's formula
describes: Render (stage 5 — Markdown + shortcode conversion via the renderer fork) and that
document's own Template step (stage 6 — `SiteTemplateStage`'s post.php/page.php + layout.php
render). A document outside the dirty set skips both and reuses its previous build's rendered
body and already-assembled page bytes directly from `BuildCache` (`var/build-cache.json` —
build-internal bookkeeping, same as `var/last-build-meta.json`, T22). This means "its list
pages" (index/tag/series/archive) are satisfied trivially and correctly without needing
pagination-boundary-aware dependency tracking: `ListingTemplateStage` always re-templates every
listing route from freshly-parsed front matter every build (cheap — cards use only title/
summary/date/tags, never the rendered body), so a changed post's appearance there is always
right regardless of whether its own Render/Template step was skipped. Verify (stage 8) also
always runs against the complete page set, cached pages included — a cached page's own bytes
being unchanged says nothing about whether something it links to still exists in *this*
release, so it isn't exempted.

`BuildCacheKey` computes SPEC §10.2's formula (`SHA-256(file) + SHA-256(templates) +
SHA-256(UI strings) + engine version + SHA-256(Md2Html.php)`) with the last four folded into
one `$globalSuffix` shared by every document's key — which is also what makes "a changed
template, UI string file, [or renderer] invalidates everything" fall out for free: change any
of them and *every* document's key differs, no separate rule needed. `EngineVersion::VERSION`
is a new small constant for the "engine version" component; `Md2Html.php`'s own path is found
via `ReflectionClass` rather than threading another constructor parameter through, since it's
always exactly wherever the autoloader put it. Also folded into `$globalSuffix`, beyond SPEC's
literal formula: the parts of `Config` that are embedded directly into every cached page's
`<head>` or route (`base_url`, `title`, `languages`, `default_language`, `url_prefix`,
`permalink`) — otherwise a config edit could produce stale cached output with nothing to catch
it. `templates/` is hashed as a whole directory tree, not just `.php` files — `style.css` and
`search.js` live there too, and a cached page's `<head>` embeds their *fingerprinted* URL, which
changes whenever their contents do; missing that would leave cached pages linking a stylesheet
filename that no longer exists in the new release.

The nav tree ("a nav-affecting page invalidates everything," SPEC §10.2) needed a separate
mechanism: it isn't a static file, and it's derived from every page's front matter
collectively, not any one document's own hash. `BuildCacheKey::navHash()` serializes the nav
tree per language and hashes it; `IncrementalPlanner` compares it build-to-build and, if it
differs, bypasses per-document diffing entirely — every document is dirty, full stop. Every
cached page's `layout.php` chrome embeds the nav tree, so this is exactly as broad as it needs
to be.

Translation-group propagation (SPEC §10.2: "every page in its translation group [changes,
since] its hreflang set changed") is the one rule that genuinely can't be derived from a
per-document key comparison — a document's own key can be unchanged while its hreflang
alternates list is stale, because a *sibling* was edited, added, or removed.
`IncrementalPlanner` handles all three: for every `translation_key` seen in either this
build's documents or the previous manifest's cached entries, if the member set changed (a
sibling appeared or disappeared) or any current member is already directly dirty, every
*current* member of that group is marked dirty too — including one whose own content is
completely unchanged. This is deliberately coarse (SPEC's own "any ambiguity resolves toward a
full rebuild"): it doesn't check whether the actual change would affect the sibling's rendered
hreflang output, just whether anything in the group moved.

One dependency this task does **not** track, documented prominently in `IncrementalPlanner`'s
own docblock rather than left to be discovered as a bug: `[include]` (SPEC §6.5). A page
reached only via `[include]`, not edited directly itself, does not propagate to documents that
include it — `DocumentRenderer`'s `IncludeResolvingPageRepository` resolves include targets
independently and always fresh regardless of this cache, but the *including* document's own
top-level render can still be served from cache even though the page it includes changed.
`--full` (now actually doing something — previously accepted and ignored) is the workaround;
closing this gap for real would mean `RenderedDocument` reporting which pages it included,
which is a reasonable follow-up but out of this task's scope.

---

## M4 — Cutover

| # | Status | Task | Deps | Spec |
|---|--------|------|------|------|
| T25 | [x] | Legacy URL enumeration tooling: crawl the existing site, emit a URL inventory | — | §15.5, §19 item 5 |
| T26 | [n/a] | ~~Legacy snapshot support: carry a frozen static tree through builds without collision~~ | T23, T25 | §15.5 |
| T27 | [ ] | Apache vhost config, systemd units (build consumer, scheduled-post timer), one-time `public` symlink setup | T23 | §3.2, §10.5, §15.1 |

**T25 note:** BUILD-ORDER lists no explicit acceptance criteria for T25; scope was derived
directly from §15.5 and §19 item 5, whose own text already draws the boundary: "the complete
list of live URLs... a crawl of the existing sitemap is the practical source; if the current
site has no sitemap, a `wget --spider --recursive` run produces one." That's enumeration —
*which paths exist* — not §15.5's separate `wget --mirror` (T26's job: downloading full pages
for the frozen-snapshot option). `bin/cuniform legacy-urls <base-url> [--output=<path>]
[--max-pages=<n>]` is a new, standalone CLI command (`src/Cutover/`, a new namespace for M4's
tooling) — it never runs as part of `bin/cuniform build` and has nothing to do with the build
pipeline; `LegacyUrlCrawler::crawl()` tries `{base-url}/sitemap.xml` first (recursing through a
sitemap index if that's what it finds — common on WordPress, SPEC's own Appendix A assumption,
e.g. Yoast's `sitemap_index.xml`), and only falls back to a same-host, robots.txt-respecting
spider when no sitemap exists at all. Output is a JSON `UrlInventory`
(`var/legacy-urls.json` by default) — path, HTTP status, content type, and discovery method
per entry; a sitemap-sourced entry carries no status/content-type (SPEC's own text is about
enumeration, and verifying every sitemap URL would be a second full pass this task doesn't
need — see LegacyUrlEntry's own docblock).

Built on PHP streams (`file_get_contents` + a stream context), not ext-curl — SPEC §4.2/§15.1
name no HTTP-client extension as even a soft requirement, and streams need only
`allow_url_fopen` (PHP's own default). Every network-touching class sits behind the
`HttpFetcher` interface specifically so `LegacyUrlCrawler`'s own logic — sitemap-index
recursion, same-host link resolution, robots.txt handling, the max-pages cap, dedup — is
fully unit-tested against a fake fetcher with zero real network access
(php-style.md: "No network ... in tests"); `StreamHttpFetcher`, the one class that actually
opens a socket, is intentionally thin and untested directly, with its one non-trivial piece
(parsing `$http_response_header`'s possibly-multi-hop shape after a followed redirect) pulled
out into `HttpResponseHeaderParser`, a pure function that *is* tested. robots.txt handling
(`RobotsTxt`) is a courtesy, not a SPEC requirement — a deliberately simplified, best-effort
reader (no wildcards, no per-record multi-agent grouping), documented as such in its own
docblock; a missing or unparseable robots.txt is always treated as allow-all, never a crawl
failure.

**T26 status — not applicable, resolved 2026-09-09 (SPEC §15.5, §19 item 4).** The operator
took the live site down before this task could be started, and confirmed a WordPress XML
export (WXR) exists instead of a live site to crawl/mirror. Freeze-and-serve — the cutover
option this task exists to support — needs a *live* site (`wget --mirror` against something
still serving), which is no longer possible; there is nothing left to snapshot. The operator's
chosen replacement is to pull the P3 import (M6, Appendix A) forward instead — see M6's own
reprioritization note below and SPEC §15.5's resolution note for the full reasoning. `[n/a]`
here is a new status marker this file hasn't used before (every prior task resolved to done or
not-yet-done) — it means "will not be built," not "not yet built," and is spelled out in full
rather than silently left `[ ]` so it doesn't read as still-pending work.

**T27 acceptance:** ACME renewal succeeds across a deploy — verify by forcing a renewal and
running a build during the challenge window. The per-language 404 fires inside each prefix and
the neutral 404 fires outside them.

**T27 status — deliverables complete, acceptance criterion not verifiable from here, status
left `[ ]` on purpose.** Every artifact SPEC §3.2/§10.5/§15.1 call for is built:

- `deploy/apache/blog.silverday.de.conf` — the vhost, including SPEC §3.2's skeleton
  transcribed faithfully (both `<Directory>` blocks, the root 302, per-language/neutral
  `ErrorDocument` mapping, the `/admin` FPM proxy), plus a port-80 vhost this file adds beyond
  SPEC's own block — SPEC's skeleton only shows :443, but Let's Encrypt's standard HTTP-01
  challenge validates over plain HTTP, so "ACME renewal succeeds" isn't achievable via the
  standard method without it — and the response headers §14.1 (CSP — no hash needed, T17's
  templates ship zero inline scripts) and §14.2 (HSTS, nosniff, etc.) call for, which no
  earlier task had a vhost file to put them in.
- `deploy/systemd/cuniform-build.service` + `.path` + `.timer` — the build-consumer unit
  (§10.5's "a separate unit consumes" half of the admin/build privilege boundary) shared by
  both triggers: `.path` watches `var/build-requested` (a convention this task defines, since
  nothing wrote to one before it — T32, "Build enqueue via request file," is what makes the
  admin app actually write it later) and `.timer` fires every 15 minutes for scheduled posts.
- `deploy/git/post-receive` — the git-push trigger (§10.5, and §12's "sole authoring path in
  P1"). Relies on `receive.denyCurrentBranch updateInstead` (documented in the hook's own
  header) rather than the hook doing its own `git checkout -f`, specifically so this composes
  cleanly with P2's admin app later committing to the *same* working tree directly (§12 Path
  B) instead of needing two reconciled checkouts.
- `bin/cuniform setup-public` (`PublicDirectorySetup`, tested) — the one-time `public/`
  provisioning step (§10.4, §3.3): an empty real directory is removed (the next build creates
  the symlink); a non-empty one is *moved aside*, never deleted, since deciding what happens to
  real content there is the still-open §15.5 cutover decision, not something this tool should
  guess. Confirmed directly relevant: this checkout's own `public/` is right now exactly that
  case — a real, non-empty, differently-owned directory from hosting provisioning — left
  untouched rather than run against, since that's a live operational action outside what this
  session should do unasked.

What's verified, and how, given this session has no live domain, no DNS control, and no
running Apache/systemd instance bound to `blog.silverday.de`:

- `apache2ctl -t` against the vhost file (loaded into a scratch config pulling in the real
  system's mod_rewrite/mod_headers/mod_alias/mod_proxy_fcgi/mod_ssl, all present on this host)
  returns `Syntax OK`.
- `systemd-analyze verify` against all three unit files returns clean, exit 0 — this also
  confirms `ExecStart`'s binaries (`/usr/bin/php`, `/bin/rm`) actually resolve.
- `sh -n` on the post-receive hook confirms shell syntax.
- `PublicDirectorySetupTest`/`ApplicationTest`'s `setup-public` cases cover all four states
  (already a symlink, doesn't exist, empty real directory, non-empty real directory) the normal
  way — `make check`.

None of that reaches the acceptance criterion's actual claim: a real ACME renewal succeeding
against a real certificate authority while a real build runs during the validation window. That
needs a live host with a public DNS record and a running certbot — categorically not something
this environment can produce, unlike, say, T23's atomicity claim, which a local `proc_open`
loop against a real filesystem could faithfully reproduce. Per this project's own rule ("do not
mark a task done until its acceptance criteria pass"), the honest status is: ready to deploy,
not yet operationally verified. Flip T27 to `[x]` once that's actually been done against the
real host — the per-language/neutral 404 half of the criterion is mechanically re-checkable at
that point too (`curl -I https://blog.silverday.de/de/does-not-exist/` should come back 404 via
`/de/404.html`, and something outside any prefix via the neutral `/404.html`).

---

## M5 — Admin

| # | Status | Task | Deps | Spec |
|---|--------|------|------|------|
| T28 | [x] | Auth: Argon2id, TOTP, recovery codes, sessions, rate limiting | T23 | §13.1 |
| T29 | [x] | Editor with front matter form, SHA-256 conflict detection, git commit via `proc_open` | T28 | §12 |
| T30 | [x] | Preview rendering through the identical C3/C4/C5 chain | T29 | §12 |
| T31 | [ ] | Media library with upload validation and re-encoding | T28 | §13.2 |
| T32 | [ ] | Build enqueue via request file, consumed by the systemd unit | T27, T28 | §10.5 |
| T33 | [ ] | Dashboard, lists, translation-status visibility, build log, rollback, audit log | T29 | §13.3 |

**T28 note:** BUILD-ORDER lists no explicit acceptance criteria for T28; scope was derived
directly from §13.1's five bullets (Argon2id, TOTP, recovery codes, sessions, rate limiting),
the same pattern T20/T21/T25 already established. New namespace `Cuniform\Admin\Auth\` in
`src/Admin/`, plus `Cuniform\Admin\AdminContext`/`AdminBootstrap` one level up — the wiring
layer `admin/*.php` calls into (see below). `admin/` and `phpstan.neon.dist`'s analysis paths
both go from empty to real for the first time this task; `.php-cs-fixer.dist.php` already
conditionally included `admin/` when present (T1), so no change needed there.

Two judgment calls, documented rather than silently decided (this task's own "stop and ask if
genuinely underspecified" was weighed against each and resolved in-code, matching how T17/T35/
T37 already handle spec-silent implementation details of this size):

1. **Breached-password check source.** §13.1 names no corpus. `DenylistBreachedPasswordChecker`
   (an offline, bundled common-password list, `src/Admin/Auth/data/common-passwords.txt`)
   stands in for a live k-anonymity lookup (e.g. HIBP) — the alternative would be this
   project's first outbound network call outside mail (§15.2), untestable under this project's
   own "no network in tests" rule, and a new runtime dependency on a third party staying up.
   `BreachedPasswordChecker` is an interface specifically so a stronger implementation can
   replace this one later without touching `PasswordPolicy`. Real, accepted limitation: a
   password absent from the bundled list but present in an actual breach corpus passes.
2. **Account storage and enrollment.** No database exists, so `AdminAccountStore` persists to
   `<var>/admin/accounts.json` (atomic tmp-then-rename, same pattern `Build\BuildCache` already
   uses) — a new, self-contained storage location under the existing `paths.var`, no
   `config/site.php` schema change. There is no self-registration screen (single-operator
   site, P2 scope) or admin UI yet to host a "create user" form, so account creation is
   `bin/cuniform admin-create-account <username> [--password-file=<path>]` — a new CLI command
   following T27's `setup-public` precedent ("operator runs a command on the server"). The
   password is read from a file or one line of STDIN, deliberately never from `$argv`
   (`ps`/shell history exposure) or an interactive masked prompt (untestable in the same
   process PHPUnit runs in — `readPassword()`'s own docblock draws the same "thin, untested
   real-I/O boundary" `StreamHttpFetcher` (T25) already established).

**Sessions are not PHP's built-in `session_start()`/`$_SESSION`.** `SessionStore` (and
`PendingLoginStore`, the same shape for the interim password-verified-but-not-yet-TOTP'd
state) are explicit, constructor-injected file stores — ambient superglobal session state
would fight php-style.md's "no static mutable state, constructor injection" and be far harder
to unit test without a real request lifecycle. `FileRecordStore` factors the atomic-write,
one-file-per-record-keyed-by-a-hash-of-the-key logic shared by `PendingLoginStore`,
`SessionStore`, and `RateLimiter` — the three stores that churn on every login attempt, unlike
`AdminAccountStore`'s single rarely-written file.

**AAL2 is a real two-step flow, not a single call.** `LoginService::startLogin()` (password)
returns a `PendingLogin` id on success; `completeSecondFactor()` (TOTP code, or a recovery
code — tried in that order) is what actually issues a `Session`. A wrong password and an
unknown username produce the identical `InvalidCredentials` outcome, and an unknown username
still runs a real Argon2id verify against a freshly generated hash — enumeration and timing
resistance, not just an appearance of it. `RateLimiter` is keyed by both `account:<username>`
and `ip:<address>` per attempt (either startLogin or completeSecondFactor), matching §13.1's
"per account and per source address" literally rather than picking one.

**`admin/login.php`/`logout.php`/`index.php` exist now, ahead of the editor (T29).** "Auth" as
a task is only actually exercised end-to-end through a real login screen — T29/T31/T33 will
add the protected screens these already defend (`admin_require_session()`), not the login flow
itself. All decision logic lives in `Cuniform\Admin\Auth\*`/`Cuniform\Admin\AdminBootstrap`,
unit tested there; `admin/*.php` is deliberately thin and not unit tested directly, the same
`bin/cuniform`-vs-`Cli\Application` split this project already uses — `AdminBootstrap::create()`
exists specifically so PHPStan can follow types across files, since a plain `require` injecting
local variables into a caller's scope is opaque to static analysis (verified empirically: every
`admin/*.php` variable read this way reported `variable.undefined` until routed through a typed
factory call instead).

CSRF (§13.2, applied here since login/logout are state-changing) is double-submit-cookie style
(`CsrfToken`, `AdminCookie::CSRF_NAME`) rather than a session-keyed synchronizer token, since
the password step has no session yet to key one against — one mechanism for every admin form,
not two. §14.1/§14.2's response headers are set by every `admin/*.php` entry point
(`admin_security_headers()`); admin responses are dynamic, so unlike the public CSP these pages
just ship zero script at all (`script-src 'none'`) rather than needing the hashed-inline-script
workaround.

Verified end-to-end against the real code path, not just PHPUnit: `bin/cuniform
admin-create-account` against a scratch `var/`, then a full HTTP round trip (PHP's built-in
server against this checkout's real `admin/`) — password step → TOTP step (code computed via
the real `Totp` class from the printed secret) → authenticated `index.php` showing "Signed in
as operator" → logout → `index.php` redirecting back to login, plus a missing-CSRF-token POST
correctly rejected with "Your session expired". No repository state was left behind (a
temporary, gitignored `config/site.php` pointed `releases`/`public`/`var` at a scratch
directory outside the repo; removed after).

`make check`: 765 tests (up from 658), PHPStan level 8 clean across `src`, `bin`, `tests`,
`admin`, PSR-12 + escaping lint clean.

**T29 note:** BUILD-ORDER lists no explicit acceptance criteria for T29 either; scope was
derived directly from §12's own "Path B" text (front matter form, SHA-256 conflict detection,
git commit with the session identity, surfacing the translation group, "language ... determines
the file's location; changing it later is a move"), the same pattern T20/T21/T25/T28/T34 already
established. New namespace `Cuniform\Admin\Editor\` in `src/Admin/`, plus two new pages,
`admin/documents.php` (list + translation status + "new post/page" links) and `admin/editor.php`
(the front matter form itself, POSTing back to itself for save/move).

**`FrontMatterEmitter` moved from `Cuniform\Import` to `Cuniform\Content\FrontMatter`.** T37
built it as the WXR importer's own front-matter writer, but "serialize front matter back to
disk" is a general content-layer concern, not an import-specific one — `Cuniform\Admin\Editor`
depending on `Cuniform\Import` for it would have been the wrong coupling, so it moved to sit
next to `FrontMatterParser`, the class it's the inverse of. `WxrImporter` only needed a `use`
statement added; its own behaviour is unchanged (`WxrImporterTest`/`ImportVerifierTest` still
pass unmodified). Extended while there: it now accepts `int`/`float` values too, emitted bare/
unquoted rather than quoted — needed for `nav_order`/`sitemap_priority` (§6.2), which nothing
before T29 ever emitted (WxrImporter, T37, is posts-only), and which `FrontMatterParser::
optionalInt()`/`optionalFloat()` require to arrive as a real int/float, not a quoted string.

**Save validates by round-tripping through the real `FrontMatterParser`, not a second copy of
its rules.** `EditorDocumentStore::save()`/`move()` build a front matter block with
`FrontMatterEmitter` and immediately re-parse it with `FrontMatterParser` — the same class T4
built and every build-blocking condition in §5.5 is already tested against. An invalid slug,
a missing required key, `image` without `image_alt`, an out-of-range `nav_group`/`legal`/
`status` value all surface as the *authoritative* error message, not a second, differently-
worded one invented in the editor. `FrontMatterParser::SLUG_PATTERN`/`ISO8601_PATTERN` were
made `public const` (previously `private`) so the store's own pre-flight path derivation for a
*new* document — it has to guess a filename before it can even call the parser — can reuse
them instead of drifting a second copy.

**Conflict handling, and "invalid," are outcomes, not exceptions.** `EditorSaveOutcome`/
`EditorSaveStatus` mirror `Auth\LoginService`/`LoginOutcome`/`LoginStatus` (T28) exactly: a
stale SHA-256 or invalid front matter are expected, form-submission-shaped results the editor
page re-renders inline, not failures that should look like a 500 — and `AdminException` is
`final`, so a data-carrying subclass for "conflict" was never actually an option. On conflict,
`EditorSaveOutcome` carries both the freshly-reloaded `EditorDocument` and the *exact on-disk
bytes* (`currentRaw`) — the typed document alone would only let the editor page reconstruct an
approximation of what's actually on disk. `LineDiffer` (a small classic LCS diff — no runtime
dependency exists for this, CLAUDE.md) renders that against what the operator was about to
write; above 2000 lines on either side it falls back to a coarse "all removed, all added" diff
rather than building an O(n·m) table for a synchronous admin request.

**A document identifier is always resolved against `DocumentIndex::discover()`.** SPEC §13.2:
"the editor taking a document identifier, never a path from the request." `EditorDocumentStore`
never joins a request value onto the content root and trusts it — it looks the identifier up
against the actual set of files `ContentDiscoverer` (T19) finds, and `AdminException::
documentNotFound()` otherwise. `DocumentIndex::summaries()` (the richer, parsed listing
`admin/documents.php` renders) deliberately does *not* fail the whole listing over one
document's invalid front matter, unlike the build pipeline's own "collect everything, then
fail" (§5.5) — a single broken file elsewhere in the tree would otherwise make the entire editor
unusable, including for fixing the very file that's broken. It's skipped from the listing and
its error surfaced instead.

**`git push` is a real, tested `GitRepository` capability that nothing calls yet.** SPEC §12
says "optionally push," but no config key exists to opt into it, and auto-pushing to a remote
from an unattended save action is exactly the kind of hard-to-reverse, shared-state action this
project's own conventions (CLAUDE.md's "Executing actions with care") say needs an explicit
decision, not a default silently wired in. `GitRepository::push()` exists and is tested (fails
gracefully, returning `false`, rather than throwing, when there's no remote); a future opt-in
only has to call it. `addAndCommit()` sets `-c user.name=`/`-c user.email=` per invocation
rather than relying on a global `~/.gitconfig` existing for whichever system user runs the
admin FPM pool (§15.1's `cuniform-web`) — SPEC's "session identity" is the signed-in operator
(`Session::$username`, paired with `config.mail.notify` as the one already-configured operator
contact address — SPEC defines no separate per-account email, and T28 didn't add one), not a
fixed service identity. `GitRepository`'s cwd is `content/` itself, not the project root: git
walks upward from wherever it's invoked to find the repository root on its own, so a path like
`posts/en/2026/...` — already relative to `content/`, which is what every identifier in this
codebase already is — resolves correctly without needing a `content/` prefix stitched onto it.

**A move is a delete-and-add in one commit, not two.** `EditorDocumentStore::move()` writes
the new file, `unlink()`s the old one, then calls `GitRepository::addAndCommit()` with *both*
paths — `git add -A -- old new` stages the deletion and the addition together, which git's own
history view recognises as a rename, without shelling out to a second `git mv` command. A move
always requires a fresh slug (§5.4: "Slugs are per-language by design") — there's no sense in
which the old slug is still correct once the language segment changes, so `move()` takes one
explicitly rather than reusing the old value.

**Three deliberate scope reductions**, each documented in code rather than silently decided:

1. **New-page creation is flat** (`pages/<language>/<slug>.md`) — §6.3's directory hierarchy
   isn't built for *creating* a new nested page. Editing an existing nested page works fine
   (load/save operate on its already-known identifier, wherever it sits), same shape as T37's
   own "posts only, this pass."
2. **The media picker isn't built here** — SPEC §12 names it in the same breath as the front
   matter form, but BUILD-ORDER's own task split gives it to T31 ("media library with upload
   validation and re-encoding"), and a picker has nothing safe to pick from before that
   validation exists. The editor's image field is a plain path input for now, with a note in
   the UI pointing at T31.
3. **The shortcode inserter is a static reference, not a click-to-insert control.** Inserting
   a snippet at the textarea's cursor position needs JavaScript, and admin pages currently ship
   *zero* script, inline or external (`script-src 'none'`, T28) — deliberately, since SPEC
   §14.1's CSP reasoning for the *public* site doesn't even apply to admin (its responses are
   dynamic, so a nonce would work), but T28 chose the simpler "no script at all" for every
   admin page rather than opening that door for one feature. Revisiting this is a CSP decision,
   not something to do silently inside this task.

**"Publish" is the status field, not a second endpoint.** SPEC §12 lists "on save" and "on
publish" as two sub-bullets of the same editing action, differing only in whether `status`
flips to `published` too — there's nothing structurally different to build beyond what the
front matter form's `status` select already does. Actually enqueuing a build on publish is
T32's own task ("Build enqueue via request file") and isn't built yet; a save that sets
`status: published` commits the change, same as any other save, and nothing more.

Verified against a real temporary git repository (`proc_open`, not a shell string) through
`GitRepositoryTest`/`EditorDocumentStoreTest`: create, update, conflict (refuse + exact-bytes
diff), invalid front matter (no write, no commit), move (single rename-shaped commit, clean
`git status` after), and a same-content resave that correctly skips committing (`git commit`
would otherwise fail with "nothing to commit"). `make check`: 797 tests (up from 765), PHPStan
level 8 clean, PSR-12 + escaping lint clean.

**T30 acceptance:** preview output is byte-identical to build output for the same document,
apart from the injected banner. A divergence is a failing test, not a note.

**T30 note:** `PreviewRenderer` (new namespace `Cuniform\Admin\Preview\`) does not
reimplement rendering — it calls the same stage 2-6 classes `BuildPipeline` itself calls
(`ContentDiscoverer`/`DocumentParser`, `SiteResolver`, `DocumentRenderer`, `SiteTemplateStage`),
which is what makes "byte-identical to build output" a property of reuse rather than
something to keep in sync by hand. The one substitution: the document being previewed is
spliced into the real, on-disk corpus in its own place, with `$request`'s raw bytes (an
editor buffer that may be unsaved, or may not exist on disk at all yet) standing in for
whatever `DocumentParser` would otherwise have read from disk — every other document in the
corpus still renders from disk, unaffected. `RenderAdapter`/`DocumentRenderer` each gained a
`renderContent()` alongside the existing `render()` (disk path) — same front matter parser,
same shortcode pass, same renderer instance, just skipping the filesystem-gateway read step —
so `render()`'s own behaviour and tests are untouched.

A draft or not-yet-due scheduled document (§5.6) never reaches `SiteResolver`'s included set
in a real build — but §12 requires preview to work for exactly those. One documented
judgment call: the previewed document's own `status` is coerced to `published` for this
render only, before splicing, purely for routing/hreflang/nav purposes — this is what
"renders as it will look once actually published" has to mean, and no template reads the
`status` field itself (confirmed by inspection: it appears nowhere under `templates/`), so
the coercion is invisible in the output bytes for an already-published document — exactly the
case the byte-identity test exercises.

The banner itself (`PreviewBanner`) is delimited by HTML comment markers rather than a fixed
string, with a matching `strip()` — that pairing is what makes "byte-identical apart from the
banner" mechanically testable rather than asserted by eye. It's inserted immediately after
the single `<body>` tag `layout.php` always emits, never before it, so everything CSP-relevant
in `<head>` stays exactly what a real build would produce.

`EditorDocumentStore` gained one read-only method, `previewPath()`, reusing its own existing
`deriveNewRelativePath()`/`findDiscovered()` rather than duplicating that derivation — the
same path `save()` would resolve internally, exposed so preview can splice at the right
position without guessing. Admin wiring: `AdminContext`/`AdminBootstrap` gained a
`previewRenderer` service (shares `$config`/`$editorDocumentStore` with the editor); the
editor form's field-to-`EditorSaveRequest` mapping — previously defined inline at the top of
`editor.php` — moved to a new shared file, `admin/editor_form.php`, required by both
`editor.php` and the new `admin/preview.php`, since a POST to `admin/editor.php`
(`form_action=save`) and a POST to `admin/preview.php` need to parse the identical set of
form fields and a plain `require __DIR__.'/editor.php'` would execute that page's own save
logic as a side effect. `editor.php` gained one more submit button
(`formaction="/admin/preview.php" formtarget="_blank"`) next to Save, posting the same form to
the new endpoint — consistent with T28/T29's "admin ships zero script" convention, so preview
is a real page navigation (opened in a new tab) rather than an AJAX call. `admin/preview.php`
requires a valid session and a valid CSRF token (POST-only — a GET has no buffer to render)
and always sets `X-Robots-Tag: noindex, nofollow` and `Cache-Control: no-store` (§12), on both
the success and the invalid-input path. It never writes to `content/`, `releases/`, or
`public` — `PreviewRenderer` only reads.

Verified against the real fixture corpus (`tests/fixtures/Build/content`, the same one
`BuildPipelineTest` uses): a real `BuildPipeline` run and a `PreviewRenderer::render()` call
against the identical, unmodified document (`posts/de/2026/2026-03-14-sicherheitskultur.md` —
translation link, image, `[figure]`, `[include]`, aliases, all exercised) produce HTML that is
byte-identical once `PreviewBanner::strip()` removes the banner; preview of the fixture's
draft and not-yet-due-scheduled posts (both of which a real build excludes entirely) succeeds
and renders their content; an unsaved body edit shows up in the rendered output while the
on-disk file is confirmed byte-unchanged after the call; an unconfigured language and an
unknown identifier both come back as `PreviewResult::invalid()` rather than throwing.
`make check`: 811 tests (up from 797), PHPStan level 8 clean across `src`, `bin`, `tests`,
`admin`, PSR-12 + escaping lint clean.

**T32 acceptance:** the admin process cannot write to `releases/` or `public` — verified by
file permissions, not by convention.

---

## M6 — Import (P3)

**Reprioritized 2026-09-09 (SPEC §15.5, §19 item 4; see T26's own status note above): M6 is
next, ahead of the remaining M5 tasks (T28-33), not after them as originally ordered.** The
live site is down and the freeze-and-serve cutover option it needed is gone with it; the
operator chose to build the WordPress import now instead, using the WXR export already in
hand, rather than accept an indefinite 404 gap or wait on a staging cutover. This changes task
*build order* only — SPEC §1.1 still says P3 ships once P1+P2 are stable, so whether the
imported content actually goes live before or after M5 (Admin) is a separate, later decision;
see SPEC's own §15.5 resolution note for the distinction spelled out in full.

T34's `Deps` is corrected from `T33` (M5's last task) to none: nothing in Appendix A's own
pipeline design needs an admin UI to run. It's a streaming CLI parser over a WXR file, the same
shape as T25's crawler — `T33` in the original table reflected M6 simply being sequenced after
M5, not a real technical dependency, and that reading no longer holds now that M6 is being
built first. T35-39's internal chain (each depending on the previous M6 task) was already
correct and needs no change.

| # | Status | Task | Deps | Spec |
|---|--------|------|------|------|
| T34 | [x] | WXR streaming parser with external entities disabled | — | §A.1 |
| T35 | [x] | HTML→Markdown converter constrained to supported constructs; unknown shortcodes preserved and reported | T34 | §A.3 |
| T36 | [ ] | Media downloader with host allow-list and checksums | T34 | §A.3 |
| T37 | [x] | Front matter emission, verbatim slugs, redirect generation for every document | T35 | §A.3 |
| T38 | [x] | Verification: count reconciliation, URL diff, word-count tolerance, migration report | T37 | §A.4 |
| T39 | [x] | Manual review tracking file and checklist workflow | T38 | §A.5 |

**T34 note:** SPEC §A.2's own pre-work checklist was run against the operator's real export
before writing any code (`~/export/blog-export.xml`, 6 items, ~60 KB — not committed, see
below) rather than guessed at:

- **Live permalink structure:** date-based (`/YYYY/MM/postname/`, WordPress's "Month and
  name" setting) for every published post; the two drafts show `?p=<id>` instead, which is
  normal — a draft has no "live" pretty permalink under any setting until it's published.
- **Gutenberg vs. Classic:** both, confirmed directly, matching §A.2's own expectation for a
  long-lived site — 5 of 6 items are Classic (no block comments), one 2018 draft is pure
  Gutenberg (`<!-- wp:paragraph -->`/`wp:heading`/`wp:list` only — no images, galleries, or
  embeds in this particular export).
- **Plugin shortcodes:** none found in any item's `content:encoded` body. `[bracket]`-shaped
  text does appear elsewhere in the export (Akismet/Jetpack comment-meta keys like
  `[akismet_history]`), but that's comment metadata, not post content, and comments are never
  imported at all (§14.4/§A.6) — WxrItem doesn't even expose them.
- **Non-German content:** all six items are in English, titles and taxonomy alike (channel
  `<language>` is `en-US`). This wasn't the assumption SPEC's own §6.4 history carried forward
  from — worth knowing before T37 decides which language tree an imported document lands in,
  since `en` is also already `default_language` (§19 item 2).
- Also noted, not part of §A.2's checklist but relevant to T35/T36 later: zero `wp-content/
  uploads` references and zero `<img>` tags anywhere in this export — T36 (media downloader)
  has nothing to fetch for this particular corpus, though it should still be built generically
  since a real host's images not making it into this specific 6-item export doesn't mean the
  Appendix A pipeline can skip media handling as a capability.

**The export file itself is intentionally not committed anywhere in this repository** — same
principle as `config/site.php`/the real legal pages (SPEC §6.4, CLAUDE.md's own "Never commit"
list): it's the operator's real content, sitting outside the repo at `~/export/
blog-export.xml`. `tests/fixtures/Import/sample.xml` is a synthetic fixture built to exercise
the same shapes (channel/authors/post/page/attachment/draft-with-null-date/sticky/categories/
postmeta) without containing anything real.

Implementation: `WxrReader` (`src/Import/`) walks the file with `XMLReader` at depth 2 (direct
children of `<channel>`), so memory stays bounded to one `<item>` at a time regardless of
export size — never one `DOMDocument` for the whole file. Each matched element's
`readOuterXML()` is re-parsed with `simplexml_load_string()` for convenient namespaced field
access (`wp:`/`content:`/`excerpt:`/`dc:`); this only works because `readOuterXML()` empirically
re-serializes every namespace declaration a subtree needs onto the element itself, confirmed
with a throwaway script against the real export before committing to the design, not assumed.
External entity loading is disabled via `LIBXML_NONET` — explicit intent, not reliance on the
fact that modern libxml2 already disables external entity *substitution* by default (verified
both ways with a crafted XXE payload; `WxrReaderTest::testExternalEntityIsNotExpanded` keeps
that verification as a regression test). A malformed item is **not** caught and skipped
independently of the rest, unlike `DocumentParser`'s per-document error collection (SPEC
§5.5) — confirmed empirically that `XMLReader::read()` itself fails at the very first
well-formedness problem in the *whole* document, however far into it, since a streaming reader
tokenizes forward from the start; there's no such thing as "one bad item, N-1 good ones" here.
An early draft had a per-item try/catch modeled on `DocumentParser`'s pattern anyway — removed
once the empirical test showed it could never actually catch anything, the same lesson T23's
`ReleaseDeployer` pruning logic already taught this project once.

Deliberately out of scope for this task, left to later M6 tasks: partitioning by
`wp:post_type`/`wp:status` (publish→published, draft→draft, etc.) and skipping revisions/nav
items/auto-drafts (§A.3's own "Pipeline" step — T35/T37's job, not this parser's); `WxrReader`
returns every item the export contains, faithfully, and decides nothing about which of them
matter.

**T35 acceptance:** the converter never emits raw HTML into a `.md` file. An unknown
shortcode appears in both the output file and the report — never dropped silently.

**T35 note:** four new classes in `src/Import/`, one per §A.3 pipeline step between "strip
block comments" and "map WP shortcodes" inclusive — `GutenbergBlockStripper`,
`WpautopNormalizer`, `WpShortcodeConverter`, and `HtmlToMarkdownConverter` (the orchestrator,
which also does the actual DOM walk). Scope is deliberately Md2Html's own supported-construct
list (headings, lists, blockquotes, fenced code, bold/italic/strikethrough/code spans, links,
images, hard breaks, `<hr>`) plus the three WP shortcode mappings SPEC §A.3 names by name
(`[caption]`→`[figure]`, `[gallery]`→ repeated `[figure]`, `[embed]`→`[embed]`) — nothing
attempts tables (Md2Html supports GFM pipe tables, but converting arbitrary `<table>` markup
into one correctly, including `colspan`/`rowspan`, is real additional scope with zero evidence
of need — see below — so a `<table>` is treated as an unsupported block like any other: text
kept, tag dropped, reported).

Validated the same way T34 was: against the operator's real export before considering this
done, not just synthetic fixtures. All six real posts convert with **zero** warnings — the
corpus genuinely is as tractable as T34's pre-work suggested (plain paragraphs/headings/lists/
links/bold, no shortcodes, no images) — and one real post's output was fed back through the
actual `Cuniform\Render\Md2Html` renderer to confirm a full round trip: the original `<a
href="...">http://www.meekro.com</a>` came back out as a semantically identical `<a>` tag
(plus Cuniform's own external-link `target="_blank" rel="noopener noreferrer"` enhancement,
a bonus, not a regression). None of this real-corpus content is committed, same as T34 — T35's
own tests use small inline HTML snippets rather than fixture files, since a converter test's
input and expected output are more readable sitting right next to each other than round-tripped
through a shared XML fixture.

Two real bugs, not just design choices, were caught by writing tests immediately after the
ad hoc real-corpus check rather than trusting the design once it looked right:

1. **Inline content sitting directly inside a block container with no `<p>` wrapper was
   mis-routed through block-level handling**, turning `<div><span>red text</span> normal</div>`
   into two separate paragraphs instead of one flowing line. This is the DOM-level version of
   the same problem `WpautopNormalizer` solves at the text level (SPEC §A.2's "wpautop
   newlines"), and needed the same fix at this layer: `convertBlockChildren()` now buffers
   consecutive non-block children (text nodes, inline elements) and flushes them as one
   implicit paragraph, rather than dispatching every child through block-level handling
   unconditionally. This also *simplified* `handleUnsupportedBlock()`, which no longer needs
   its own "does this have a block child" branch — `convertBlockChildren()` is correct for
   both cases now, uniformly.
2. **`<script>`/`<style>` content leaked into the output** the first time a script+style
   fixture was actually run, because neither tag was in the block-tag list `convertBlockChildren`
   dispatches on — they fell through to the *inline* path instead, where the generic
   "unsupported inline element" handler recurses into children and keeps their text content,
   which for `<script>`/`<style>` is code, not prose. Fixed by adding both to the block-tag
   list (so they reach the block-level drop-entirely branch) plus a matching guard in the
   inline-element handler as defence in depth, in case a future DOM shape nests one somewhere
   this class doesn't expect.

One more class involved indirectly: `WpShortcodeConverter`'s generic "unknown shortcode"
report initially flagged `[figure ...]` — its own output for a mapped `[caption]` — as
"unknown," because the exclusion list only named WordPress's own shortcode names (`caption`,
`gallery`, `embed`), not Cuniform's (`figure`, `video`, `embed`, `details`, `note`, `toc`,
`include`). By the time that check runs, a `[figure ...]`/`[embed ...]` in the text is just as
likely to be this class's *own* output as WordPress content that happened to reuse a name —
either way it's recognized, not unknown. Both exclusion lists are now kept, named for what
they actually are.

Deliberately unescaped, documented as a known, accepted gap rather than solved: bracket text
(`[`/`]`) and Markdown-special characters (`*`, `_`, `#`, backtick, ...) inside plain prose are
left exactly as extracted, because escaping brackets would corrupt `WpShortcodeConverter`'s own
shortcode output surviving the same DOM walk, and Md2Html has no backslash-escape syntax to
safely neutralize the rest even if this class tried. Not present anywhere in the real export
(confirmed, not assumed), so this is a real but currently-inert limitation — worth revisiting
if a future export actually has prose containing literal `[bracket text]` or a line starting
with a Markdown-significant character.

**T37 note:** three new pieces in `src/Import/` — `FrontMatterEmitter` (writes a front matter
block that `RestrictedYamlParser`, T4, can read back — the first thing in this codebase that
needs to *write* front matter rather than only parse it), `WxrImporter` (the orchestrator: partitions
by `wp:post_type`/`wp:status`, this task's own share of §A.3's "Pipeline" step —
BUILD-ORDER's T34 note already flagged that partitioning belonged here, not in the parser), and
`ImportedDocumentWriter`. Plus `bin/cuniform import-wxr <path-to-export.xml>
[--output-dir=<path>]`, tying `WxrReader` (T34) → `WxrImporter` → `ImportedDocumentWriter`
together.

**Posts only, this pass.** The real export has no `page` items to validate a page-import path
against (T34's own note: 6 items, all `post_type=post`) — a `page` item is recognized and
reported with a warning, never silently dropped, but not yet converted to a `PageFrontMatter`
document. `WxrImporter`'s own docblock flags this as the thing to revisit once a real export
actually has one, rather than guessing at a design nothing can confirm.

**Staged, not shipped.** `import-wxr` never writes into `content/` — output lands in
`var/import/` by default (never built, never served), because SPEC §A.5 requires every
imported document to be reviewed before it ships, and writing straight into `content/posts/`
would make a freshly imported document visible to the very next `bin/cuniform build` with
nothing in between. Promoting a reviewed document into `content/` is left as the operator's
own deliberate action; nothing here suggests a "promote" command exists yet.

**Redirects are per-document `aliases` front matter, not a written file.** `RedirectMapCompiler`
(T21) already turns a document's own `aliases` list into a compiled redirect at build time —
`WxrImporter` only has to put the item's old path there (from `wp:link`'s path component, and
only when it was a real pretty-permalink path — a `?p=123` link never had a real indexed URL,
so it gets no alias). This is deliberately the *entire* scope of "redirect generation" this
task claims: SPEC §A.3 also mentions category/tag archives, feeds, and date archives in the
same breath, but those aren't tied to any single document's front matter, Cuniform has no
"category" concept to map WordPress's onto, and BUILD-ORDER's own T37 line names "redirect
generation for every *document*" specifically. Left for a dedicated follow-up, not silently
dropped — `WxrImporter`'s own docblock says so explicitly.

**One documented judgment call**, the same kind T17 already established a pattern for
(flag it, don't silently decide): WordPress categories and tags are merged into one flat,
deduplicated `tags` list, case-insensitively — Cuniform has no separate "category" concept to
keep them apart. The real export's own categories (general, webdesign, virtual-worlds, ...)
read as broad topical tags in practice, not a rigid hierarchy, which is what makes this
reasonable rather than just convenient; worth a second look if a future export's categories are
more clearly hierarchical.

Validated the same way T34 and T35 were: all six real posts run through `WxrImporter`, and
every one of the resulting six front matter blocks parses cleanly through the actual
`Cuniform\Content\FrontMatter\FrontMatterParser` — not just this task's own emitter — with
zero errors. One real warning fires, exactly where expected: `post_id=180`'s WXR `post_name`
was empty (an unfinished draft that was never actually published under a real slug), so its
slug is derived from the title via Cuniform's own `Slugifier` instead — SPEC §5.4's verbatim
policy has nothing to take verbatim in that case, and the fallback is reported, not silent.
None of this real-corpus content is committed; `WxrImporterTest` builds small in-memory
`WxrItem`s directly rather than round-tripping through a shared XML fixture, for the same
readability reason T35's tests do.

**T38 acceptance:** the import is idempotent — running it twice against the same export
produces identical output. Any count delta between export and generated files is a hard failure.

**T38 note:** three new pieces in `src/Import/` — `ImportVerifier` (the orchestrator),
`ImportReport` (its output — a value object with one field per §A.4 report category), and
`LegacyPermalink` (a one-method class pulled out of `WxrImporter::buildAliases()` once
`ImportVerifier` needed the identical "is this a real pretty-permalink path" rule for its own
URL diff — kept single-sourced rather than duplicated, since the two checks silently drifting
apart on what counts as a real old URL would be exactly the kind of bug this task exists to
catch). `bin/cuniform import-wxr` now runs `ImportVerifier::verify()` between import and
write — a hard failure blocks the write entirely, so nothing partial ever lands in the staging
directory — and prints a migration-report summary after its existing warning lines.

**What "count reconciliation... any delta is a hard failure" turned out to mean here**, since
this pipeline has no second, external system to reconcile the WXR file against (SPEC §A.4's
own wording assumes one — a live CMS to query — that this migration doesn't have): `WxrImporter`
already accounts for every item it reads by construction (each loop iteration either appends
one document or takes an early `continue`, nothing in between), so re-deriving that accounting
a second time here would only risk a second copy of its status/slug/date rules quietly drifting
from the first. The one place a real count delta can still happen despite that construction is
two *different* items resolving to the same output file path — a slug+date collision, which
`ImportedDocumentWriter` would silently resolve by one document overwriting the other on disk.
`ImportVerifier` catches exactly this (`findDuplicateOutputPaths()`) and throws before anything
is written — the literal reading of T38's own acceptance wording, "generated *files*," not just
documents in memory. Nothing in the real six-item export triggers it; a synthetic fixture
(`tests/fixtures/Import/colliding.xml`, two items sharing a slug and calendar day) exercises it
directly, both at the `ImportVerifier` level and through the CLI (confirming the staging
directory is never even created on a hard failure, not partially populated).

**URL diff**, adapted to this migration's own §15.5 resolution: SPEC §A.4 says "URL diff between
the old sitemap and the built site," but there is no old sitemap — the live site is down (§19
item 4) — and no built site yet either (imported documents are staged, not shipped, until
manual review). The adaptation: every `post`/`page` item with a real pretty-permalink path that
did **not** get imported (a `page` item, SPEC §5.4's invalid-slug skip, `private` status, ...)
is surfaced as an unresolved legacy URL — a path that will have no redirect once review is
done, worth a human decision rather than a silent loss. An item that *was* imported already
carries its own `aliases` entry (T37) and needs no separate check here; `BuildVerifier` (T22)
independently confirms every alias actually resolves once a real build runs over `content/`,
which is a different, later check this task doesn't duplicate.

**Word-count tolerance** compares each imported item's original `content:encoded` (HTML
stripped) against its own converted Markdown body — recovered via the real
`Cuniform\Content\FrontMatter\FrontMatterParser`, the same one T37 already validated against,
rather than re-deriving front-matter-stripping logic a second time. The tolerance itself (20%)
is unspecified by SPEC ("with a tolerance," no number given) — a documented judgment call, the
same kind T17/T37 already established a pattern for: wide enough to absorb ordinary conversion
noise (a caption folded into alt text, a dropped decorative `<span>`), tight enough to still
catch a post that lost most of its content in conversion. `footnotes` and `failedMediaDownloads`
are real fields on `ImportReport`, always empty in this implementation — not omitted, since
SPEC §A.4 names both explicitly: no footnote-specific detection is built (a footnote plugin's
shortcode would still surface generically under `unknownShortcodes`), and T36 (media
downloader) isn't built yet, so there's nothing to attempt a download with, let alone fail one.

Validated against the operator's real export, the same way T34/T35/T37 were: `ImportVerifier`
completes with zero hard failures against the real six-item corpus (source IDs 10, 11, 15, 35,
66, 180 — no output-path collisions). `countsByStatus` shows 4 `publish` + 2 `draft`, matching
T34's own pre-work note. Every §A.4-named bucket is empty except `otherNotices`, which holds
exactly one entry — `post_id=180`'s already-known empty-`post_name` fallback warning (T37) —
confirming this task surfaces nothing new the prior tasks hadn't already found and explained.
None of this real-corpus content is committed; `ImportVerifierTest` builds small in-memory
`WxrItem`s directly, matching T35/T37's own test style, including its own collision case.
`tests/fixtures/Import/colliding.xml` is a separate, small synthetic fixture used only at the
CLI level (`ApplicationTest`) — confirming the *end-to-end* command, not just `ImportVerifier`
in isolation, refuses to stage anything when two real WXR items collide.

**Idempotency** (T38's other named acceptance criterion) needed no new code — nothing in
`src/Import/` reads the clock, generates a random value, or carries any other hidden state
between calls, confirmed by inspection (`grep` for `time()`/`uniqid`/`rand`/`random_` across
`src/Import/` turns up nothing) rather than assumed. `ImportVerifierTest`'s own
`testRunningTheImportTwiceOnTheSameExportProducesByteIdenticalDocuments` runs `WxrImporter`
twice against the same in-memory export and asserts every resulting document's path, contents,
and source ID are identical byte-for-byte, plus the warnings list itself.

**T39 note:** four new pieces in `src/Import/` — `ReviewDecision` (an enum: `pending`, `keep`,
`reject`), `ReviewEntry` (one document's review state, immutable — `withDecision()` returns a
new instance rather than mutating in place, matching this codebase's readonly-value-object
convention), `ReviewChecklistBuilder` (pure: builds fresh entries from an import run, merges a
prior tracking file's decisions onto them, and orders entries for display), and
`ReviewChecklistStore` (the only I/O — reads and writes the tracking file as JSON, mirroring the
existing `WxrImporter`/`ImportedDocumentWriter` computation-vs-persistence split from T37). Two
new CLI commands, `review-status [--file=<path>]` and `review-mark <source-id>
<pending|keep|reject> [--file=<path>]`, plus `import-wxr` itself now builds/merges/saves the
tracking file (`var/import-review.json` by default, a new `--review-file=<path>` override) as
part of every run.

**"A checkbox per source_id" became one `decision` field, not a bare boolean.** SPEC §A.5's own
fifth review point — "content genuinely worth keeping... the cheapest moment to delete posts
that have not aged well" — is itself a decision with two outcomes, not just a yes/no on whether
someone looked at the document. `Pending` doubles as "not yet reviewed," so there's no second,
separate reviewed flag that could disagree with the decision.

**"An interrupted review can resume rather than restart"** is what governs
`ReviewChecklistBuilder::merge()`: a prior run's recorded decision for a given source_id is
carried forward onto a fresh build, matched by source_id, every time `import-wxr` runs again —
which matters because re-running the import (say, after fixing an unknown shortcode) is a normal
part of getting T38's migration report clean, and it must never silently discard review work
already done in the meantime. Two things are handled deliberately, not left implicit: a
source_id no longer present in the fresh build (an item that stopped importing) is dropped —
nothing is left to review; and flags are always taken from the fresh build, never carried over
from the stale one, since the whole point of a re-run is often to see whether a fix actually
cleared a flag.

**Ordering is a read-time concern, not a stored one.** The tracking file itself stays keyed by
source_id, order-independent; SPEC §A.4's "documents with flags first, clean ones batched" is
applied by `ReviewChecklistBuilder::sortedForReview()` only when `review-status` displays the
list, so the file's own layout never has to be re-derived from an order that would otherwise
grow stale as decisions accumulate across sessions.

**`review-status` prints SPEC §A.5's own five-point checklist as a header**, once, before
listing pending documents — the literal text of "it needs a checklist rather than a skim" is
what makes this a checklist workflow rather than a bare data dump. Only pending documents are
listed individually (with source_id, relative path, title, and flag count/detail); already-
decided ones are summarized as a single count, since there's nothing left to act on for them.

Validated against the operator's real export end-to-end through the actual `bin/cuniform`
CLI (not just the underlying classes): 6 documents staged, 1 flagged (the already-known
empty-slug fallback from T37/T38), all 6 starting `pending`. Marked one `keep` and one `reject`
via `review-mark`; `review-status` immediately reflected 4 pending / 2 decided, with the marked
two correctly absent from the pending list. Re-ran `import-wxr` a second time — both decisions
were still in place afterward, confirming the merge behavior holds through a real CLI round
trip, not just in an in-memory test. None of this real-corpus content is committed;
`ReviewChecklistBuilderTest`/`ReviewChecklistStoreTest` use small in-memory `WxrItem`s and a
temp-directory JSON file respectively, matching this milestone's established test style.

**Deliberately out of scope, and said so in code rather than silently absent:** marking a
document `reject` only records the decision — it does not delete the staged file from
`var/import/`, and marking `keep` does not copy it into `content/`. Both remain a manual action
the operator takes themselves, the same boundary T37's own CLI output already states ("copy the
ones you keep into content/posts/..."). T39's job is tracking the decision, not acting on it —
adding either action later is a small, separate change, not a redesign.

---

## Standing rules

- A task that turns out to need a dependency, a framework, or a database is a task that needs
  a conversation first. Stop and say so.
- If the spec is silent or ambiguous, ask. Do not decide in code and document it in a comment.
- Tests ship with the code. A task with passing acceptance criteria but no tests is not done.
