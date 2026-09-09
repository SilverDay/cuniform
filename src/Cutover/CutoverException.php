<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

use Cuniform\CuniformException;

/**
 * Failures specific to the M4 cutover tooling (SPEC §15.5) — legacy URL
 * enumeration (T25) today, the frozen-snapshot machinery (T26) later.
 * Kept separate from BuildException: this tooling runs standalone, never
 * as part of `bin/cuniform build`, so its failures are never build
 * failures.
 */
final class CutoverException extends CuniformException
{
    public static function invalidBaseUrl(string $url): self
    {
        return new self("'{$url}' is not a valid absolute URL (need an http:// or https:// host)");
    }

    public static function fetchFailed(string $url): self
    {
        return new self("could not fetch '{$url}'");
    }

    /**
     * @param list<string> $errors
     */
    public static function fromErrors(array $errors): self
    {
        $lines = array_map(static fn (string $error): string => '- ' . $error, $errors);

        return new self("Legacy URL enumeration failed:\n" . implode("\n", $lines));
    }
}
