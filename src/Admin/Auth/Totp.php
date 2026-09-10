<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * TOTP, RFC 6238 (HOTP, RFC 4226, with a 30-second time-step counter).
 * SPEC §13.1: "TOTP (RFC 6238) mandatory — AAL2." HMAC-SHA1 is what RFC 6238
 * itself specifies as the default algorithm (also what every mainstream
 * authenticator app assumes when no algorithm is stated in the otpauth: URI)
 * — a deliberate spec-mandated choice, not a weaker substitute for SHA-256.
 */
final class Totp
{
    private const STEP_SECONDS = 30;
    private const DIGITS       = 6;

    /**
     * @param int|null $timestamp Defaults to now; a fixed value only for tests.
     */
    public function currentCode(TotpSecret $secret, ?int $timestamp = null): string
    {
        return $this->codeForCounter($secret, $this->counterFor($timestamp ?? time()));
    }

    /**
     * Accepts a code from one step before or after the current one (a
     * ±30s allowance for clock drift and human entry delay) — SPEC doesn't
     * name a window, and RFC 6238 §5.2 itself recommends allowing "at most
     * one time step" either side, which this follows directly rather than
     * widening it further.
     *
     * @param int|null $timestamp Defaults to now; a fixed value only for tests.
     */
    public function verify(TotpSecret $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        $code    = trim($code);
        $counter = $this->counterFor($timestamp ?? time());

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeForCounter($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `otpauth://totp/...` URI (the de facto standard authenticator-app
     * enrolment format) so the operator can scan or manually enter the
     * secret without this codebase needing a QR-rendering dependency.
     */
    public function provisioningUri(TotpSecret $secret, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);

        return 'otpauth://totp/' . $label . '?' . http_build_query([
            'secret' => $secret->toBase32(),
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::STEP_SECONDS,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function counterFor(int $timestamp): int
    {
        return intdiv($timestamp, self::STEP_SECONDS);
    }

    private function codeForCounter(TotpSecret $secret, int $counter): string
    {
        $binaryCounter = pack('J', $counter); // 8-byte big-endian, RFC 4226 §5.2
        $hash          = hash_hmac('sha1', $binaryCounter, $secret->raw, true);

        $offset = ord($hash[19]) & 0x0F;
        $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $code = $truncated % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }
}
