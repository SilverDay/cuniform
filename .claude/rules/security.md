---
paths:
  - "src/**/*.php"
  - "admin/**/*.php"
  - "templates/**/*.php"
---

# Security rules

Requirements from SPEC §4.6, §13, §14. A violation is a build-breaking defect, not a nit.

## Paths and files

- Every content read goes through the filesystem gateway: `realpath()`, assert a strict
  descendant of `content/`, assert extension is `md` or `markdown`, assert size <= 2 MiB.
  The renderer's own file guards are NOT inherited — the engine calls `convert()` with a
  string, not `convertFile()`.
- A path never comes from request input. The admin app takes a document **identifier** and
  resolves it against the content index.
- Template names resolve against an allow-list of files in `templates/`, never as a path.

## Output escaping

- Templates escape by default: `e()`, `eAttr()`, `eUrl()` (scheme allow-list), `eJs()`.
- Exactly ONE unescaped sink exists in the codebase: `$doc->bodyHtml`, renderer output. If a
  second seems necessary, the answer is a shortcode handler instead.
- Shortcode handlers escape every attribute value themselves. They are a trust boundary and
  will be handling imported content in P3.

## Admin (P2)

- Argon2id, minimum 12 characters, no composition rules, no forced rotation, breached-list check.
- TOTP mandatory. Recovery is single-use hashed codes — never an email reset path.
- Session cookie: `Secure`, `HttpOnly`, `SameSite=Strict`, `__Secure-` prefix, `Path=/admin`.
  Do not use `__Host-`; it mandates `Path=/`.
- CSRF synchronizer token on every state-changing request.
- Uploads: extension AND content-type AND magic bytes, re-encoded to strip EXIF, random name.
- `git` runs via `proc_open` with an argument array. Never build a shell string.
- Mail headers are assembled by a helper with CRLF validation. Never concatenate.

## Never

- No `unsafe-inline` or `unsafe-eval` in CSP. Public pages are static and carry no
  per-request nonce — use hashed inline scripts, or preferably external files only.
- No third-party origin in any public page: fonts self-hosted, embeds click-to-load.
- No user-submitted content of any kind. There is no comment form and no contact form.
