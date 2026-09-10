<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Cuniform\Admin\AdminBootstrap;
use Cuniform\Build\BuildOutcome;

/**
 * @param array<string, int> $counts
 */
function build_log_document_count_label(array $counts): string
{
    $parts = [];
    foreach ($counts as $language => $count) {
        $parts[] = "{$language}: {$count}";
    }

    return implode(', ', $parts);
}

admin_security_headers();

$context = AdminBootstrap::create(dirname(__DIR__));
$config  = $context->config;

admin_require_session($context->loginService);
$csrf = admin_csrf_value($context->csrfToken, $context->adminCookie);

$rollbackErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid($context->csrfToken)) {
        $rollbackErrors = ['Your session expired — please try again.'];
    } else {
        $context->rollbackRequestQueue->enqueue();
        header('Location: /admin/build-log.php?rollback=1');
        exit;
    }
}

$rollbackQueued = isset($_GET['rollback']);

$entries         = $context->buildLogReader->recent(50);
$buildPending    = $context->buildRequestQueue->isPending();
$rollbackPending = $context->rollbackRequestQueue->isPending();

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cuniform admin — build log</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<main class="admin-page">
<h1><?= e($config->title) ?> — build log</h1>
<nav class="admin-nav"><?= admin_nav_html() ?></nav>

<?php if ($rollbackQueued): ?>
<p class="admin-notice">Rollback queued — a systemd path unit will run it as the build user
(SPEC §10.5). This page doesn't poll; reload to check progress.</p>
<?php endif; ?>

<?php if ($rollbackErrors !== []): ?>
<p class="admin-error" role="alert"><?= e($rollbackErrors[0]) ?></p>
<?php endif; ?>

<?php if ($buildPending): ?>
<p class="admin-notice">A build is currently queued.</p>
<?php endif; ?>

<p class="admin-hint">Rolling back re-points <code>public</code> at the previous release
(SPEC §10.4) — it does not undo any content change, and it never runs directly in this
process (§10.5/§15.1): this button only enqueues a request, the same privilege-separated way
a publish enqueues a build.</p>

<form method="post" action="/admin/build-log.php" class="admin-inline-form">
<input type="hidden" name="csrf_token" value="<?= eAttr($csrf) ?>">
<?php if ($rollbackPending): ?>
<button type="submit" disabled>Rollback already queued</button>
<?php else: ?>
<button type="submit">Queue a rollback</button>
<?php endif; ?>
</form>

<?php if ($entries === []): ?>
<p>No builds recorded yet.</p>
<?php else: ?>
<table class="admin-table">
<thead>
<tr><th>When</th><th>Outcome</th><th>Duration</th><th>Documents</th><th>Routes</th><th>Message</th></tr>
</thead>
<tbody>
<?php foreach ($entries as $entry): ?>
<tr>
<td><?= e($entry->timestamp->format('Y-m-d H:i:s')) ?></td>
<td><?= e($entry->outcome === BuildOutcome::Success ? 'Success' : 'Failed') ?></td>
<td><?= e(number_format($entry->durationSeconds, 1)) ?>s</td>
<td><?= e(build_log_document_count_label($entry->documentCountByLanguage)) ?></td>
<td><?= e((string) $entry->routeCount) ?></td>
<td><?= e($entry->message ?? '—') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</main>
</body>
</html>
