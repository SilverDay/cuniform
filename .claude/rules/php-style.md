---
paths:
  - "src/**/*.php"
  - "admin/**/*.php"
  - "bin/*"
  - "tests/**/*.php"
---

# PHP conventions

- `declare(strict_types=1);` immediately after `<?php`, before the namespace.
- PSR-12. Four spaces. One class per file. Filename matches class name.
- Namespace `Cuniform\`, PSR-4 rooted at `src/`. Sub-namespaces mirror directories.
- Type every parameter, return, and property. `mixed` requires a comment explaining why.
- `readonly` value objects for anything crossing a layer boundary. No setters on them.
- No `static` mutable state, no singletons, no service locator. Constructor injection.
- No suppression operator (`@`). No `extract()`, `eval()`, variable variables, or variable
  function calls.
- Exceptions: one base `Cuniform\CuniformException`; a subclass per failure category
  (`ConfigException`, `ContentException`, `RenderException`, `BuildException`).
  Never throw or catch bare `\Exception`.
- Build-time failures collect into a result object and are reported together (SPEC §5.5,
  §10.3) — throwing on the first bad document is a bug, not a style choice.
- Error messages name the file and, where it exists, the line. A validation error the author
  cannot locate is half an error.

## Testing

- PHPUnit; tests mirror `src/` under `tests/`.
- Every parser, slugifier, shortcode, route, and translation-grouping behaviour gets a test,
  including failure cases: the spec's build-blocking conditions are test cases, not prose.
- No network, no filesystem outside a per-test temp directory, no reliance on the global CWD.
