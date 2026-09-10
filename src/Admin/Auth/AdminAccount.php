<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * A single admin operator account (SPEC §13.1). Readonly value object
 * (php-style.md) — updates (password change, recovery-code consumption)
 * go through the `with*()` methods and are persisted by AdminAccountStore.
 */
final class AdminAccount
{
    /**
     * @param list<string> $recoveryCodeHashes SHA-256 hashes (RecoveryCodes::hash()); consumed entries are removed.
     */
    public function __construct(
        public readonly string $username,
        public readonly string $passwordHash,
        public readonly string $totpSecretBase32,
        public readonly array $recoveryCodeHashes,
        public readonly string $createdAt,
    ) {
    }

    public function withPasswordHash(string $passwordHash): self
    {
        return new self($this->username, $passwordHash, $this->totpSecretBase32, $this->recoveryCodeHashes, $this->createdAt);
    }

    /**
     * @param list<string> $recoveryCodeHashes
     */
    public function withRecoveryCodeHashes(array $recoveryCodeHashes): self
    {
        return new self($this->username, $this->passwordHash, $this->totpSecretBase32, $recoveryCodeHashes, $this->createdAt);
    }
}
