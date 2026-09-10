<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Cuniform\Admin\AdminBootstrap;
use Cuniform\Admin\Auth\AdminCookie;

admin_security_headers();

$context      = AdminBootstrap::create(dirname(__DIR__));
$loginService = $context->loginService;
$adminCookie  = $context->adminCookie;
$csrfToken    = $context->csrfToken;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_csrf_valid($csrfToken)) {
    header('Location: /admin/index.php');
    exit;
}

$sessionId = admin_cookie(AdminCookie::SESSION_NAME);
if ($sessionId !== null) {
    $loginService->logout($sessionId);
}

header('Set-Cookie: ' . $adminCookie->clear(AdminCookie::SESSION_NAME), false);
header('Location: /admin/login.php');
