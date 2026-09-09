# Session Handover — 2026-09-09

Point-in-time snapshot for picking this work back up, written at the end of a session that
implemented T1–T16, T18, and a deliberately partial T17. `docs/SPEC.md` and
`docs/BUILD-ORDER.md` are still the authoritative sources — this file explains the *why*
behind decisions those documents don't fully capture, and what to do next. Safe to delete
once it goes stale (the BUILD-ORDER checkboxes and acceptance-criteria notes are the durable
record; this is scaffolding for the handoff itself).

## Current state

- Repo: `https://github.com/SilverDay/cuniform`, branch `main`, pushed through commit
  `e59f1ff`.
- `make check` is green: 305 tests, 558 assertions, PHPStan level 8, PSR-12 + escaping lint.
- BUILD-ORDER.md status: T1–T16 and T18 marked `[x]`. T17 is `[ ]` with a "T17 progress" note
  (search for it) explaining exactly what's built vs. deferred — read that note before
  touching templates again.
- `config/site.php` exists locally (real values, gitignored) — `default_language: en`,
  bilingual `de`+`en`, `url_prefix: always`.

## Environment notes (not part of the repo)

- This machine had neither Composer nor the GitHub CLI installed at session start. Both are
  now at `~/.local/bin/{composer,gh}` (already on `PATH` via `~/.bashrc`). If a fresh
  environment doesn't have them, same fix: official installer scripts, no sudo needed.
- `.claude/settings.json`'s `git push` deny rule was relaxed to the default ask-for-
  confirmation behavior partway through the session (the user asked for this explicitly) — it
  was a hard deny before.
- `git config --global --add safe.directory /srv/vhosts/blog.silverday.de` was needed once
  (the vhost root is owned by a different system user than the one running these sessions);
  already applied, shouldn't need repeating.

## Decisions made this session that a future reader should know about

These aren't in SPEC.md's prose (or are recent amendments to it) — check `docs/SPEC.md`
directly for the current wording, but the reasoning is here:

1. **`default_language` is `en`, not `de`** (SPEC §7.1 example, Appendix B #8, Open Items #2).
   Root redirects to `/en/`, `x-default` hreflang points at `/en/`. §6.4's "legal pages
   follow `default_language`" generalization (see #2) means the *authoritative* Impressum is
   now the English one — the German-law basis for requiring one at all is unaffected, since
   that follows the operator's jurisdiction, not the UI language.
2. **The renderer is an owned fork, not a vendored copy** (SPEC §4, Appendix B #6). Forked
   from `SilverDay/md2html-php@f1e0162`, which already contained the bug fixes that prompted
   forking in the first place. `src/Render/CHANGELOG-FORK.md` is the provenance record — keep
   it current on every change to `src/Render/Md2Html.php`, there's no hash check enforcing
   this anymore.
3. **Legal-page authority follows `default_language` generically**, not a hardcoded language
   (SPEC §6.4). Reserved slugs for legal pages are derived from the `legal:` front matter
   marker at build time, not a fixed word list — a route collision on that slug is caught by
   ordinary uniqueness checking, no separate mechanism needed. Real legal-page content is
   gitignored; only `*.example.md` placeholders are committed.
4. **Number formatting was deliberately not built** (T15, SPEC §7.9). The section is titled
   "Dates and numbers" but the prose only ever discusses dates. Don't invent a scheme if this
   comes up again — ask what the concrete need is first.
5. **`RenderAdapter` takes an injected `Md2Html` instance**, not one it builds itself (a
   change from how T7 originally shipped it). This exists so the same instance can be shared
   with the `[toc]` shortcode handler, which needs `getHeadings()` to reflect the *current*
   document — only true if adapter and handler share one renderer. `Md2Html::isHeadless()`
   defensively asserts the invariant that used to be enforced by construction.
6. **Template variable naming: `$doc`, not `$view`.** SPEC §9 literally writes
   `$doc->bodyHtml`, and `tools/escaping-lint.php` (pre-existing, from T1) hardcodes that
   exact expression as its one sink exception. Templates bind the ViewModel as `$doc`. The
   lint's sink allow-list was extended to two more already-safe-HTML cases beyond that one:
   `$layout->content` (another template's own already-escaped output) and a heading's `text`
   entry (already HTML-escaped by the renderer's `getHeadings()`).
7. **Two SPEC-implicit nav rules resolved as judgment calls** (T18, §6.2): a page needs
   `nav_order` set to appear in a nav tree at all (reading "absent = reachable but not in
   nav" literally); `nav_order` without `nav_group` defaults to `primary`.
8. **`[include]`'s page lookup depends on an interface** (`IncludedPageRepository`), not a
   concrete implementation — resolving a slug to a real page needs the whole-corpus content
   index that doesn't exist until T19. The handler's own logic (depth cap, cycle detection,
   cross-language check) is complete and tested against a fake repository now.

## What's deferred and why (all the same shape: needs a corpus that doesn't exist yet)

T10 (language resolver), T11 (translation grouping), T12 (route table), T14 (hreflang) are
all built as **pure, testable components operating on caller-supplied data** — none of them
scan the actual `content/` tree. That scanning is T19's Discover/Resolve stages. When T19
lands, it wires these together; nothing about their own design should need to change, but
worth re-reading each one's class docblock for the exact contract expected.

T17's remaining templates (`index.php`, `tag.php`, `series.php`, `archive.php`, `search.php`,
`404.php`, `feed.xml.php`, plus the `post-card`/`pagination` partials) all need aggregated
data — post listings, pagination state, tag/series indexes, a search index — that only T19
(and T20 for the search index specifically) produce. Building their ViewModels now would mean
guessing shapes with nothing to verify them against.

`nav-primary.php`/`nav-footer.php` render whatever `NavItem` list they're handed correctly,
but nothing populates that list from real content yet (`NavTreeBuilder` exists and is fully
tested, just not wired to a real page corpus). `lang-switcher.php` doesn't yet render a
disabled/home-link state for a language with no translation (SPEC §7.7) — flagged in
BUILD-ORDER, worth fixing whenever nav wiring happens for real.

## Recommended next step

**T19** (build pipeline stages 1–6) is the natural next task — it's what turns T10–T14's
pure components and T17's partial template set into an actual site, and unblocks finishing
T17 for real rather than against synthetic fixtures. Read `docs/SPEC.md` §10.1 and §10.2
before starting; the incremental-build cache key and invalidation rules (§10.2) have
implications for how the Discover/Parse stages should be structured from the start, not
something to bolt on after.

Two things worth deciding with the user before or during T19, since they're genuine design
choices rather than something inferable from SPEC:
- How `config/lang/{de,en}.php` get populated for real (T13's catalogue is built and tested
  against synthetic fixtures; `templates/post.php` already references a `updated_on` key that
  doesn't exist in any real file yet).
- Whether to keep building strictly bottom-up per BUILD-ORDER's numbering, or follow
  dependencies more loosely the way this session did with T17/T18 (the user chose "partial
  T17, then T18" when asked — same kind of call will come up again once T19 reveals what T17
  actually needs).
