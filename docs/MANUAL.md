# Cuniform Manual

This is the practical manual for running, maintaining, and customising a Cuniform site.
It reflects the actual code in this repository and the build flow it implements.

Cuniform is a static-site blog and content engine: content is written as Markdown files on disk,
then a build step renders the site into a release directory and swaps it into place as a static
public site. There is no database, no runtime PHP in the public request path, and no Composer
runtime dependency.

## 1. What this project does

The site is assembled from:

- content in `content/`
- settings in `config/site.php`
- templates in `templates/`
- the renderer and build pipeline in `src/`
- a CLI entry point in `bin/cuniform`

The main workflow is:

1. Write Markdown content under `content/`
2. Configure the site in `config/site.php`
3. Run a build
4. Review the generated release and deploy it
5. Repeat as content changes

## 2. Requirements

Minimum local requirements:

- PHP 8.3+
- `ext-intl` recommended; the project includes a fallback path if it is unavailable
- `ext-gd` is required for media upload re-encoding in the admin UI
- Composer, used only for development tooling
- Git

The public site is served as static HTML from the `public/` symlink created by the build.

## 3. Initial setup

From a fresh checkout:

```bash
cd /srv/vhosts/blog.silverday.de
composer install
cp config/site.example.php config/site.php
```

Then edit `config/site.php`.

### 3.1 Minimum settings

At minimum, set these keys:

```php
return [
    'base_url'         => 'https://example.com',
    'title'            => 'Example Site',
    'timezone'         => 'Europe/Berlin',
    'languages'        => ['en', 'de'],
    'default_language' => 'en',
    'url_prefix'       => 'always',
    'permalink'        => '/{slug}/',
    'template_set'     => 'default', // optional named theme under templates/<name>/
    'paths' => [
        'content'   => __DIR__ . '/../content',
        'templates' => __DIR__ . '/../templates',
        'releases'  => __DIR__ . '/../releases',
        'public'    => __DIR__ . '/../public',
        'var'       => __DIR__ . '/../var',
    ],
];
```

The project validates these keys at build time. Invalid values fail the build instead of being silently ignored.

### 3.2 Language and URL rules

The site can be single-language or multilingual.

- `languages` lists the configured languages
- `default_language` is the primary language
- `url_prefix` can be `always`, `auto`, or `never`
- `permalink` controls the route pattern

Examples:

- `/{slug}/`
- `/{year}/{month}/{day}/{slug}/`
- `/{language}/{slug}/` when language is in the path

The build enforces the configured rules and rejects invalid combinations.

## 4. Content layout

Content is stored under `content/` with language and type separated by directory.

Typical layout:

```text
content/
  pages/
    en/
      about.md
      legal/
        privacy.md
    de/
      ueber-uns.md
  posts/
    en/
      2026/
        2026-03-14-first-post.md
    de/
      2026/
        2026-03-14-erster-beitrag.md
  media/
    2026/
      09/
```

A page or post is usually defined by file path and front matter, not by a database record.

## 5. Writing content

Each document is a Markdown file with front matter.

Example post:

```markdown
---
slug: hello-world
status: published
summary: A short summary of the article.
date: 2026-03-14
title: Hello World
language: en
translation_key: hello-world
categories:
  - news
  - updates
tags:
  - launch
  - blog
---

# Hello World

This is the article body.
```

### 5.1 Required fields

The exact required fields are enforced by the front matter parser and build validator.
Typical required values include at least:

- `slug`
- `status`
- `summary`
- `date`
- `title`
- `language`

In actual usage, the schema is validated by the project, and invalid front matter blocks the build.

### 5.2 Status values

The most common values are:

- `draft`
- `published`

A published document is in the live build; a draft is kept in content but excluded from the public site unless previewed directly.

### 5.3 Translation grouping

If two posts belong to a translated set, give them the same `translation_key` across languages.
The build uses that to group translations and generate correct alternates and hreflang metadata.

### 5.4 Legal and static pages

The project expects legal pages to be authored as content files and not committed in their final
personalised form. The example templates under `content/pages/.../*.example.md` are placeholders.

### 5.5 Media and images

Images, diagrams, and other binary media assets are stored under:

```text
content/media/
```

Media is **shared across all languages** and organized by year and month:

```text
content/
  media/
    2026/
      09/
        architecture-diagram.png
        photo.jpg
```

#### Referencing media in content
The public URL prefix for all media is `/media/...`.

1. **In Markdown body:**
   ```markdown
   ![Architecture Diagram](/media/2026/09/architecture-diagram.png)
   ```

2. **In document front matter (featured / header image):**
   ```markdown
   ---
   slug: release-announcement
   title: Release Announcement
   image: /media/2026/09/photo.jpg
   image_alt: Team photo at release
   ---
   ```

3. **In shortcodes (`[figure]`, `[video]`):**
   ```markdown
   [figure src="/media/2026/09/architecture-diagram.png" caption="System Architecture" /]
   ```

#### How media is processed and deployed
- **Build deployment**: During the build step, `MediaCopier` copies the entire `content/media/` directory into `/media/` at the root of the generated release.
- **Verification**: The build verification stage checks internal media references to ensure no broken image links are published.
- **Uploading via Admin**: You can upload images directly through the Admin interface at `/admin/media.php`, which validates dimensions and formats, stores them under `content/media/YYYY/MM/`, and generates the `/media/YYYY/MM/...` markup for you.
- **Adding manually**: You can also add files directly to `content/media/YYYY/MM/` on disk (or sync them from external backup storage) and run `php bin/cuniform build`.

## 6. Building the site

Run the project in dry-run mode first:

```bash
php bin/cuniform build --dry-run
```

Then do the real build:

```bash
php bin/cuniform build
```

Useful additional build flags and CLI commands:

```bash
# Force a full rebuild (bypass incremental cache)
php bin/cuniform build --full

# Roll back public symlink to the previous release
php bin/cuniform build --rollback

# Allow changing URL scheme / language prefix configuration
php bin/cuniform build --allow-url-scheme-change

# Initialize the public/ symlink on a freshly provisioned host
php bin/cuniform setup-public

# Quality checks
make check
make test
make stan
make lint
```

### 6.1 What the build does

The build pipeline includes:

- lock acquisition
- content discovery
- front matter parsing
- route resolution
- rendering Markdown and shortcodes
- template rendering
- artifact generation (RSS/Atom feeds, sitemap, search index, robots.txt, security.txt, fingerprinted assets)
- verification (internal link checking, media presence, HTML structure)
- atomic deploy to `public/`

The `--dry-run` mode validates everything without writing or deploying output.

### 6.2 Incremental rebuilds

The project supports an incremental build cache. If a post or template changes, the relevant output is rebuilt without redoing the entire site unless needed.

If something seems stale or a config/template change may affect everything, use:

```bash
php bin/cuniform build --full
```

### 6.3 Host provisioning (`setup-public`)

On freshly provisioned web hosts, the `public/` docroot often starts as an ordinary directory created by the operating system or Apache.

Before the first build, run:

```bash
php bin/cuniform setup-public
```

This safely archives any existing `public/` directory (moving it to `public.provisioned-YYYYMMDDHHMMSS`) and initializes `public/` as the atomic symlink required by `ReleaseDeployer`.

### 6.4 URL scheme changes

Cuniform guards against accidental URL breaks. If you alter `languages`, `default_language`, or `url_prefix` in `config/site.php`, the build will intentionally abort to prevent breaking existing canonical URLs.

If the change is intentional, run:

```bash
php bin/cuniform build --allow-url-scheme-change
```

## 7. Maintaining and updating a site

### 7.1 Add a post

Create a file such as:

```text
content/posts/en/2026/2026-03-14-new-post.md
```

Then add valid front matter and Markdown body, then run:

```bash
php bin/cuniform build --dry-run
php bin/cuniform build
```

### 7.2 Edit a page

Edit the relevant Markdown file directly. The build will detect the change and regenerate the page and the route affected by it.

### 7.3 Rename or move a post

The project supports moves as document operations, but the route is based on slug and language,
so changing location or slug is a real content change requiring rebuild and a check for collisions.

### 7.4 Update templates and styles

When a template or stylesheet changes, the build invalidates the cached output, and pages are rebuilt accordingly.

### 7.5 Redirects

The project supports redirects via a redirect map and aliases. Manual entries can live under:

```text
content/redirects.map
```

This is part of the build output and the generated redirect rules that are intended for the site vhost.

## 8. Setting up the admin user

The admin app is protected and requires a real account before it can be used.

Create the first user from the command line with:

```bash
php bin/cuniform admin-create-account myuser
```

If you want to provide the password via a file instead of the command line, use:

```bash
printf '%s\n' 'your-strong-password' > /tmp/admin-password.txt
php bin/cuniform admin-create-account myuser --password-file=/tmp/admin-password.txt
```

The command creates the account and prints:

- the username
- the generated TOTP secret in base32 format
- a provisioning URI for QR code generation
- recovery codes

Important: do not store the password in the repository or in an ordinary shell history.
Use a temporary file or a secure secret store, and delete it once the account is created.

### 8.1 TOTP setup

After creating the account, the operator must set up TOTP in an authenticator app.

The generated secret can be entered manually or scanned as a QR code using the provisioning URI.
The login flow is two-step:

1. enter the username and password
2. enter the 6-digit TOTP code or one of the recovery codes

### 8.2 Logging in

Once the user is created, open the admin app in the browser, for example:

```text
https://example.com/admin/login.php
```

Then:

1. enter your username
2. enter the password
3. enter the 6-digit TOTP code
4. continue to the main admin dashboard

### 8.3 Admin workflow

The admin app is used for authenticated tasks such as:

- document management
- editor screens
- preview rendering
- media uploads
- build-trigger requests

This is a real app, but it still relies on the same build system and content files. The admin app is not a database-backed CMS; it writes to the content tree and commits changes through Git in the same way as the CLI workflow.

Typical flow:

1. Log in to the admin app
2. Open a document
3. Edit front matter or body
4. Save or preview
5. Build is triggered as needed
6. Review generated output

## 9. Creating templates and template sets

Templates live under `templates/` and are chosen by the build using the allow-list in the template resolver.

Allowed template names include:

- `layout.php`
- `post.php`
- `page.php`
- `index.php`
- `tag.php`
- `series.php`
- `archive.php`
- `search.php`
- `404.php`
- `404-root.php`

Allowed partial names (under `partials/` or `<template-set>/partials/`) include:

- `head.php`
- `nav-primary.php`
- `nav-footer.php`
- `lang-switcher.php`
- `post-card.php`
- `pagination.php`
- `toc.php`

### 9.1 Configurable template sets and fallback

Cuniform supports custom template sets via the `template_set` setting in `config/site.php`.

```php
'template_set' => 'mytheme',
```

When a template set is configured:

1. The resolver looks for the requested template file under `templates/<template-set>/<filename>`.
2. If the file does not exist in that subdirectory, Cuniform automatically falls back to the default file in the root `templates/<filename>`.
3. Partials resolved via `partial('name.php')` check `templates/<template-set>/partials/<name.php>` first, then fall back to `templates/partials/<name.php>`.
4. Template assets such as `style.css` and `search.js` follow the same fallback logic: if `templates/<template-set>/style.css` exists, it is fingerprinted and deployed; otherwise `templates/style.css` is used.

This means you do not need to duplicate every template or partial to create a theme. For example, to customize only the site layout and the post card presentation:

```text
templates/
  layout.php                <-- default fallback
  page.php                  <-- default fallback
  post.php                  <-- default fallback
  style.css                 <-- default fallback
  partials/
    head.php                <-- default fallback
    post-card.php           <-- default fallback
    pagination.php          <-- default fallback
    toc.php                 <-- default fallback
  mytheme/
    layout.php              <-- overrides default layout
    partials/
      post-card.php         <-- overrides default post card partial
```

All other templates and partials (e.g. `page.php`, `post.php`, `partials/head.php`, `partials/pagination.php`, etc.) continue to resolve seamlessly from the default `templates/` directory.

### 9.2 Template entry points and partials

The main layout file is `layout.php` (in your template set or the root `templates/`).
It provides the page shell:

- `<!DOCTYPE html>`
- `<html lang>`
- `<head>` (via `partial('head.php')`)
- primary navigation (via `partial('nav-primary.php')`)
- language switcher (via `partial('lang-switcher.php')`)
- main content area
- footer navigation (via `partial('nav-footer.php')`)

`page.php` and `post.php` are the document body templates; they render content into the layout and include `partial('toc.php')` when table of contents rendering is active.

Listing templates (`index.php`, `tag.php`, `series.php`, `archive.php`, `search.php`) render paginated collections and taxonomies using `partial('post-card.php')` and `partial('pagination.php')`.

### 9.3 Template conventions and helpers

Templates and partials are plain PHP files. Escaping is deliberate and centralized.

Use these helpers:

- `e($value)` for regular text
- `eAttr($value)` for HTML attributes
- `eUrl($value)` for URLs
- `eJs($value)` for JavaScript context
- `t($key)` for localized UI strings
- `partial($name)` to include a partial with template-set fallback (e.g. `include partial('head.php');` or `include partial('post-card.php');`)

The project deliberately treats `$doc->bodyHtml` as the one unescaped rendering sink. Everything else should be escaped.

### 9.4 Example page template

```php
<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\PageViewModel $context
 */
$doc = $context;
?>
<article class="page">
    <h1><?= e($doc->title) ?></h1>
    <?php include partial('toc.php'); ?>
    <div class="page-body">
        <?= $doc->bodyHtml ?>
    </div>
</article>
```

### 9.5 Example layout template

```php
<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\LayoutContext $context
 */
$layout = $context;
$doc = $layout->page;
?>
<!DOCTYPE html>
<html lang="<?= eAttr($doc->language) ?>">
<head>
<?php include partial('head.php'); ?>
</head>
<body>
<?php include partial('nav-primary.php'); ?>
<?php include partial('lang-switcher.php'); ?>
<main>
    <?= $layout->content ?>
</main>
<?php include partial('nav-footer.php'); ?>
</body>
</html>
```

### 9.6 Working with Partials

Partials are reusable snippet templates located in `templates/partials/` or `templates/<template-set>/partials/`. They execute in the scope of the caller or read from `$doc`/`$layout`/`$item`:

- **`head.php`**: Renders `<title>`, `<meta description>`, canonical links, stylesheet `<link>`, and alternate language hreflang links.
- **`nav-primary.php`**: Renders the primary navigation list with hierarchy support.
- **`nav-footer.php`**: Renders footer navigation links.
- **`lang-switcher.php`**: Renders the language toggle list when the site is multilingual.
- **`post-card.php`**: Renders individual post teaser cards in listing and archive pages.
- **`pagination.php`**: Renders previous/next and page counter links for paginated listings.
- **`toc.php`**: Renders the nested table of contents list when enabled in front matter.

To include a partial from any template, use `<?php include partial('<name>.php'); ?>`.

### 9.7 Front matter template selection

A page front matter block may specify a custom `template` name (defaults to `page.php`):

```markdown
---
slug: custom-page
title: Custom Page
status: published
summary: A page with custom layout
language: en
template: page.php
---
```

When resolving a named template, the resolver checks `templates/<template-set>/<template>` first, then falls back to `templates/<template>`. The template name must be present in the allowed list in `TemplateResolver`.

### 9.8 Adding a custom template or partial

Because template names from front matter represent a security trust boundary (preventing path traversal and unintended file inclusion), template and partial names are checked against strict allow-lists in [src/Template/TemplateResolver.php](../src/Template/TemplateResolver.php).

To create and use a new custom page template (e.g. `landing.php`):

1. **Register the template name in `src/Template/TemplateResolver.php`**:
   Add the filename to the `ALLOWED` list:
   ```php
   private const ALLOWED = [
       'layout.php',
       'post.php',
       'page.php',
       'index.php',
       'tag.php',
       'series.php',
       'archive.php',
       'search.php',
       '404.php',
       '404-root.php',
       'landing.php', // <-- add your new template here
   ];
   ```

2. **Create the template file**:
   Place the template either in `templates/landing.php` (for the default set) or inside a theme directory `templates/<template-set>/landing.php`. The template receives a `Cuniform\Template\PageViewModel` instance as `$context`:
   ```php
   <?php

   declare(strict_types=1);

   /**
    * @var Cuniform\Template\PageViewModel $context
    */
   $doc = $context;
   ?>
   <section class="landing-page">
       <header class="landing-hero">
           <h1><?= e($doc->title) ?></h1>
           <p class="summary"><?= e($doc->summary) ?></p>
       </header>
       <div class="landing-content">
           <?= $doc->bodyHtml ?>
       </div>
   </section>
   ```

3. **Use the template in page front matter**:
   In your Markdown document under `content/pages/`:
   ```markdown
   ---
   slug: welcome
   title: Welcome to SilverDay
   status: published
   summary: Special landing page
   language: en
   template: landing.php
   ---
   ```

4. **Adding a custom partial**:
   If you need a new reusable partial (e.g. `newsletter.php`), add `'newsletter.php'` to `TemplateResolver::ALLOWED_PARTIALS`, create `templates/partials/newsletter.php` (or `templates/<template-set>/partials/newsletter.php`), and call `<?php include partial('newsletter.php'); ?>`.

5. **Verify**:
   Run `make check` to verify linting and test coverage, then test your build with `php bin/cuniform build --dry-run`.

## 10. Working with Markdown and shortcodes

The renderer converts Markdown documents to clean HTML. Raw HTML in document bodies is escaped by default for safety; all rich or structured markup is emitted through shortcode handlers.

### 10.1 Shortcode reference

- **`[figure src="..." alt="..." caption="..." width="..." height="..." /]`**
  Renders responsive, lazy-loaded `<figure>` elements with optional `<figcaption>`:
  ```markdown
  [figure src="/media/2026/09/chart.png" alt="Benchmark" caption="Performance over time" /]
  ```

- **`[video src="..." poster="..." /]`**
  Renders self-hosted `<video controls preload="metadata">`:
  ```markdown
  [video src="/media/2026/09/demo.mp4" poster="/media/2026/09/demo-thumb.jpg" /]
  ```

- **`[embed provider="youtube|vimeo" id="..." /]`**
  Click-to-load privacy facade (no external network requests or third-party tracking until clicked):
  ```markdown
  [embed provider="youtube" id="dQw4w9WgXcQ" /]
  ```

- **`[details summary="..."]...[/details]`**
  Native HTML5 expandable accordion without JavaScript:
  ```markdown
  [details summary="Technical Implementation Details"]
  This content is collapsible and hidden until expanded.
  [/details]
  ```

- **`[note type="info|warn|danger"]...[/note]`**
  Styled callout box (defaults to `info` if omitted):
  ```markdown
  [note type="warn"]
  Remember to backup your content directory before migrations.
  [/note]
  ```

- **`[toc /]`**
  Inserts an inline table of contents rendered from the document's headings.

- **`[include page="<slug>" /]`**
  Transcludes the rendered body of another page in the **same language** (nesting capped at depth 2; cycles are detected and fail the build):
  ```markdown
  [include page="shared-disclaimer" /]
  ```

## 11. WordPress migration and review workflow

Cuniform includes a built-in import pipeline for migrating sites from WordPress WXR export files.

### 11.1 Staging the import

Export your WordPress site via **Tools → Export → All content** (or Posts). Then run:

```bash
php bin/cuniform import-wxr /path/to/export.xml
```

This performs a safe import:
- Extracts post content, publication dates, and tags/categories into front matter.
- Rewrites legacy `/wp-content/uploads/YYYY/MM/` URLs to `/media/YYYY/MM/`.
- Validates integrity and stages candidate files in `var/import/` (it **never** writes directly into `content/`).
- Initializes a review checklist in `var/import-review.json`.

### 11.2 Reviewing staged documents

Review status across all imported posts:

```bash
php bin/cuniform review-status
```

Mark individual documents as approved (`keep`), rejected (`reject`), or `pending`:

```bash
php bin/cuniform review-mark 10 keep
php bin/cuniform review-mark 12 reject
```

Once reviewed, copy the approved markdown files from `var/import/` into `content/posts/<lang>/...` and download any referenced media into `content/media/`.

### 11.3 Legacy URL crawling

To verify that legacy URLs are mapped to redirects, crawl the old live site:

```bash
php bin/cuniform legacy-urls https://old-blog.example.com --output=var/legacy-urls.txt
```

This crawls sitemaps or spiders same-host links to generate an inventory against which `content/redirects.map` entries can be validated.

## 12. Deployment and production

The project includes production deployment assets under `deploy/`:

- **Apache vhost** (`deploy/apache/blog.silverday.de.conf`):
  - Pre-configured vhost with static docroot pointed at `public/`.
  - Secure `/admin` FastCGI socket configuration and IP allow-list.
  - Caching and security headers.
  - Proper `ErrorDocument 404` routing.

- **Systemd automation** (`deploy/systemd/`):
  - `cuniform-build.path` & `cuniform-build.service`: watches `var/trigger-build` to execute builds automatically when triggered via Admin or webhooks.
  - `cuniform-rollback.path` & `cuniform-rollback.service`: watches `var/trigger-rollback`.
  - `cuniform-scheduled-build.timer`: runs periodically to publish scheduled/future-dated posts.

- **Git push-to-deploy hook** (`deploy/git/post-receive`):
  - Place in a bare Git repository on your server to automatically rebuild upon `git push`.

### 12.1 Production deployment checklist

1. Prepare `config/site.php` on the production server.
2. Initialize symlink: `php bin/cuniform setup-public`.
3. Create admin account: `php bin/cuniform admin-create-account <user>`.
4. Run dry run: `php bin/cuniform build --dry-run`.
5. Run full build: `php bin/cuniform build --full`.
6. Enable systemd units:
   ```bash
   systemctl enable --now cuniform-build.path cuniform-scheduled-build.timer
   ```

## 13. Recommended maintenance workflow

For a normal site, the workflow is:

```bash
# 1. edit content or templates
# 2. validate
make check

# 3. dry-run build
php bin/cuniform build --dry-run

# 4. real build
php bin/cuniform build
```

For a bigger change or a template update:

```bash
php bin/cuniform build --full
```

For rollback:

```bash
php bin/cuniform build --rollback
```

## 14. Common pitfalls

- Invalid front matter blocks the build
- Routing collisions are rejected
- Changing URL prefixes or languages without `--allow-url-scheme-change` aborts the build
- Unknown template names fail validation
- `javascript:`/`data:` URLs are rejected in template and shortcode output
- Content paths must stay under the configured content root
- The project does not accept arbitrary user-submitted HTML in document bodies

## 15. Where to look next

Important files for day-to-day work:

- [README.md](../README.md)
- [docs/SPEC.md](SPEC.md)
- [docs/BUILD-ORDER.md](BUILD-ORDER.md)
- [config/site.example.php](../config/site.example.php)
- [templates/layout.php](../templates/layout.php)
- [templates/page.php](../templates/page.php)
- [src/Template/TemplateResolver.php](../src/Template/TemplateResolver.php)

This is the basic operational manual for Cuniform. For the deeper design rules and acceptance
criteria, read [docs/SPEC.md](SPEC.md) and [docs/BUILD-ORDER.md](BUILD-ORDER.md).
