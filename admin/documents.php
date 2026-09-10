<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Cuniform\Admin\AdminBootstrap;

admin_security_headers();

$context = AdminBootstrap::create(dirname(__DIR__));
$config  = $context->config;

$session = admin_require_session($context->loginService);

$index     = $context->editorDocumentStore->index();
$documents = $index['documents'];
$errors    = $index['errors'];

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
<p><a href="/admin/index.php">&larr; Dashboard</a></p>

<p class="admin-new-links">
<?php foreach ($config->languages as $language): ?>
<a href="/admin/editor.php?new=1&amp;kind=post&amp;lang=<?= eAttr($language) ?>">New post (<?= e($language) ?>)</a>
&middot;
<a href="/admin/editor.php?new=1&amp;kind=page&amp;lang=<?= eAttr($language) ?>">New page (<?= e($language) ?>)</a>
<?php if ($language !== $config->languages[array_key_last($config->languages)]): ?> &middot; <?php endif; ?>
<?php endforeach; ?>
</p>

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

<?php if ($documents === []): ?>
<p>No documents yet.</p>
<?php else: ?>
<table class="admin-table">
<thead>
<tr><th>Language</th><th>Kind</th><th>Title</th><th>Slug</th><th>Status</th><th>Translation key</th></tr>
</thead>
<tbody>
<?php foreach ($documents as $document): ?>
<tr>
<td><?= e($document->language) ?></td>
<td><?= e($document->kind->name) ?></td>
<td><a href="/admin/editor.php?id=<?= eAttr(urlencode($document->identifier)) ?>"><?= e($document->title) ?></a></td>
<td><?= e($document->slug) ?></td>
<td><?= e($document->status->value) ?></td>
<td><?= e($document->translationKey ?? '—') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</main>
</body>
</html>
