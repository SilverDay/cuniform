---
paths:
  - "content/**"
---

# Content rules

- The first path segment under `posts/` and `pages/` is the language code — always present,
  even for a single-language site (SPEC §7.2).
- Post filenames: `YYYY-MM-DD-slug.md`. The authoritative date and slug are in front matter.
- Front matter is a restricted YAML subset (SPEC §5.2): scalars, quoted strings, flat
  sequences, ISO-8601 dates. No anchors, aliases, custom tags, merge keys.
- `lang` is NOT a front matter key. Language comes from the tree.
- A `slug` in front matter is used verbatim and never re-slugified.
- Never write example content containing raw HTML — it would be escaped and shown as text.
- Test fixtures live in `tests/fixtures/`, not in `content/`.
