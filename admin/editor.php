<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Cuniform\Admin\AdminBootstrap;
use Cuniform\Admin\AdminException;
use Cuniform\Admin\Editor\EditorDocument;
use Cuniform\Admin\Editor\EditorSaveRequest;
use Cuniform\Admin\Editor\EditorSaveStatus;
use Cuniform\Admin\Editor\LineDiffer;
use Cuniform\Admin\Editor\LineDiffOp;
use Cuniform\Content\ContentException;
use Cuniform\Content\FrontMatter\DocumentKind;

/**
 * @return array<string, string|bool>
 */
function editor_blank_form_values(string $language, string $kind): array
{
    return [
        'identifier' => '', 'expected_sha256' => '', 'language' => $language, 'kind' => $kind,
        'title' => '', 'slug' => '', 'status' => 'draft', 'summary' => '',
        'translation_key' => '', 'updated' => '', 'image' => '', 'image_alt' => '',
        'canonical' => '', 'noindex' => false, 'aliases' => '', 'toc' => false, 'source_id' => '',
        'date' => '', 'tags' => '', 'series' => '',
        'template' => 'page.php', 'nav_label' => '', 'nav_order' => '', 'nav_parent' => '',
        'nav_group' => '', 'sitemap_priority' => '', 'legal' => '',
        'body' => '',
    ];
}

/**
 * @return array<string, string|bool>
 */
function editor_form_values_from_document(EditorDocument $doc): array
{
    return [
        'identifier' => $doc->identifier,
        'expected_sha256' => $doc->sha256,
        'language' => $doc->language,
        'kind' => $doc->kind === DocumentKind::Post ? 'post' : 'page',
        'title' => $doc->title,
        'slug' => $doc->slug,
        'status' => $doc->status->value,
        'summary' => $doc->summary,
        'translation_key' => $doc->translationKey ?? '',
        'updated' => $doc->updated?->format('Y-m-d\TH:i:sP') ?? '',
        'image' => $doc->image ?? '',
        'image_alt' => $doc->imageAlt ?? '',
        'canonical' => $doc->canonical ?? '',
        'noindex' => $doc->noindex,
        'aliases' => implode("\n", $doc->aliases),
        'toc' => $doc->toc,
        'source_id' => $doc->sourceId ?? '',
        'date' => $doc->date?->format('Y-m-d\TH:i:sP') ?? '',
        'tags' => implode(', ', $doc->tags),
        'series' => $doc->series ?? '',
        'template' => $doc->template ?? 'page.php',
        'nav_label' => $doc->navLabel ?? '',
        'nav_order' => $doc->navOrder === null ? '' : (string) $doc->navOrder,
        'nav_parent' => $doc->navParent ?? '',
        'nav_group' => $doc->navGroup === null ? '' : $doc->navGroup->value,
        'sitemap_priority' => $doc->sitemapPriority === null ? '' : (string) $doc->sitemapPriority,
        'legal' => $doc->legal === null ? '' : $doc->legal->value,
        'body' => $doc->body,
    ];
}

/**
 * @return array<string, string|bool>
 */
function editor_form_values_from_post(): array
{
    return [
        'identifier' => admin_post_field('identifier'),
        'expected_sha256' => admin_post_field('expected_sha256'),
        'language' => admin_post_field('language'),
        'kind' => admin_post_field('kind'),
        'title' => admin_post_field('title'),
        'slug' => admin_post_field('slug'),
        'status' => admin_post_field('status'),
        'summary' => admin_post_field('summary'),
        'translation_key' => admin_post_field('translation_key'),
        'updated' => admin_post_field('updated'),
        'image' => admin_post_field('image'),
        'image_alt' => admin_post_field('image_alt'),
        'canonical' => admin_post_field('canonical'),
        'noindex' => admin_post_checked('noindex'),
        'aliases' => admin_post_field('aliases'),
        'toc' => admin_post_checked('toc'),
        'source_id' => admin_post_field('source_id'),
        'date' => admin_post_field('date'),
        'tags' => admin_post_field('tags'),
        'series' => admin_post_field('series'),
        'template' => admin_post_field('template'),
        'nav_label' => admin_post_field('nav_label'),
        'nav_order' => admin_post_field('nav_order'),
        'nav_parent' => admin_post_field('nav_parent'),
        'nav_group' => admin_post_field('nav_group'),
        'sitemap_priority' => admin_post_field('sitemap_priority'),
        'legal' => admin_post_field('legal'),
        'body' => admin_post_field('body'),
    ];
}

function editor_request_from_post(): EditorSaveRequest
{
    $kind = admin_post_field('kind') === 'page' ? DocumentKind::Page : DocumentKind::Post;

    return new EditorSaveRequest(
        identifier: admin_post_field_or_null('identifier'),
        expectedSha256: admin_post_field_or_null('expected_sha256'),
        language: admin_post_field('language'),
        kind: $kind,
        title: admin_post_field('title'),
        slug: admin_post_field('slug'),
        status: admin_post_field('status'),
        summary: admin_post_field('summary'),
        translationKey: admin_post_field_or_null('translation_key'),
        updated: admin_post_field_or_null('updated'),
        image: admin_post_field_or_null('image'),
        imageAlt: admin_post_field_or_null('image_alt'),
        canonical: admin_post_field_or_null('canonical'),
        noindex: admin_post_checked('noindex'),
        aliases: admin_post_lines('aliases'),
        toc: admin_post_checked('toc'),
        sourceId: admin_post_field_or_null('source_id'),
        date: admin_post_field_or_null('date'),
        tags: admin_post_csv('tags'),
        series: admin_post_field_or_null('series'),
        template: admin_post_field_or_null('template'),
        navLabel: admin_post_field_or_null('nav_label'),
        navOrder: admin_post_field_or_null('nav_order'),
        navParent: admin_post_field_or_null('nav_parent'),
        navGroup: admin_post_field_or_null('nav_group'),
        sitemapPriority: admin_post_field_or_null('sitemap_priority'),
        legal: admin_post_field_or_null('legal'),
        body: admin_post_field('body'),
    );
}

admin_security_headers();

$context = AdminBootstrap::create(dirname(__DIR__));
$config  = $context->config;
$store   = $context->editorDocumentStore;

$session = admin_require_session($context->loginService);
$csrf    = admin_csrf_value($context->csrfToken, $context->adminCookie);

$formErrors   = [];
$conflict     = null;
$conflictDiff = [];
$notFound     = null;
$doc          = null;
$translations = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid($context->csrfToken)) {
        $formErrors = ['Your session expired — please try again.'];
        $formValues = editor_form_values_from_post();
    } elseif (admin_post_field('form_action') === 'move') {
        $identifier = admin_post_field('identifier');
        $outcome    = $store->move($identifier, admin_post_field('new_language'), admin_post_field('new_slug'), $session->username, $config->mail->notify);

        if ($outcome->status === EditorSaveStatus::Saved && $outcome->document !== null) {
            header('Location: /admin/editor.php?id=' . urlencode($outcome->document->identifier) . '&moved=1');
            exit;
        }

        $formErrors = $outcome->status === EditorSaveStatus::NotFound
            ? ["'{$identifier}' no longer exists — it may have been edited elsewhere."]
            : $outcome->errors;

        try {
            $doc          = $store->load($identifier);
            $formValues   = editor_form_values_from_document($doc);
            $translations = $store->translations($doc);
        } catch (AdminException|ContentException) {
            $formValues = editor_blank_form_values($config->defaultLanguage, 'post');
        }
    } else {
        $request = editor_request_from_post();
        $outcome = $store->save($request, $session->username, $config->mail->notify);

        if ($outcome->status === EditorSaveStatus::Saved && $outcome->document !== null) {
            header('Location: /admin/editor.php?id=' . urlencode($outcome->document->identifier) . '&saved=1');
            exit;
        }

        $formValues = editor_form_values_from_post();

        if ($outcome->status === EditorSaveStatus::Conflict && $outcome->current !== null && $outcome->currentRaw !== null) {
            $conflict     = $outcome->current;
            $attempted    = $store->render($request);
            $conflictDiff = (new LineDiffer())->diff($attempted, $outcome->currentRaw);
        } elseif ($outcome->status === EditorSaveStatus::NotFound) {
            $notFound = $outcome->notFoundIdentifier;
        } else {
            $formErrors = $outcome->errors;
        }
    }
} else {
    $id  = $_GET['id'] ?? null;
    $isNew = isset($_GET['new']);

    if (is_string($id) && $id !== '') {
        try {
            $doc          = $store->load($id);
            $formValues   = editor_form_values_from_document($doc);
            $translations = $store->translations($doc);
        } catch (AdminException) {
            $notFound   = $id;
            $formValues = editor_blank_form_values($config->defaultLanguage, 'post');
        } catch (ContentException $e) {
            $formErrors = [$e->getMessage()];
            $formValues = editor_blank_form_values($config->defaultLanguage, 'post');
        }
    } elseif ($isNew) {
        $lang = $_GET['lang'] ?? $config->defaultLanguage;
        $lang = is_string($lang) && in_array($lang, $config->languages, true) ? $lang : $config->defaultLanguage;
        $kind = ($_GET['kind'] ?? 'post') === 'page' ? 'page' : 'post';
        $formValues = editor_blank_form_values($lang, $kind);
    } else {
        header('Location: /admin/documents.php');
        exit;
    }
}

/** @var array<string, string|bool> $formValues */
$isEditing = $formValues['identifier'] !== '';
$isPost    = $formValues['kind'] === 'post';
$saved     = isset($_GET['saved']);
$moved     = isset($_GET['moved']);

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cuniform admin — editor</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<main class="admin-page">
<h1><?= e($config->title) ?> — editor</h1>
<p><a href="/admin/documents.php">&larr; Documents</a></p>

<?php if ($saved): ?>
<p class="admin-notice">Saved.</p>
<?php endif; ?>
<?php if ($moved): ?>
<p class="admin-notice">Moved.</p>
<?php endif; ?>

<?php if ($notFound !== null): ?>
<p class="admin-error" role="alert">'<?= e($notFound) ?>' does not exist — it may have been deleted or edited elsewhere.</p>
<?php endif; ?>

<?php if ($formErrors !== []): ?>
<p class="admin-error" role="alert">
This could not be saved:
<ul>
<?php foreach ($formErrors as $formError): ?>
<li><?= e($formError) ?></li>
<?php endforeach; ?>
</ul>
</p>
<?php endif; ?>

<?php if ($conflict !== null): ?>
<div class="admin-error" role="alert">
<p>Someone else changed this document since you loaded it — nothing was written. Reload to see
the current version, or copy anything you still want to keep from your own edit above.</p>
<p>What's on disk now (title: <?= e($conflict->title) ?>, status: <?= e($conflict->status->value) ?>) vs. what
you were about to save:</p>
<pre class="admin-diff"><?php foreach ($conflictDiff as $entry): ?><span class="diff-<?= e(strtolower($entry->op->name)) ?>"><?= $entry->op === LineDiffOp::Added ? '+ ' : ($entry->op === LineDiffOp::Removed ? '- ' : '  ') ?><?= e($entry->line) ?>
</span><?php endforeach; ?></pre>
</div>
<?php endif; ?>

<?php if ($isEditing && $translations !== []): ?>
<div class="admin-translations">
<h2>Translations</h2>
<ul>
<?php foreach ($translations as $sibling): ?>
<li><?= e($sibling->language) ?>: <a href="/admin/editor.php?id=<?= eAttr(urlencode($sibling->identifier)) ?>"><?= e($sibling->title) ?></a> (<?= e($sibling->status->value) ?>)</li>
<?php endforeach; ?>
</ul>
</div>
<?php elseif ($isEditing && $doc !== null && $doc->translationKey === null): ?>
<p class="admin-hint">No <code>translation_key</code> set — this document isn't linked to any translation.</p>
<?php endif; ?>

<form method="post" action="/admin/editor.php" class="admin-editor-form">
<input type="hidden" name="csrf_token" value="<?= eAttr($csrf) ?>">
<input type="hidden" name="form_action" value="save">
<input type="hidden" name="identifier" value="<?= eAttr((string) $formValues['identifier']) ?>">
<input type="hidden" name="expected_sha256" value="<?= eAttr((string) $formValues['expected_sha256']) ?>">
<input type="hidden" name="language" value="<?= eAttr((string) $formValues['language']) ?>">
<input type="hidden" name="kind" value="<?= eAttr((string) $formValues['kind']) ?>">

<p><strong>Language:</strong> <?= e((string) $formValues['language']) ?> &middot;
<strong>Kind:</strong> <?= e((string) $formValues['kind']) ?></p>

<label for="title">Title</label>
<input type="text" id="title" name="title" value="<?= eAttr((string) $formValues['title']) ?>" required>

<label for="slug">Slug</label>
<input type="text" id="slug" name="slug" value="<?= eAttr((string) $formValues['slug']) ?>" pattern="[a-z0-9-]{1,96}" required>

<label for="status">Status</label>
<select id="status" name="status">
<?php foreach (['draft', 'published', 'scheduled'] as $statusOption): ?>
<option value="<?= eAttr($statusOption) ?>"<?= $formValues['status'] === $statusOption ? ' selected' : '' ?>><?= e($statusOption) ?></option>
<?php endforeach; ?>
</select>

<label for="summary">Summary (max 200 characters)</label>
<textarea id="summary" name="summary" maxlength="200" rows="2"><?= e((string) $formValues['summary']) ?></textarea>

<label for="translation_key">Translation key</label>
<input type="text" id="translation_key" name="translation_key" value="<?= eAttr((string) $formValues['translation_key']) ?>">

<label for="updated">Updated (ISO-8601, optional)</label>
<input type="text" id="updated" name="updated" value="<?= eAttr((string) $formValues['updated']) ?>" placeholder="2026-03-14T10:00:00+01:00">

<label for="image">Image path</label>
<input type="text" id="image" name="image" value="<?= eAttr((string) $formValues['image']) ?>" placeholder="/media/2026/03/example.jpg">

<label for="image_alt">Image alt text (required when an image is set)</label>
<input type="text" id="image_alt" name="image_alt" value="<?= eAttr((string) $formValues['image_alt']) ?>">

<label for="canonical">Canonical URL</label>
<input type="text" id="canonical" name="canonical" value="<?= eAttr((string) $formValues['canonical']) ?>">

<label><input type="checkbox" name="noindex"<?= $formValues['noindex'] ? ' checked' : '' ?>> noindex</label>
<label><input type="checkbox" name="toc"<?= $formValues['toc'] ? ' checked' : '' ?>> Table of contents</label>

<label for="aliases">Old paths that should redirect here (one per line)</label>
<textarea id="aliases" name="aliases" rows="3"><?= e((string) $formValues['aliases']) ?></textarea>

<label for="source_id">Source ID (opaque, rarely set by hand)</label>
<input type="text" id="source_id" name="source_id" value="<?= eAttr((string) $formValues['source_id']) ?>">

<?php if ($isPost): ?>
<h2>Post fields</h2>
<label for="date">Date (ISO-8601, required)</label>
<input type="text" id="date" name="date" value="<?= eAttr((string) $formValues['date']) ?>" placeholder="2026-03-14T10:00:00+01:00" required>

<label for="tags">Tags (comma-separated)</label>
<input type="text" id="tags" name="tags" value="<?= eAttr((string) $formValues['tags']) ?>">

<label for="series">Series</label>
<input type="text" id="series" name="series" value="<?= eAttr((string) $formValues['series']) ?>">
<?php else: ?>
<h2>Page fields</h2>
<label for="template">Template (usually "page.php")</label>
<input type="text" id="template" name="template" value="<?= eAttr((string) $formValues['template']) ?>">

<label for="nav_label">Nav label (falls back to title)</label>
<input type="text" id="nav_label" name="nav_label" value="<?= eAttr((string) $formValues['nav_label']) ?>">

<label for="nav_order">Nav order (leave blank to keep this page out of the nav)</label>
<input type="text" id="nav_order" name="nav_order" value="<?= eAttr((string) $formValues['nav_order']) ?>" inputmode="numeric">

<label for="nav_parent">Nav parent slug</label>
<input type="text" id="nav_parent" name="nav_parent" value="<?= eAttr((string) $formValues['nav_parent']) ?>">

<label for="nav_group">Nav group</label>
<select id="nav_group" name="nav_group">
<option value=""<?= $formValues['nav_group'] === '' ? ' selected' : '' ?>>(unset)</option>
<?php foreach (['primary', 'footer', 'none'] as $navGroupOption): ?>
<option value="<?= eAttr($navGroupOption) ?>"<?= $formValues['nav_group'] === $navGroupOption ? ' selected' : '' ?>><?= e($navGroupOption) ?></option>
<?php endforeach; ?>
</select>

<label for="sitemap_priority">Sitemap priority override (0.0–1.0)</label>
<input type="text" id="sitemap_priority" name="sitemap_priority" value="<?= eAttr((string) $formValues['sitemap_priority']) ?>">

<label for="legal">Legal role</label>
<select id="legal" name="legal">
<option value=""<?= $formValues['legal'] === '' ? ' selected' : '' ?>>(none)</option>
<?php foreach (['impressum', 'privacy'] as $legalOption): ?>
<option value="<?= eAttr($legalOption) ?>"<?= $formValues['legal'] === $legalOption ? ' selected' : '' ?>><?= e($legalOption) ?></option>
<?php endforeach; ?>
</select>
<?php endif; ?>

<label for="body">Body (Markdown)</label>
<textarea id="body" name="body" rows="24" class="admin-body"><?= e((string) $formValues['body']) ?></textarea>

<details class="admin-shortcode-reference">
<summary>Shortcode reference</summary>
<ul>
<li><code>[figure src="/media/..." alt="..." caption="..." width="..." height="..."]</code></li>
<li><code>[video src="/media/..." poster="/media/..."]</code></li>
<li><code>[embed provider="..." id="..."]</code></li>
<li><code>[details summary="..."]...[/details]</code></li>
<li><code>[note type="info|warn|danger"]...[/note]</code></li>
<li><code>[toc]</code></li>
<li><code>[include page="slug"]</code></li>
</ul>
<p class="admin-hint">Inserting media by picking a file is built in T31 (media library) — for now,
type the media path directly.</p>
</details>

<button type="submit">Save</button>
</form>

<?php if ($isEditing): ?>
<form method="post" action="/admin/editor.php" class="admin-move-form">
<h2>Move to another language</h2>
<p class="admin-hint">Slugs are per-language (SPEC §5.4) — moving a document to a new language
always needs a new slug for it.</p>
<input type="hidden" name="csrf_token" value="<?= eAttr($csrf) ?>">
<input type="hidden" name="form_action" value="move">
<input type="hidden" name="identifier" value="<?= eAttr((string) $formValues['identifier']) ?>">

<label for="new_language">New language</label>
<select id="new_language" name="new_language">
<?php foreach ($config->languages as $languageOption): ?>
<?php if ($languageOption === $formValues['language']) {
    continue;
} ?>
<option value="<?= eAttr($languageOption) ?>"><?= e($languageOption) ?></option>
<?php endforeach; ?>
</select>

<label for="new_slug">New slug</label>
<input type="text" id="new_slug" name="new_slug" pattern="[a-z0-9-]{1,96}" required>

<button type="submit">Move</button>
</form>
<?php endif; ?>
</main>
</body>
</html>
