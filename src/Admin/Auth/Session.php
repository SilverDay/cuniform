<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * An authenticated admin session (SPEC §13.1): "30 min idle / 12 h
 * absolute, server-side store in var/". Both windows are enforced by
 * SessionStore against createdAt/lastActivityAt on every find(), not by
 * this value object — it just carries the two timestamps a decision needs.
 */
final class Session
{
    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly int $createdAt,
        public readonly int $lastActivityAt,
    ) {
    }

    public function isExpired(int $now, int $idleSeconds, int $absoluteSeconds): bool
    {
        return ($now - $this->lastActivityAt > $idleSeconds) || ($now - $this->createdAt > $absoluteSeconds);
    }
}
