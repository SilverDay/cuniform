<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * Builds `Set-Cookie` header values for the admin app's three cookies
 * (session, pending-login, CSRF token) with the exact attributes SPEC
 * §13.1 requires: `Secure`, `HttpOnly`, `SameSite=Strict`, `__Secure-`
 * prefix, `Path=/admin`.
 *
 * `__Host-` is deliberately not used — it mandates `Path=/`, which SPEC
 * itself rules out ("`Path=/admin` is tidiness only — it does not isolate
 * the cookie from same-origin script", §13.1, §3.4).
 *
 * No `Max-Age`/`Expires` is set on any of these — all three are ordinary
 * session cookies (cleared when the browser closes). The server-side store
 * is what actually enforces the 30-min-idle/12h-absolute/5-min-pending
 * windows (SessionStore, PendingLoginStore); a cookie lifetime here would
 * only be a second, redundant expiry to keep in sync with the first.
 */
final class AdminCookie
{
    public const SESSION_NAME       = '__Secure-cuniform_admin';
    public const PENDING_LOGIN_NAME = '__Secure-cuniform_admin_pending';
    public const CSRF_NAME          = '__Secure-cuniform_admin_csrf';

    public function header(string $name, string $value): string
    {
        return "{$name}=" . rawurlencode($value) . '; Path=/admin; Secure; HttpOnly; SameSite=Strict';
    }

    /**
     * Immediately-expiring cookie, used on logout — a real deletion
     * requires the browser to see an `Expires` in the past, not merely the
     * absence of one.
     */
    public function clear(string $name): string
    {
        return "{$name}=; Path=/admin; Secure; HttpOnly; SameSite=Strict; Expires=Thu, 01 Jan 1970 00:00:00 GMT";
    }
}
