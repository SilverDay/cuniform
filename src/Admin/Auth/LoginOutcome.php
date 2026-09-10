<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * The result of one step of LoginService's two-step (password, then
 * TOTP/recovery-code) login flow. Exactly one of pendingLoginId/session/
 * retryAfterSeconds is set, matching which LoginStatus case this is —
 * callers switch on `status`, not on which payload field is non-null.
 */
final class LoginOutcome
{
    private function __construct(
        public readonly LoginStatus $status,
        public readonly ?string $pendingLoginId = null,
        public readonly ?Session $session = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
    }

    public static function pendingTotp(string $pendingLoginId): self
    {
        return new self(LoginStatus::PendingTotp, pendingLoginId: $pendingLoginId);
    }

    public static function authenticated(Session $session): self
    {
        return new self(LoginStatus::Authenticated, session: $session);
    }

    public static function invalidCredentials(): self
    {
        return new self(LoginStatus::InvalidCredentials);
    }

    public static function invalidCode(): self
    {
        return new self(LoginStatus::InvalidCode);
    }

    public static function rateLimited(int $retryAfterSeconds): self
    {
        return new self(LoginStatus::RateLimited, retryAfterSeconds: $retryAfterSeconds);
    }

    public static function expired(): self
    {
        return new self(LoginStatus::Expired);
    }
}
