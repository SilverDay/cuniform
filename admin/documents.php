<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Cuniform\Admin\AdminBootstrap;
use Cuniform\Admin\Editor\DocumentSummary;
use Cuniform\Admin\Editor\TranslationCoverage;
use Cuniform\Content\FrontMatter\DocumentKind;

admin_security_headers();

$context = AdminBootstrap::create(dirname(__DIR__));
$config  = $context->config;

$session = admin_require_session($context->loginService);

$index     = $context->editorDocumentStore->index();
$documents = $index['documents'];
$errors    = $index['errors'];
$coverage  = TranslationCoverage::compute($documents);

$languageFilter    = $_GET['language'] ?? '';
$languageFilter    = is_string($languageFilter) && in_array($languageFilter, $config->languages, true) ? $languageFilter : '';
$kindFilter        = $_GET['kind'] ?? '';
$kindFilter        = is_string($kindFilter) && in_array($kindFilter, ['post', 'page'], true) ? $kindFilter : '';
$translationFilter = $_GET['translation'] ?? '';
$translationFilter = is_string($translationFilter) && in_array($translationFilter, ['translated', 'untranslated'], true) ? $translationFilter : '';

$filtered = array_values(array_filter($documents, static function (DocumentSummary $document) use ($languageFilter, $kindFilter, $translationFilter, $coverage): bool {
    if ($languageFilter !== '' && $document->language !== $languageFilter) {
        return false;
    }

    $kindValue = $document->kind === DocumentKind::Post ? 'post' : 'page';
    if ($kindFilter !== '' && $kindValue !== $kindFilter) {
        return false;
    }

    if ($translationFilter === 'translated' && !($coverage[$document->identifier] ?? false)) {
        return false;
    }

    if ($translationFilter === 'untranslated' && ($coverage[$document->identifier] ?? false)) {
        return false;
    }

    return true;
}));

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cuniform admin — documents</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<main class="admin-page">
<h1><?= e($config->title) ?> — documents</h1>
<nav class="admin-nav"><?= admin_nav_html() ?></nav>

<p class="admin-new-links">
<?php foreach ($config->languages as $language): ?>
<a href="/admin/editor.php?new=1&amp;kind=post&amp;lang=<?= eAttr($language) ?>">New post (<?= e($language) ?>)</a>
&middot;
<a href="/admin/editor.php?new=1&amp;kind=page&amp;lang=<?= eAttr($language) ?>">New page (<?= e($language) ?>)</a>
<?php if ($language !== $config->languages[array_key_last($config->languages)]): ?> &middot; <?php endif; ?>
<?php endforeach; ?>
</p>

<form method="get" action="/admin/documents.php" class="admin-filter-form">
<label for="language">Language</label>
<select id="language" name="language">
<option value="">All</option>
<?php foreach ($config->languages as $language): ?>
<option value="<?= eAttr($language) ?>"<?= $languageFilter === $language ? ' selected' : '' ?>><?= e($language) ?></option>
<?php endforeach; ?>
</select>

<label for="kind">Kind</label>
<select id="kind" name="kind">
<option value="">All</option>
<option value="post"<?= $kindFilter === 'post' ? ' selected' : '' ?>>Post</option>
<option value="page"<?= $kindFilter === 'page' ? ' selected' : '' ?>>Page</option>
</select>

<label for="translation">Translation status</label>
<select id="translation" name="translation">
<option value="">All</option>
<option value="translated"<?= $translationFilter === 'translated' ? ' selected' : '' ?>>Translated</option>
<option value="untranslated"<?= $translationFilter === 'untranslated' ? ' selected' : '' ?>>Untranslated</option>
</select>

<button type="submit">Filter</button>
<?php if ($languageFilter !== '' || $kindFilter !== '' || $translationFilter !== ''): ?>
<a href="/admin/documents.php">Clear</a>
<?php endif; ?>
</form>

<?php if ($errors !== []): ?>
<p class="admin-error" role="alert">
Some documents could not be listed — their front matter has errors:
<ul>
<?php foreach ($errors as $error): ?>
<li><?= e($error) ?></li>
<?php endforeach; ?>
</ul>
</p>
<?php endif; ?>

<?php if ($filtered === []): ?>
<p><?= $documents === [] ? 'No documents yet.' : 'No documents match this filter.' ?></p>
<?php else: ?>
<table class="admin-table">
<thead>
<tr><th>Language</th><th>Kind</th><th>Title</th><th>Slug</th><th>Status</th><th>Translated</th></tr>
</thead>
<tbody>
<?php foreach ($filtered as $document): ?>
<tr>
<td><?= e($document->language) ?></td>
<td><?= e($document->kind->name) ?></td>
<td><a href="/admin/editor.php?id=<?= eAttr(urlencode($document->identifier)) ?>"><?= e($document->title) ?></a></td>
<td><?= e($document->slug) ?></td>
<td><?= e($document->status->value) ?></td>
<td><?= e(($coverage[$document->identifier] ?? false) ? 'Yes' : 'No') ?><?= $document->translationKey !== null ? ' (' . e($document->translationKey) . ')' : '' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</main>
</body>
</html>
