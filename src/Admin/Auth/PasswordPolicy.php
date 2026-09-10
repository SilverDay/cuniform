<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * SPEC §13.1 / NIST SP 800-63B baseline: >= 12 characters, breached-password
 * check on set, no composition rules, no forced rotation. "No composition
 * rules" is enforced by absence — this class deliberately has no checks for
 * uppercase/digit/symbol content; PasswordPolicyTest asserts a plain
 * 12-character lowercase phrase validates, which is what proves that
 * absence rather than just not noticing it's missing. "No forced rotation"
 * has nothing to implement — it's the absence of an expiry check anywhere
 * in this codebase.
 */
final class PasswordPolicy
{
    private const MIN_LENGTH = 12;

    public function __construct(private readonly BreachedPasswordChecker $breachedPasswordChecker = new DenylistBreachedPasswordChecker())
    {
    }

    /**
     * @return list<string> Empty when the password is acceptable.
     */
    public function validate(string $password): array
    {
        $errors = [];

        if (mb_strlen($password, 'UTF-8') < self::MIN_LENGTH) {
            $errors[] = 'must be at least ' . self::MIN_LENGTH . ' characters (NIST SP 800-63B, SPEC §13.1)';
        }

        if ($this->breachedPasswordChecker->isBreached($password)) {
            $errors[] = 'appears on a list of common/breached passwords (SPEC §13.1) — choose a different one';
        }

        return $errors;
    }
}
