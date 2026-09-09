---
name: cuniform-verify
description: Run the full Cuniform quality gate — PHPStan level 8, PHPUnit, PSR-12, the escaping lint, and the renderer-fork changelog check — and report failures without fixing them yet.
disable-model-invocation: true
---

Run the quality gate and report. Do not fix anything until the full report exists — fixing
mid-run hides how many things are actually broken.

1. `make stan` — PHPStan level 8. New baseline entries are not an acceptable fix.
2. `make test` — PHPUnit.
3. `make lint` — PSR-12 plus the escaping lint (any `<?=` in `templates/` not routed through
   `e()`, `eAttr()`, `eUrl()`, or `eJs()`, excluding the single permitted `bodyHtml` sink).
4. If `src/Render/Md2Html.php` changed, confirm `src/Render/CHANGELOG-FORK.md` was updated
   alongside it (starting commit plus one entry per change). This is an owned fork, not a
   vendored copy (SPEC §4) — there is no hash to verify, but an undocumented change to it is
   still a finding: flag it the same way you would a missing test.
5. Report a numbered list of failures, each with file, line, and the rule or spec section it
   violates. Then ask which to address first.
