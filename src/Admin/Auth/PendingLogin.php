<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * The interim state between a correct password and a completed login — AAL2
 * (SPEC §13.1) is a two-step process, and this record is what step 2 (the
 * TOTP/recovery-code form) is submitted against. Never authenticates
 * anything on its own; LoginService only ever turns one into a Session
 * after TOTP/recovery verification succeeds.
 */
final class PendingLogin
{
    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly int $createdAt,
    ) {
    }

    public function isExpired(int $now, int $ttlSeconds): bool
    {
        return $now - $this->createdAt > $ttlSeconds;
    }
}
