<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

use InvalidArgumentException;

/**
 * RFC 4648 base32 — no runtime dependency provides this, and TOTP secrets
 * (RFC 6238 via RFC 4226) are conventionally exchanged as base32 text (what
 * an authenticator app's "enter code manually" field expects). Used only by
 * TotpSecret; not a general-purpose utility.
 */
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $binary = '';
        foreach (str_split($bytes) as $byte) {
            $binary .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= self::ALPHABET[(int) bindec($chunk)];
        }

        return $output;
    }

    public function decode(string $encoded): string
    {
        $encoded = strtoupper(str_replace(['-', ' ', '='], '', $encoded));

        $binary = '';
        foreach (str_split($encoded) as $char) {
            $position = strpos(self::ALPHABET, $char);
            if ($position === false) {
                throw new InvalidArgumentException("'{$char}' is not a valid base32 character");
            }

            $binary .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue; // trailing padding bits from the 5-bit grouping, not a real byte
            }

            $bytes .= chr((int) bindec($chunk));
        }

        return $bytes;
    }
}
