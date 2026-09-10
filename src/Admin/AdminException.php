<?php

declare(strict_types=1);

namespace Cuniform\Admin;

use Cuniform\CuniformException;

/**
 * Failures in the admin application (SPEC §13) — auth, session handling,
 * rate limiting. A separate category from ConfigException/ContentException/
 * RenderException/BuildException (php-style.md: "one CuniformException, a
 * subclass per failure category").
 */
final class AdminException extends CuniformException
{
    public static function accountNotFound(string $username): self
    {
        return new self("no admin account '{$username}' (SPEC §13.1)");
    }

    public static function accountAlreadyExists(string $username): self
    {
        return new self("admin account '{$username}' already exists (SPEC §13.1)");
    }

    /**
     * @param list<string> $errors
     */
    public static function weakPassword(array $errors): self
    {
        $lines = array_map(static fn (string $error): string => '- ' . $error, $errors);

        return new self("Password does not meet policy (SPEC §13.1, NIST SP 800-63B):\n" . implode("\n", $lines));
    }

    public static function storeUnavailable(string $path): self
    {
        return new self("could not read or write admin store: {$path}");
    }

    public static function documentNotFound(string $identifier): self
    {
        return new self("no such document: '{$identifier}' (SPEC §13.2 — resolved against the content index)");
    }

    public static function documentWriteFailed(string $path): self
    {
        return new self("could not write document: {$path}");
    }

    /**
     * @param list<string> $command
     */
    public static function gitCommandFailed(array $command, string $output): self
    {
        $cmd = implode(' ', $command);

        return new self("git command failed ({$cmd}): " . trim($output));
    }
}
