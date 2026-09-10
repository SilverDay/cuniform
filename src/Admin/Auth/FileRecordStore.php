<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

use Cuniform\Admin\AdminException;

/**
 * Shared atomic-write, one-file-per-record JSON store used by
 * PendingLoginStore, SessionStore, and RateLimiter — the three stores that
 * churn on every login attempt, as opposed to AdminAccountStore's single
 * rarely-written file. The record key (a session ID, a rate-limit bucket
 * name, ...) is hashed into the filename so the raw key — a bearer token
 * for SessionStore's case — never sits in cleartext as a filename on disk.
 */
final class FileRecordStore
{
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @return array<string, mixed>|null Null when the record doesn't exist
     *                                    or fails to parse as a JSON object.
     */
    public function read(string $key): ?array
    {
        $path = $this->pathFor($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function write(string $key, array $data): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o700, true) && !is_dir($this->directory)) {
            throw AdminException::storeUnavailable($this->directory);
        }

        $path = $this->pathFor($key);
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $json) === false || !chmod($tmp, 0o600) || !rename($tmp, $path)) {
            throw AdminException::storeUnavailable($path);
        }
    }

    public function delete(string $key): void
    {
        @unlink($this->pathFor($key));
    }

    private function pathFor(string $key): string
    {
        return rtrim($this->directory, '/') . '/' . hash('sha256', $key) . '.json';
    }
}
