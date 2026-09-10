<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * Argon2id password hashing (SPEC §13.1), memory cost >= 64 MiB. PHP's
 * `PASSWORD_ARGON2ID` is used directly rather than a hand-rolled KDF —
 * "no runtime dependencies" (CLAUDE.md) means no third-party library, not
 * "no PHP extension"; `ext-sodium`/`ext-argon2`'s Argon2id support is part
 * of PHP itself (bundled since 7.2/7.3), the same category as `ext-intl`
 * (SPEC §7.9) rather than a Composer package.
 */
final class PasswordHasher
{
    private const MEMORY_COST_KIB = 65536; // 64 MiB, SPEC §13.1's own floor

    private const OPTIONS = [
        'memory_cost' => self::MEMORY_COST_KIB,
        'time_cost'   => 3,
        'threads'     => 1,
    ];

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, self::OPTIONS);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * True when a stored hash predates a policy change (e.g. this class's
     * own OPTIONS were tightened after the hash was created) and should be
     * replaced on next successful login.
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPTIONS);
    }
}
