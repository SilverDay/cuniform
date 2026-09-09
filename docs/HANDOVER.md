# Session Handover — 2026-09-09 (roadmap pivot: M6 pulled forward)

Point-in-time snapshot for picking this work back up, written after a session that did no
engine code — it recorded a project decision that reshapes what comes next. `docs/SPEC.md`
and `docs/BUILD-ORDER.md` remain authoritative — this file explains the *why* behind decisions
those documents don't fully capture, and what to do next. Safe to delete once it goes stale.

## What happened this session

The operator (SilverDay) reported that `blog.silverday.de`'s live site has been taken down,
and that they hold a WordPress XML export (WXR) instead. This was the actual cause of T26
(legacy snapshot support) being blocked — not, as the previous session's handover framed it,
an undecided preference among SPEC §15.5's three cutover options. With no live site left,
option 1 (freeze-and-serve, which needs `wget --mirror` against something still serving) is
gone outright, not just undesirable.

Asked which of the remaining paths to take, the operator chose a fourth option SPEC §15.5
didn't originally list: **pull the P3 WordPress import (Appendix A, BUILD-ORDER's M6) forward,
ahead of M5 (Admin)**, and build it next, rather than accept the gap or wait on a staging
cutover. Recorded in both documents:

- `docs/SPEC.md`: §15.5 has a resolution note (freeze-and-serve struck through as no longer
  available, the chosen path explained, and an explicit statement of what does *not* change —
  §1.1's phase-shipping criterion, the P1 forward-compat hooks). §19 items 3 and 4 are marked
  resolved (it's WordPress; the cutover option is decided); item 5 is marked superseded rather
  than separately resolved. Appendix B's decision-log entry #4 has a matching revision note.
- `docs/BUILD-ORDER.md`: T26's row is marked `[n/a]` (a new status this file hasn't used
  before — "will not be built," distinct from `[ ]`/"not yet built" — with a status note
  explaining why, placed where T27's own status note already lives). M6's section gained a
  reprioritization note explaining the new build order and correcting T34's `Deps` column
  (`T33` → none — see below).

## Decisions made this session that a future reader should know about

1. **This was a decision only the operator could make, so it was asked rather than guessed.**
   The previous session's handover had already framed T26 as blocked on *a* decision; this
   session's first move was confirming what the decision actually was now that the underlying
   facts had changed, via `AskUserQuestion`, not picking one of §15.5's original three options
   unilaterally. The chosen answer wasn't even one of the three offered verbatim — "accelerate
   the import" was added as a fourth, clearly-labelled option specifically because the other
   three no longer fit the new facts well.
2. **T34's `Deps` column changed from `T33` to none, and this needed its own explanation,
   not just a silent edit.** The original BUILD-ORDER had M6's first task depending on M5's
   *last* task. Re-reading Appendix A's own design (a streaming `XMLReader` CLI pipeline, no
   admin UI anywhere in §A.1-§A.5) against how T25 was actually built (a CLI tool, `Cuniform\
   Cutover\`, no admin dependency) made clear that `T33` reflected M6 simply being sequenced
   after M5 in the original phase order, not a genuine technical requirement. Worth another
   look if a later session picking up T34 finds a real reason T33 (or any other M5 task) is
   actually needed — this session's read is that there isn't one, not a certainty beyond doubt.
3. **SPEC §1.1's phase-shipping criterion ("P3 ships when P1+P2 stable") was deliberately left
   unchanged**, even though task *build order* now puts M6 ahead of M5. This distinction is
   spelled out explicitly in three places (SPEC §15.5's resolution note, SPEC's Appendix B
   entry #4, and BUILD-ORDER's M6 reprioritization note) specifically so it doesn't get lost —
   building the importer next and *shipping* imported content to production are different
   decisions, and only the first one was made this session.
4. **T25's crawler (`LegacyUrlCrawler`, `bin/cuniform legacy-urls`) is not wasted work** despite
   having nothing left to crawl for this site — it's generic, reusable tooling, and the session
   said so explicitly in both SPEC and BUILD-ORDER rather than leaving its now-odd relationship
   to "the" cutover unexplained. This site's actual URL source going forward is each WXR
   `<item><link>`, read directly by the importer once it exists (T37 specifically —
   "redirect generation for every document").
5. **A new project memory was saved** (`cutover_site_down_wxr_export.md`, type: project) —
   the site-down/WXR-export fact and its consequences, since it's exactly the kind of
   non-derivable project context future sessions need and can't recover from the code alone.
   Worth updating or removing once the import actually ships and this stops being live context.

## What's still deferred and why

- The WXR export file's own location wasn't stated this session — needed before T34 can
  actually start; ask if it isn't already obvious from the repo or a fresh message.
- Everything M6 itself needs (T34-39) — none of it is built yet. This session was scope
  clarification, not implementation.
- The §1.1 phase-shipping question this session deliberately left alone: whether the finished
  import goes live before or after M5 (Admin) exists. Worth revisiting once M6 is closer to
  done, not now.

## Recommended next step

**T34** (WXR streaming parser with external entities disabled, SPEC §A.1) is next per the
reprioritization above — no BUILD-ORDER dependency left unmet. Before starting, SPEC §A.2's
own pre-work list is worth doing first, now that a real export exists to check it against:
confirm the live permalink structure (needed to generate correct redirects, §8.1), whether
content is Gutenberg or Classic (a long-lived site likely has both), which plugin shortcodes
appear (every unrecognized one is a data-loss risk per §A.3), and whether any content is
already non-German (decides whether every imported document lands in `de/` or not) — the
export itself answers all four, and getting them wrong is exactly the kind of thing that's
cheap to check now and expensive to discover mid-import.
