<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * A TOTP shared secret (RFC 6238 via RFC 4226), held as raw bytes and
 * exchanged as base32 text — the format an authenticator app's manual-entry
 * field expects. 20 bytes (160 bits) matches RFC 4226's own recommended
 * secret length for HMAC-SHA1.
 */
final class TotpSecret
{
    private const DEFAULT_BYTES = 20;

    private function __construct(public readonly string $raw)
    {
    }

    public static function generate(int $bytes = self::DEFAULT_BYTES): self
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException('$bytes must be >= 1');
        }

        return new self(random_bytes($bytes));
    }

    public static function fromBase32(string $encoded): self
    {
        return new self((new Base32())->decode($encoded));
    }

    public function toBase32(): string
    {
        return (new Base32())->encode($this->raw);
    }
}
