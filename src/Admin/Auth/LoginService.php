<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * Orchestrates SPEC §13.1's login flow end to end: rate limiting, password
 * verification, the TOTP/recovery-code second factor (AAL2 — TOTP is
 * "mandatory", not optional), and session issuance. `admin/login.php` is a
 * thin caller: it reads superglobals, calls one of these two methods, and
 * translates the LoginOutcome into a redirect/cookie/error page. All the
 * actual decision logic lives here so it can be unit tested without a real
 * HTTP request.
 */
final class LoginService
{
    public function __construct(
        private readonly AdminAccountStore $accounts,
        private readonly PendingLoginStore $pendingLogins,
        private readonly SessionStore $sessions,
        private readonly RateLimiter $rateLimiter,
        private readonly PasswordHasher $passwordHasher = new PasswordHasher(),
        private readonly Totp $totp = new Totp(),
        private readonly RecoveryCodes $recoveryCodes = new RecoveryCodes(),
    ) {
    }

    /**
     * Step 1: username + password. Never reveals whether the username
     * exists — an unknown account still runs a real Argon2id verify
     * against a freshly generated hash, so a timing difference doesn't leak
     * which usernames are valid, and the outcome (InvalidCredentials) is
     * identical either way.
     */
    public function startLogin(string $username, string $password, string $sourceIp): LoginOutcome
    {
        $accountKey = $this->accountKey($username);
        $ipKey      = $this->ipKey($sourceIp);

        $retryAfter = max($this->rateLimiter->retryAfterSeconds($accountKey), $this->rateLimiter->retryAfterSeconds($ipKey));
        if ($retryAfter > 0) {
            return LoginOutcome::rateLimited($retryAfter);
        }

        $account      = $this->accounts->find($username);
        $passwordHash = $account !== null ? $account->passwordHash : $this->passwordHasher->hash(bin2hex(random_bytes(16)));
        $passwordOk   = $this->passwordHasher->verify($password, $passwordHash);

        if ($account === null || !$passwordOk) {
            $this->rateLimiter->recordFailure($accountKey);
            $this->rateLimiter->recordFailure($ipKey);

            return LoginOutcome::invalidCredentials();
        }

        return LoginOutcome::pendingTotp($this->pendingLogins->create($username)->id);
    }

    /**
     * Step 2: a 6-digit TOTP code, or one of the account's recovery codes
     * (SPEC §13.1's recovery path). A matched recovery code is consumed
     * (removed from the account) immediately — single-use, per spec.
     */
    public function completeSecondFactor(string $pendingLoginId, string $code, string $sourceIp): LoginOutcome
    {
        $pending = $this->pendingLogins->find($pendingLoginId);
        if ($pending === null) {
            return LoginOutcome::expired();
        }

        $accountKey = $this->accountKey($pending->username);
        $ipKey      = $this->ipKey($sourceIp);

        $retryAfter = max($this->rateLimiter->retryAfterSeconds($accountKey), $this->rateLimiter->retryAfterSeconds($ipKey));
        if ($retryAfter > 0) {
            return LoginOutcome::rateLimited($retryAfter);
        }

        $account = $this->accounts->find($pending->username);
        if ($account === null) {
            $this->pendingLogins->delete($pendingLoginId);

            return LoginOutcome::invalidCode();
        }

        $consumedRecoveryHash = $this->matchAndFactor($account, $code);
        if ($consumedRecoveryHash === false) {
            $this->rateLimiter->recordFailure($accountKey);
            $this->rateLimiter->recordFailure($ipKey);

            return LoginOutcome::invalidCode();
        }

        if ($consumedRecoveryHash !== null) {
            $remaining = array_values(array_filter(
                $account->recoveryCodeHashes,
                static fn (string $hash): bool => $hash !== $consumedRecoveryHash,
            ));
            $this->accounts->save($account->withRecoveryCodeHashes($remaining));
        }

        $this->rateLimiter->recordSuccess($accountKey);
        $this->rateLimiter->recordSuccess($ipKey);
        $this->pendingLogins->delete($pendingLoginId);

        return LoginOutcome::authenticated($this->sessions->create($pending->username));
    }

    public function validateSession(string $sessionId): ?Session
    {
        return $this->sessions->find($sessionId);
    }

    public function logout(string $sessionId): void
    {
        $this->sessions->destroy($sessionId);
    }

    /**
     * @return string|null|false The consumed recovery-code hash if a
     *                            recovery code matched, null if the TOTP
     *                            code matched (nothing to consume), or
     *                            false if neither matched.
     */
    private function matchAndFactor(AdminAccount $account, string $code): string|null|false
    {
        if ($this->totp->verify(TotpSecret::fromBase32($account->totpSecretBase32), $code)) {
            return null;
        }

        foreach ($account->recoveryCodeHashes as $hash) {
            if ($this->recoveryCodes->verify($code, $hash)) {
                return $hash;
            }
        }

        return false;
    }

    private function accountKey(string $username): string
    {
        return 'account:' . $username;
    }

    private function ipKey(string $sourceIp): string
    {
        return 'ip:' . $sourceIp;
    }
}
