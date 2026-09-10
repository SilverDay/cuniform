<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * SPEC §13.1's "breached-password check on set" (NIST SP 800-63B). An
 * interface rather than one fixed implementation: see
 * DenylistBreachedPasswordChecker's own docblock for the judgment call on
 * what backs the default implementation.
 */
interface BreachedPasswordChecker
{
    public function isBreached(string $password): bool;
}
