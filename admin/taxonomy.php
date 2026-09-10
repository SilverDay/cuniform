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
<title>Cuniform admin — tags &amp; series</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<main class="admin-page">
<h1><?= e($config->title) ?> — tags &amp; series</h1>
<nav class="admin-nav"><?= admin_nav_html() ?></nav>
<p class="admin-hint">Tags and series are language-scoped (SPEC §7.10) — the same word in two
languages is two different archives. Counts reflect published posts only, same as a real
build.</p>

<?php if ($reportError !== null): ?>
<p class="admin-error" role="alert">
The site currently has an error that would also block a real build, so this can't be shown
until it's fixed:
<pre><?= e($reportError) ?></pre>
</p>
<?php elseif ($report !== null): ?>
<?php foreach ($config->languages as $language): ?>
<h2><?= e($language) ?></h2>

<h3>Tags</h3>
<?php $tags = $report->listing->tagsByLanguage[$language] ?? []; ?>
<?php if ($tags === []): ?>
<p>No tags.</p>
<?php else: ?>
<table class="admin-table">
<thead><tr><th>Tag</th><th>Posts</th></tr></thead>
<tbody>
<?php foreach ($tags as $tag): ?>
<tr><td><?= e($tag->label) ?></td><td><?= e((string) count($tag->posts)) ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

<h3>Series</h3>
<?php $series = $report->listing->seriesByLanguage[$language] ?? []; ?>
<?php if ($series === []): ?>
<p>No series.</p>
<?php else: ?>
<table class="admin-table">
<thead><tr><th>Series</th><th>Posts</th></tr></thead>
<tbody>
<?php foreach ($series as $one): ?>
<tr><td><?= e($one->label) ?></td><td><?= e((string) count($one->posts)) ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
</main>
</body>
</html>
