<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Cuniform\Admin\AdminBootstrap;

admin_security_headers();

$context      = AdminBootstrap::create(dirname(__DIR__));
$config       = $context->config;
$loginService = $context->loginService;
$adminCookie  = $context->adminCookie;
$csrfToken    = $context->csrfToken;

$session = admin_require_session($loginService);
$csrf    = admin_csrf_value($csrfToken, $adminCookie);

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
<main class="admin-auth">
<h1><?= e($config->title) ?> admin</h1>
<p>Signed in as <strong><?= e($session->username) ?></strong>.</p>
<p>The dashboard (SPEC §13.3 — recent documents, build status, draft count) isn't built yet
(BUILD-ORDER.md T33). This page confirms the login flow (T28) works end to end.</p>
<form method="post" action="/admin/logout.php">
<input type="hidden" name="csrf_token" value="<?= eAttr($csrf) ?>">
<button type="submit">Sign out</button>
</form>
</main>
</body>
</html>
