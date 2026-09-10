<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Cuniform\Admin\AdminBootstrap;
use Cuniform\Admin\Editor\DocumentSummary;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\CuniformException;
use Cuniform\Template\NavItem;

/**
 * @param list<NavItem> $items
 */
function pages_render_nav_tree(array $items): void
{
    if ($items === []) {
        return;
    }

    echo '<ul>';
    foreach ($items as $item) {
        echo '<li><a href="' . eAttr($item->url) . '">' . e($item->label) . '</a>';
        pages_render_nav_tree($item->children);
        echo '</li>';
    }
    echo '</ul>';
}

admin_security_headers();

$context = AdminBootstrap::create(dirname(__DIR__));
$config  = $context->config;

admin_require_session($context->loginService);

$reportError = null;
$report      = null;

try {
    $report = $context->siteReport->build();
} catch (CuniformException $e) {
    $reportError = $e->getMessage();
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cuniform admin — pages</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<main class="admin-page">
<h1><?= e($config->title) ?> — pages</h1>
<nav class="admin-nav"><?= admin_nav_html() ?></nav>
<p class="admin-hint">The nav trees below show what a real build would currently produce
(SPEC §6.2/§6.3) — a page only appears once it has <code>nav_order</code> set; drafts and
not-yet-due scheduled pages are excluded, same as a real build.</p>

<?php if ($reportError !== null): ?>
<p class="admin-error" role="alert">
The site currently has an error that would also block a real build, so nav trees can't be
shown until it's fixed:
<pre><?= e($reportError) ?></pre>
</p>
<?php elseif ($report !== null): ?>
<?php foreach ($config->languages as $language): ?>
<h2><?= e($language) ?></h2>
<?php $nav = $report->navByLanguage[$language] ?? ['primary' => [], 'footer' => []]; ?>
<h3>Primary nav</h3>
<?php if ($nav['primary'] === []): ?>
<p>No pages in the primary nav.</p>
<?php else: ?>
<?php pages_render_nav_tree($nav['primary']); ?>
<?php endif; ?>
<h3>Footer nav</h3>
<?php if ($nav['footer'] === []): ?>
<p>No pages in the footer nav.</p>
<?php else: ?>
<?php pages_render_nav_tree($nav['footer']); ?>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>

<h2>All pages</h2>
<?php
$index = $context->editorDocumentStore->index();
$pages = array_values(array_filter($index['documents'], static fn (DocumentSummary $d): bool => $d->kind === DocumentKind::Page));
?>
<?php if ($pages === []): ?>
<p>No pages yet.</p>
<?php else: ?>
<table class="admin-table">
<thead>
<tr><th>Language</th><th>Title</th><th>Slug</th><th>Status</th></tr>
</thead>
<tbody>
<?php foreach ($pages as $page): ?>
<tr>
<td><?= e($page->language) ?></td>
<td><a href="/admin/editor.php?id=<?= eAttr(urlencode($page->identifier)) ?>"><?= e($page->title) ?></a></td>
<td><?= e($page->slug) ?></td>
<td><?= e($page->status->value) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</main>
</body>
</html>
