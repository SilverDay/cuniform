<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * The one-time output of AccountEnrollment::enroll() — everything the
 * operator must see now and never again (SPEC §13.1: recovery codes are
 * "displayed once, stored hashed"; the TOTP secret is likewise never
 * persisted in recoverable plaintext form outside this response, only as
 * part of the account's stored base32 secret used for verification).
 */
final class EnrollmentResult
{
    /**
     * @param list<string> $recoveryCodes Plaintext, single-use.
     */
    public function __construct(
        public readonly AdminAccount $account,
        public readonly string $totpSecretBase32,
        public readonly string $totpProvisioningUri,
        public readonly array $recoveryCodes,
    ) {
    }
}
