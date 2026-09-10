<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

use Cuniform\Admin\Auth\AdminCookie;
use Cuniform\Admin\Auth\CsrfToken;
use Cuniform\Admin\Auth\LoginService;
use Cuniform\Admin\Auth\Session;

/**
 * Request-scoped helpers shared by every admin/*.php entry point (SPEC
 * §13). Service wiring itself lives in Cuniform\Admin\AdminBootstrap, not
 * here — see that class's docblock for why (PHPStan can't follow variables
 * a `require` injects into the caller's scope). Every helper below takes
 * its dependencies as parameters for the same reason: nothing here reads
 * an ambient variable, only superglobals, which is the normal, expected
 * thing for admin (P2) request-handling code to do — unlike templates/,
 * which php-style.md bars from touching superglobals at all.
 */

/**
 * SPEC §14.1/§14.2's site-wide headers, applied here too. Admin responses
 * are dynamic (unlike the public site), so none of these pages need the
 * hashed-inline-script workaround the public CSP relies on — they ship no
 * script at all, inline or external, so `script-src 'none'` is exact
 * rather than a placeholder.
 */
function admin_security_headers(): void
{
    header(
        "Content-Security-Policy: default-src 'self'; script-src 'none'; "
        . "style-src 'self'; img-src 'self'; font-src 'self'; connect-src 'self'; "
        . "frame-ancestors 'none'; form-action 'self'; base-uri 'none'; object-src 'none'"
    );
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

function admin_client_ip(): string
{
    $address = $_SERVER['REMOTE_ADDR'] ?? null;

    return is_string($address) && $address !== '' ? $address : '0.0.0.0';
}

function admin_post_field(string $name): string
{
    $value = $_POST[$name] ?? null;

    return is_string($value) ? $value : '';
}

function admin_post_field_or_null(string $name): ?string
{
    $value = admin_post_field($name);

    return $value === '' ? null : $value;
}

function admin_post_checked(string $name): bool
{
    return isset($_POST[$name]);
}

/**
 * A textarea's contents split into one entry per non-blank line — used for
 * `aliases` (SPEC §5.2, one alias per line).
 *
 * @return list<string>
 */
function admin_post_lines(string $name): array
{
    $lines = preg_split('/\R/', admin_post_field($name)) ?: [];

    return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
}

/**
 * A comma-separated field split into a trimmed list — used for `tags`
 * (SPEC §5.3).
 *
 * @return list<string>
 */
function admin_post_csv(string $name): array
{
    $items = explode(',', admin_post_field($name));

    return array_values(array_filter(array_map('trim', $items), static fn (string $item): bool => $item !== ''));
}

function admin_cookie(string $name): ?string
{
    $value = $_COOKIE[$name] ?? null;

    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * Null when there is no session cookie, or the session it names doesn't
 * exist or has expired (SessionStore already enforces the idle/absolute
 * windows — this just surfaces "no session" either way).
 */
function admin_current_session(LoginService $loginService): ?Session
{
    $sessionId = admin_cookie(AdminCookie::SESSION_NAME);

    return $sessionId === null ? null : $loginService->validateSession($sessionId);
}

/**
 * Every protected admin page (index.php today; the editor/media/dashboard
 * pages later tasks add) calls this first — redirects to login and exits
 * when there is no valid session.
 */
function admin_require_session(LoginService $loginService): Session
{
    $session = admin_current_session($loginService);
    if ($session === null) {
        header('Location: /admin/login.php');
        exit;
    }

    return $session;
}

/**
 * Issues (or reuses) the CSRF double-submit cookie for the current
 * request and returns its value — the caller embeds this in a hidden
 * form field (CsrfToken's own docblock explains the double-submit choice).
 */
function admin_csrf_value(CsrfToken $csrfToken, AdminCookie $adminCookie): string
{
    $existing = admin_cookie(AdminCookie::CSRF_NAME);
    if ($existing !== null) {
        return $existing;
    }

    $token = $csrfToken->issue();
    header('Set-Cookie: ' . $adminCookie->header(AdminCookie::CSRF_NAME, $token), false);

    return $token;
}

function admin_csrf_valid(CsrfToken $csrfToken): bool
{
    return $csrfToken->matches(admin_cookie(AdminCookie::CSRF_NAME), admin_post_field('csrf_token'));
}
