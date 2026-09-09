# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# Cuniform — project instructions

Cuniform is a database-less, optionally multilingual blog and page engine in PHP 8.3.
Markdown files on disk are rendered to static HTML by a build step. No framework,
no runtime dependencies, no database.

**The specification is authoritative: @docs/SPEC.md**
Task order and acceptance criteria: @docs/BUILD-ORDER.md

## Current state

This is a spec-only scaffold: `src/`, `bin/`, `templates/`, `admin/`, and `tests/` do not
exist yet. Only `docs/`, `config/site.example.php`, the quality-gate config (`Makefile`,
`phpstan.neon.dist`, `phpunit.xml.dist`, `tools/*-lint.php`), and the `.claude/` setup below
are checked in. Start at T1 in `docs/BUILD-ORDER.md` and proceed one task at a time — see
Working style.

## Non-negotiables

- **No runtime dependencies.** Composer is dev-only (PHPUnit, PHPStan). `vendor/` is never deployed.
- **No PHP in the public request path.** The build writes static files; only `admin/` runs at request time.
- **`src/Render/Md2Html.php` is an owned fork, edited directly** (revised 2026-09-09 — see SPEC
  §4, Appendix B #6; it was a byte-identical vendored copy through spec v0.8). Every change
  — bug fix or feature — is logged in `src/Render/CHANGELOG-FORK.md`, which records the
  upstream commit it started from. Cuniform-specific wiring (front matter handling, shortcode
  placeholders) still stays in the adapter, not the renderer itself — that line didn't move.
- **The renderer escapes all text and does not accept raw HTML.** That is intended. Do not add
  passthrough. Controlled HTML comes from shortcode handlers only.
- **Every build is verified before deploy** (SPEC §10.3). A failing check means no swap.
- Never commit anything under `content/media/`, `releases/`, `var/`, or `public`. Same for the
  real legal pages (`content/pages/*/impressum.md`, `.../privacy.md`) — they carry the
  operator's real name/address/contact details; only the `*.example.md` templates are
  committed (SPEC §6.4).

## Spelling

The project is **Cuniform**, one `e`. Never write `Cuneiform` — that is a different project
(a BSD-licensed OCR system) and a stray `e` makes the divergence look accidental. This applies
to code, comments, commit messages, docs, and the site itself (SPEC §16).

## Commands

```bash
make test          # PHPUnit
make stan          # PHPStan level 8
make lint          # PSR-12 check + the escaping lint (see below)
make check         # all three — run before every commit
php bin/cuniform build [--full] [--dry-run]
php bin/cuniform build --rollback
```

## Layout

```
src/            engine (PSR-4 `Cuniform\`), hand-rolled autoloader, no Composer autoload at runtime
src/Render/     render adapter + the owned Md2Html fork (CHANGELOG-FORK.md tracks provenance)
templates/      plain-PHP templates
config/         site.php, lang/<code>.php
content/        posts/<lang>/…, pages/<lang>/…, media/, redirects.map
admin/          P2 web app (own docroot via Alias)
bin/cuniform       CLI entry point
tests/          PHPUnit
```

## Claude Code tooling already in this repo

- `.claude/settings.json` pre-approves `make check/test/stan/lint`, `composer install`,
  `git status/diff/log`, and `php bin/cuniform build --dry-run`. It denies reading
  `config/site.php` or `var/**`, `git push`, and `rm -rf`. (It still has a stale deny rule
  for editing `src/Vendor/**`, left over from before the renderer became an owned fork at
  `src/Render/Md2Html.php` — harmless since that path no longer exists, but worth deleting
  next time the file is touched; editing `.claude/settings.json` itself needs the user.)
- `.claude/rules/{content,php-style,security,templates}.md` are path-scoped and load
  automatically when a matching file is opened, so their detail isn't repeated here —
  notably the exception hierarchy (one `CuniformException`, a subclass per failure
  category), session-cookie attributes, upload validation, and test isolation rules.
- `/cuniform-task [id]`, `/cuniform-verify`, and `/cuniform-spec-check [component]` are
  project skills implementing the workflow in "Working style" below. Prefer them over
  re-deriving the same steps ad hoc.

## Conventions

- `declare(strict_types=1);` in every PHP file. PSR-12. PHPStan level 8, no baseline
  entries added without a comment saying why.
- Namespace `Cuniform\`, PSR-4 against `src/`, including the renderer fork
  (`Cuniform\Render\Md2Html`) — it autoloads like any other class, no bare `require`.
- No static mutable state. Constructor injection. Objects that cross layer boundaries are
  immutable value objects (`readonly`).
- Filesystem access goes through one gateway class that enforces `realpath()` containment
  under `content/` — never `file_get_contents()` on a path derived from input.
- Errors during a build are **collected and reported together**, then the build exits non-zero.
  Do not fail on the first bad document.

## What not to do

- Do not add a framework, a template engine, or a Composer runtime dependency. If a task
  seems to need one, stop and say so instead of adding it.
- Do not introduce a database, a cache server, or a queue. `var/` files and `flock` are the
  concurrency primitives.
- Do not write to `releases/` or `public` from anything except the build pipeline.
- Do not invent spec behaviour. If `docs/SPEC.md` is silent or ambiguous on something you
  need, ask rather than choosing — the spec is maintained and the gap should be closed there.
- Do not mark a task done until its acceptance criteria in `docs/BUILD-ORDER.md` pass.

## Working style

Work one task at a time from `docs/BUILD-ORDER.md`. Before starting, read the spec sections
the task cites. After finishing, run `make check` and state which acceptance criteria now pass.
`/cuniform-task` sequences this, `/cuniform-verify` runs the gate and reports before fixing,
and `/cuniform-spec-check` audits finished work against the spec.

Tests come with the code, not after it. The parser, slugifier, shortcode layer, route
resolver, and translation grouping are the components where bugs are cheapest to catch in a
unit test and most expensive to catch in production output.
