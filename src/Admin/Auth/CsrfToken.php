<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * CSRF synchronizer token (SPEC §13.2: "CSRF synchronizer token on every
 * state-changing request"), double-submit-cookie style: the same random
 * value is set as an AdminCookie::CSRF_NAME cookie and rendered into a
 * hidden form field by the same response, and a state-changing request is
 * rejected unless both match. This works even for the login form, where no
 * session yet exists to hold a synchronizer token against — the classic
 * synchronizer pattern (a token stored server-side keyed to the session)
 * has nothing to key to before authentication, so double-submit is used
 * uniformly for every admin form rather than switching mechanisms between
 * the login form and everything after it.
 */
final class CsrfToken
{
    public function issue(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function matches(?string $cookieValue, ?string $submittedValue): bool
    {
        if ($cookieValue === null || $submittedValue === null || $cookieValue === '' || $submittedValue === '') {
            return false;
        }

        return hash_equals($cookieValue, $submittedValue);
    }
}
