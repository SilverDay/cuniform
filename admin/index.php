<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Cuniform\Admin\AdminBootstrap;
use Cuniform\Admin\Editor\DocumentSummary;
use Cuniform\Admin\Editor\TranslationCoverage;
use Cuniform\Build\BuildOutcome;
use Cuniform\Content\FrontMatter\DocumentStatus;

admin_security_headers();

$context = AdminBootstrap::create(dirname(__DIR__));
$config  = $context->config;

$session = admin_require_session($context->loginService);
$csrf    = admin_csrf_value($context->csrfToken, $context->adminCookie);

$index     = $context->editorDocumentStore->index();
$documents = $index['documents'];
$indexErrors = $index['errors'];

$draftCount = 0;
foreach ($documents as $document) {
    if ($document->status === DocumentStatus::Draft) {
        $draftCount++;
    }
}

// SPEC §12's own framing ("a published German post with a forgotten
// English draft is obvious rather than discovered by a reader") is the
// literal reading applied here: a document counts as untranslated when it
// has no translation in any other configured language — whether because it
// carries no translation_key at all, or because it carries one nothing
// else currently shares.
$coverage = TranslationCoverage::compute($documents);
$untranslatedCount = 0;
foreach ($coverage as $translated) {
    if (!$translated) {
        $untranslatedCount++;
    }
}

$recent = $documents;
usort($recent, static fn (DocumentSummary $a, DocumentSummary $b): int => $b->mtime <=> $a->mtime);
$recent = array_slice($recent, 0, 10);

$latestBuild    = $context->buildLogReader->latest();
$buildPending    = $context->buildRequestQueue->isPending();
$rollbackPending = $context->rollbackRequestQueue->isPending();

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cuniform admin</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<main class="admin-page">
<h1><?= e($config->title) ?> admin</h1>
<nav class="admin-nav"><?= admin_nav_html() ?></nav>
<p>Signed in as <strong><?= e($session->username) ?></strong>.
<form method="post" action="/admin/logout.php" class="admin-inline-form">
<input type="hidden" name="csrf_token" value="<?= eAttr($csrf) ?>">
<button type="submit">Sign out</button>
</form>
</p>

<?php if ($indexErrors !== []): ?>
<p class="admin-error" role="alert">
Some documents could not be read:
<ul>
<?php foreach ($indexErrors as $error): ?>
<li><?= e($error) ?></li>
<?php endforeach; ?>
</ul>
</p>
<?php endif; ?>

<div class="admin-stat-row">
<div class="admin-stat"><span class="admin-stat-value"><?= e((string) count($documents)) ?></span><span class="admin-stat-label">Documents</span></div>
<div class="admin-stat"><span class="admin-stat-value"><?= e((string) $draftCount) ?></span><span class="admin-stat-label">Drafts</span></div>
<div class="admin-stat"><span class="admin-stat-value"><?= e((string) $untranslatedCount) ?></span><span class="admin-stat-label">Untranslated</span></div>
</div>

<h2>Last build</h2>
<?php if ($buildPending): ?>
<p class="admin-notice">A build is currently queued.</p>
<?php endif; ?>
<?php if ($rollbackPending): ?>
<p class="admin-notice">A rollback is currently queued.</p>
<?php endif; ?>
<?php if ($latestBuild === null): ?>
<p>No build has run yet.</p>
<?php else: ?>
<p class="<?= $latestBuild->outcome === BuildOutcome::Success ? 'admin-notice' : 'admin-error' ?>">
<?= e($latestBuild->outcome === BuildOutcome::Success ? 'Succeeded' : 'Failed') ?>
at <?= e($latestBuild->timestamp->format('Y-m-d H:i:s')) ?>
(<?= e(number_format($latestBuild->durationSeconds, 1)) ?>s)
<?php if ($latestBuild->message !== null): ?> — <?= e($latestBuild->message) ?><?php endif; ?>
</p>
<p><a href="/admin/build-log.php">Full build log &rarr;</a></p>
<?php endif; ?>

<h2>Recent documents</h2>
<?php if ($recent === []): ?>
<p>No documents yet.</p>
<?php else: ?>
<table class="admin-table">
<thead>
<tr><th>Language</th><th>Kind</th><th>Title</th><th>Status</th><th>Modified</th></tr>
</thead>
<tbody>
<?php foreach ($recent as $document): ?>
<tr>
<td><?= e($document->language) ?></td>
<td><?= e($document->kind->name) ?></td>
<td><a href="/admin/editor.php?id=<?= eAttr(urlencode($document->identifier)) ?>"><?= e($document->title) ?></a></td>
<td><?= e($document->status->value) ?></td>
<td><?= e(date('Y-m-d H:i', $document->mtime)) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</main>
</body>
</html>
