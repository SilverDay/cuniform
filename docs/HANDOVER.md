# Session Handover — 2026-09-09 (T34)

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T34 (WXR streaming parser) — the first M6 task, now prioritized ahead of M5 (see
the previous session's roadmap-pivot handover, superseded by this one but worth reading for
the reasoning). `docs/SPEC.md` and `docs/BUILD-ORDER.md` remain authoritative — this file
explains the *why* behind decisions those documents don't fully capture, and what to do next.
Safe to delete once it goes stale.

## Current state

- `make check` is green: 528 tests, 1075 assertions, PHPStan level 8, PSR-12 + escaping lint.
- BUILD-ORDER.md: M1-M3 all `[x]`. M4: T25 `[x]`, T26 `[n/a]`, T27 deliverables done (checkbox
  open pending live verification). **M6: T34 `[x]`.** M5 (T28-33) untouched, deliberately —
  M6 is being built first.
- New namespace: `src/Import/` — `WxrReader`, `WxrDocument`, `WxrChannel`, `WxrItem`,
  `WxrAuthor`, `WxrCategory`, `ImportException`. No CLI command yet — a parser alone produces
  only an in-memory `WxrDocument`, nothing a CLI command would do anything useful with until
  T35 (HTML→Markdown) and T37 (front matter emission) exist. Wiring a command is a later
  task's job, not this one's.
- The operator's real WXR export lives at `~/export/blog-export.xml` (outside the repo,
  never committed — see below). It was read directly this session to do SPEC §A.2's pre-work
  and to sanity-check `WxrReader` against real data, both ad hoc, not as a committed test.

## Decisions made this session that a future reader should know about

1. **SPEC §A.2's pre-work was done against the real export before writing any parsing code,**
   not assumed or deferred. Findings, all now recorded in BUILD-ORDER's T34 note: permalink
   structure is date-based (`/YYYY/MM/postname/`) for published posts, `?p=<id>` for drafts
   (normal — a draft has no live pretty permalink under any setting); the site has both
   Classic and Gutenberg content (5 Classic items, one pure-Gutenberg 2018 draft using only
   paragraph/heading/list blocks); zero shortcodes anywhere; **all six items are in English**,
   which wasn't the assumption baked into SPEC's own §6.4 history and is worth knowing before
   T37 decides which language tree an imported document lands in (`en` is already
   `default_language`, so this may mean it's simpler than expected — nothing decided this
   session, just flagged). Also noted: zero media references in this export at all, so T36
   (media downloader) has nothing to actually fetch for this specific corpus, though it still
   needs to exist as a general capability.
2. **The real export file is not committed anywhere, on purpose** — same principle as
   `config/site.php` and the real legal pages (SPEC §6.4's "never commit" list, which this
   isn't literally part of, but the reasoning transfers directly: it's the operator's real
   content). `tests/fixtures/Import/sample.xml` is a synthetic fixture built to exercise the
   same shapes without containing anything real. If a future session needs the real export
   again, it's still at `~/export/blog-export.xml` — ask the operator again only if that's
   changed.
3. **A malformed WXR item is not caught and skipped independently — a parse failure always
   aborts the whole read.** This diverges from `DocumentParser`'s per-document error
   collection (SPEC §5.5), and an early draft of `WxrReader` actually copied that pattern
   (try/catch around each item, collecting a list of errors) before empirical testing showed
   it could never fire: `XMLReader::read()` itself fails at the very first well-formedness
   problem in the *whole* document, however far into it, since a streaming reader tokenizes
   forward from the start — there's no such thing as "item 5 of 20 is bad, continue with the
   rest" the way there is for 20 independent front-matter files. Same lesson T23's
   `ReleaseDeployer` pruning logic already taught this project once: write the test for a
   scenario before trusting the design, not after.
4. **External entity loading is hardened explicitly (`LIBXML_NONET`), even though empirical
   testing showed modern libxml2 already disables external entity *substitution* by default**
   on this host regardless. Verified both configurations against a crafted XXE payload before
   deciding — the explicit flag is intent made durable, not redundant defense: relying on a
   platform default that could differ across PHP/libxml builds is exactly the kind of implicit
   assumption this project's conventions (RestrictedYamlParser, FilesystemGateway's realpath
   containment) consistently avoid elsewhere. Kept as a regression test
   (`WxrReaderTest::testExternalEntityIsNotExpanded`), not just a one-time manual check.
5. **`readOuterXML()` re-serializing every namespace declaration a subtree needs onto the
   element itself was verified empirically with a throwaway script against the real export,**
   not assumed from documentation — this is what makes parsing a `<item>` fragment standalone
   with `simplexml_load_string()` safe, even though its `wp:`/`content:`/`excerpt:`/`dc:`
   prefixes are declared on the document's root `<rss>`, nowhere near the item itself.

## What's still deferred and why

- T35 (HTML→Markdown conversion), T36 (media downloader — nothing to fetch for this export,
  but still needs building generically), T37 (front matter emission, redirect generation),
  T38 (verification), T39 (manual review tracking) — none of M6 beyond T34 is built yet.
- Partitioning by `wp:post_type`/`wp:status` (publish→published, draft→draft, skip revisions/
  nav items/auto-drafts — SPEC §A.3's "Pipeline" step) is deliberately not in `WxrReader` at
  all. It returns every item the export contains, faithfully, and decides nothing about which
  of them matter for import — that's T35/T37's job.
- No CLI command yet for the importer (see "Current state" above) — nothing to usefully wire
  up until conversion and front-matter emission exist too.

## Recommended next step

**T35** (HTML→Markdown converter constrained to supported constructs; unknown shortcodes
preserved and reported) is next in M6's now-prioritized sequence. Given this session's pre-work
findings, the real corpus this needs to handle is small and tractable: `<p>`, headings, lists,
links, and basic inline formatting from 5 Classic posts, plus one Gutenberg draft using only
paragraph/heading/list blocks (no images, galleries, or embeds to worry about in this
particular export) — worth reading `content:encoded` from a couple of real items directly
(`~/export/blog-export.xml`, still there) before assuming the general Appendix A.3 HTML→
Markdown mapping table covers everything this specific site's markup actually contains.
