# Cuniform — Claude Code package

Implementation package for **Cuniform**, a database-less Markdown blog and page engine
(PHP 8.3, Apache, no framework, no runtime dependencies).

This package contains **no implementation code by design.** It contains the specification,
the task order, the standards, and the quality gates. Skeleton code would have to be
reconciled against the spec anyway, and half-written scaffolding is harder to correct than
an empty directory.

## What is here

| Path | Purpose |
|------|---------|
| `CLAUDE.md` | Loaded every session: non-negotiables, layout, commands, conventions |
| `docs/SPEC.md` | The specification — authoritative for all behaviour |
| `docs/BUILD-ORDER.md` | 39 tasks across 6 milestones, with dependencies and acceptance criteria |
| `.claude/rules/` | Path-scoped rules; load only when Claude touches matching files |
| `.claude/skills/` | `/cuniform-task`, `/cuniform-verify`, `/cuniform-spec-check` |
| `.claude/settings.json` | Pre-approved commands |
| `Makefile`, `phpstan.neon.dist`, `phpunit.xml.dist`, `composer.json` | Quality gate |
| `config/site.example.php` | Annotated configuration template |

Rules are path-scoped so they cost no context until they are relevant: the security rules load
when Claude opens something in `src/`, the template rules when it opens `templates/`.

## Getting started

```bash
cp -r cuniform/ ~/projects/cuniform && cd ~/projects/cuniform
git init && git add -A && git commit -m "Cuniform: spec, standards, build order"
composer install          # dev tooling only — nothing here ships
claude
```

Then in the session:

```
/cuniform-task              # starts T1, or pass a task id: /cuniform-task T5
/cuniform-verify            # full quality gate, reported before anything is fixed
/cuniform-spec-check T12    # audit an implemented component against the spec
```

## The one rule worth repeating

`src/Render/Md2Html.php` is owned code, edited directly like anything else in `src/` — but
every change, bug fix or feature, is logged in `src/Render/CHANGELOG-FORK.md` along with the
upstream commit the fork started from. Skip that logging and this silently becomes an
unmaintained, undocumented fork — the exact failure mode the old vendoring rule existed to
prevent, just moved one file over.
