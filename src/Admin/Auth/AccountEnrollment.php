<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

use Cuniform\Admin\AdminException;

/**
 * Bootstraps an admin account (SPEC §13.1). There is no self-registration
 * screen — this is a single-operator site (§13 P2 scope) — so account
 * creation is a CLI-only operation (`bin/cuniform admin-create-account`),
 * the same "operator runs a command on the server" model T27's
 * `setup-public` already established, not a web form.
 */
final class AccountEnrollment
{
    public function __construct(
        private readonly AdminAccountStore $accounts,
        private readonly PasswordPolicy $passwordPolicy = new PasswordPolicy(),
        private readonly PasswordHasher $passwordHasher = new PasswordHasher(),
        private readonly RecoveryCodes $recoveryCodes = new RecoveryCodes(),
        private readonly Totp $totp = new Totp(),
        private readonly string $issuer = 'Cuniform',
    ) {
    }

    /**
     * @throws AdminException When the account already exists or the
     *                        password fails PasswordPolicy.
     */
    public function enroll(string $username, string $password): EnrollmentResult
    {
        if ($this->accounts->find($username) !== null) {
            throw AdminException::accountAlreadyExists($username);
        }

        $errors = $this->passwordPolicy->validate($password);
        if ($errors !== []) {
            throw AdminException::weakPassword($errors);
        }

        $totpSecret    = TotpSecret::generate();
        $plaintextCodes = $this->recoveryCodes->generate();
        $recoveryHashes = array_map(
            fn (string $code): string => $this->recoveryCodes->hash($code),
            $plaintextCodes,
        );

        $account = new AdminAccount(
            $username,
            $this->passwordHasher->hash($password),
            $totpSecret->toBase32(),
            $recoveryHashes,
            gmdate('c'),
        );

        $this->accounts->save($account);

        return new EnrollmentResult(
            $account,
            $totpSecret->toBase32(),
            $this->totp->provisioningUri($totpSecret, $username, $this->issuer),
            $plaintextCodes,
        );
    }
}
