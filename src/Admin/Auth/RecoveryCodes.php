<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * SPEC §13.1: "single-use recovery codes generated at enrolment, displayed
 * once, stored hashed." Each code is 10 random bytes (80 bits) rendered as
 * two 8-character base32 groups — high enough entropy that a fast hash
 * (SHA-256) is appropriate for the stored form; unlike a user-chosen
 * password, there is no dictionary to guard against, only brute force
 * against 80 bits, so Argon2id's deliberate slowness buys nothing here and
 * would only make bulk verification (10 stored hashes to check per attempt)
 * needlessly expensive.
 */
final class RecoveryCodes
{
    private const CODE_BYTES = 10;

    public function __construct(private readonly Base32 $base32 = new Base32())
    {
    }

    /**
     * @return list<string> Plaintext codes, formatted "XXXXXXXX-XXXXXXXX".
     *                       The caller shows these exactly once and never
     *                       persists the plaintext.
     */
    public function generate(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw    = $this->base32->encode(random_bytes(self::CODE_BYTES));
            $codes[] = substr($raw, 0, 8) . '-' . substr($raw, 8, 8);
        }

        return $codes;
    }

    public function hash(string $plaintextCode): string
    {
        return hash('sha256', $this->normalize($plaintextCode));
    }

    public function verify(string $plaintextCode, string $hash): bool
    {
        return hash_equals($hash, $this->hash($plaintextCode));
    }

    private function normalize(string $code): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim($code)));
    }
}
