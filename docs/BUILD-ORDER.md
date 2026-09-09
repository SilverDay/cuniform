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
| T34 | [ ] | WXR streaming parser with external entities disabled | — | §A.1 |
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
