<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Cuniform\Admin\AdminBootstrap;
use Cuniform\Admin\Auth\AdminCookie;
use Cuniform\Admin\Auth\LoginStatus;

admin_security_headers();

$context      = AdminBootstrap::create(dirname(__DIR__));
$config       = $context->config;
$loginService = $context->loginService;
$adminCookie  = $context->adminCookie;
$csrfToken    = $context->csrfToken;

if (admin_current_session($loginService) !== null) {
    header('Location: /admin/index.php');
    exit;
}

$error = null;
$step  = 'password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid($csrfToken)) {
        $error = 'Your session expired — please try again.';
    } elseif (admin_post_field('step') === 'password') {
        $outcome = $loginService->startLogin(admin_post_field('username'), admin_post_field('password'), admin_client_ip());

        if ($outcome->status === LoginStatus::PendingTotp) {
            header('Set-Cookie: ' . $adminCookie->header(AdminCookie::PENDING_LOGIN_NAME, $outcome->pendingLoginId ?? ''), false);
            $step = 'totp';
        } elseif ($outcome->status === LoginStatus::RateLimited) {
            $error = 'Too many attempts — try again in ' . $outcome->retryAfterSeconds . ' seconds.';
        } else {
            $error = 'Invalid username or password.';
        }
    } elseif (admin_post_field('step') === 'totp') {
        $pendingId = admin_cookie(AdminCookie::PENDING_LOGIN_NAME);

        if ($pendingId === null) {
            $error = 'Your login attempt expired — please start again.';
        } else {
            $outcome = $loginService->completeSecondFactor($pendingId, admin_post_field('code'), admin_client_ip());

            if ($outcome->status === LoginStatus::Authenticated && $outcome->session !== null) {
                header('Set-Cookie: ' . $adminCookie->clear(AdminCookie::PENDING_LOGIN_NAME), false);
                header('Set-Cookie: ' . $adminCookie->header(AdminCookie::SESSION_NAME, $outcome->session->id), false);
                header('Location: /admin/index.php');
                exit;
            }

            if ($outcome->status === LoginStatus::RateLimited) {
                $error = 'Too many attempts — try again in ' . $outcome->retryAfterSeconds . ' seconds.';
                $step  = 'totp';
            } elseif ($outcome->status === LoginStatus::Expired) {
                header('Set-Cookie: ' . $adminCookie->clear(AdminCookie::PENDING_LOGIN_NAME), false);
                $error = 'Your login attempt expired — please start again.';
            } else {
                $error = 'Invalid code.';
                $step  = 'totp';
            }
        }
    }
} elseif (admin_cookie(AdminCookie::PENDING_LOGIN_NAME) !== null) {
    $step = 'totp';
}

$csrf = admin_csrf_value($csrfToken, $adminCookie);

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cuniform admin — sign in</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<main class="admin-auth">
<h1><?= e($config->title) ?> admin</h1>
<?php if ($error !== null): ?>
<p class="admin-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
<?php if ($step === 'password'): ?>
<form method="post" action="/admin/login.php" autocomplete="off">
<input type="hidden" name="step" value="password">
<input type="hidden" name="csrf_token" value="<?= eAttr($csrf) ?>">
<label for="username">Username</label>
<input type="text" id="username" name="username" autocomplete="username" required autofocus>
<label for="password">Password</label>
<input type="password" id="password" name="password" autocomplete="current-password" required>
<button type="submit">Continue</button>
</form>
<?php else: ?>
<form method="post" action="/admin/login.php" autocomplete="off">
<input type="hidden" name="step" value="totp">
<input type="hidden" name="csrf_token" value="<?= eAttr($csrf) ?>">
<label for="code">Authenticator code or recovery code</label>
<input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus>
<button type="submit">Sign in</button>
</form>
<?php endif; ?>
</main>
</body>
</html>
