<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Cuniform\Admin\AdminBootstrap;
use Cuniform\CuniformException;

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
<title>Cuniform admin — redirects</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<main class="admin-page">
<h1><?= e($config->title) ?> — redirects</h1>
<nav class="admin-nav"><?= admin_nav_html() ?></nav>
<p class="admin-hint">Read-only (SPEC §8.3): a document's own <code>aliases</code> field, plus
manual entries from <code>content/redirects.map</code>, compiled the same way a real build
compiles them. Edit either source and re-check here — nothing on this page writes anything.</p>

<?php if ($reportError !== null): ?>
<p class="admin-error" role="alert">
The site currently has an error that would also block a real build, so redirects can't be
shown until it's fixed:
<pre><?= e($reportError) ?></pre>
</p>
<?php elseif ($report !== null): ?>
<?php if ($report->warnings !== []): ?>
<p class="admin-error" role="alert">
<ul>
<?php foreach ($report->warnings as $warning): ?>
<li><?= e($warning) ?></li>
<?php endforeach; ?>
</ul>
</p>
<?php endif; ?>

<?php if ($report->redirects === []): ?>
<p>No redirects yet.</p>
<?php else: ?>
<table class="admin-table">
<thead><tr><th>Old path</th><th>New path</th><th>Source</th></tr></thead>
<tbody>
<?php foreach ($report->redirects as $entry): ?>
<tr>
<td><code><?= e($entry->oldPath) ?></code></td>
<td><code><?= e($entry->newPath) ?></code></td>
<td><?= e($entry->source) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
