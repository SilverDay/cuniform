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
| T5 | [ ] | Slugifier: UTF-8, German transliteration, collision suffixes, verbatim passthrough | T1 | §5.4 |
| T6 | [x] | Renderer fork: import `Md2Html.php` into `src/Render/`, namespace it, write `CHANGELOG-FORK.md`, port the known bug fixes and the fork features of §4.3 | T1 | §4.1, §4.2, §4.3 |
| T7 | [ ] | Render adapter: front matter strip, guards | T4, T5, T6 | §4.6 |
| T8 | [ ] | Shortcode layer: code masking, placeholder tokens, pre/post passes, handler interface | T7 | §4.5 |
| T9 | [ ] | Shortcode handlers: figure, video, embed, details, note, toc, include | T8 | §4.5, §6.5 |

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
| T10 | [ ] | Language resolver: derive language from tree, validate against config, reject unknown dirs | T2, T4 | §7.2 |
| T11 | [ ] | Translation grouping by `translation_key`, duplicate detection | T10 | §7.3 |
| T12 | [ ] | Route builder: `{L}` substitution for all three `url_prefix` modes, reserved slugs, namespace collision detection | T2, T5, T10 | §7.4, §8.1, §8.2 |
| T13 | [ ] | UI string catalogue and `t()`, missing-key detection | T2 | §7.8 |
| T14 | [ ] | hreflang set construction, `x-default`, self-reference, published-only filtering | T11, T12 | §7.5 |
| T15 | [ ] | Date/number formatting via `IntlDateFormatter` with documented fallback | T13 | §7.9 |

**T12 acceptance:** the same corpus builds correctly under `always`, `auto`, and `never`.
Under `never` with two configured languages, config validation fails. A post claiming `/en/`,
`/tag/`, `/admin`, or a legal-page slug is a build error.

**T13 acceptance:** a missing key for a configured language fails the build. It does not fall
back to the default language.

**T14 acceptance:** a single-language site emits no `alternate` links, no `x-default`, and no
`og:locale:alternate`. An asymmetric hreflang set fails the build.

---

## M3 — Templates and build

| # | Status | Task | Deps | Spec |
|---|--------|------|------|------|
| T16 | [ ] | ViewModel objects and escaping helpers `e`/`eAttr`/`eUrl`/`eJs`; escaping lint in `make lint` | T7 | §9 |
| T17 | [ ] | Template set: layout, post, page, index, tag, series, archive, search, 404, feed | T14, T16 | §9 |
| T18 | [ ] | Page hierarchy and nav trees per language, `nav_*` handling, three-level cap | T12 | §6.2, §6.3 |
| T19 | [ ] | Build pipeline stages 1–6: lock, discover, parse, resolve, render, template | T17, T18 | §10.1 |
| T20 | [ ] | Artifacts: per-language feeds, sitemap with alternates, search index with threshold warning, robots.txt, security.txt, asset fingerprinting | T19 | §11 |
| T21 | [ ] | Redirect map compilation and the hand-written feed redirect entry | T12 | §7.11, §8.3 |
| T22 | [ ] | Build verification (§10.3), including the URL-scheme-change guard | T20, T21 | §10.3 |
| T23 | [ ] | Atomic deploy, release pruning, `--rollback` | T22 | §10.4 |
| T24 | [ ] | Incremental build cache and invalidation, including translation-group invalidation | T19 | §10.2 |

**T19 acceptance:** a second concurrent build is rejected by `flock`, not queued. A template
error aborts the build with no output written.

**T22 acceptance:** each verification condition in §10.3 has a test that makes it fire.
A redirect pointing at a non-existent path blocks the deploy. Changing `url_prefix` without
`--allow-url-scheme-change` blocks the deploy and prints the vhost directives that must change.

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
