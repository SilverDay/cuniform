---
paths:
  - "templates/**"
---

# Template rules

- Plain PHP. No Twig, no compilation, no logic beyond presentation branching.
- A template receives one immutable ViewModel. No superglobals, no `file_get_contents`,
  no direct config access.
- Every echo goes through a helper: `<?= e($x) ?>`, `eAttr()`, `eUrl()`, `eJs()`.
  A bare `<?= $x ?>` fails the escaping lint and must not be committed.
- `$doc->bodyHtml` is the single permitted unescaped output.
- One template set serves all languages. Chrome strings come from `t()`; never hard-code
  German or English text in a template, even for a single-language site (SPEC §7.8).
- Multilingual partials degrade to nothing: with one configured language the switcher and
  hreflang partials emit no markup at all, not an empty container (SPEC §7.5, §7.7, NFR-9).
- Any error thrown in a template aborts the build. Never emit a partial page.
