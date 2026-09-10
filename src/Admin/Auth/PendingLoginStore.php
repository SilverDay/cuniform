<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * Server-side store for PendingLogin records, keyed by a random ID handed
 * to the browser in a short-lived cookie between the password step and the
 * TOTP step. A 5-minute TTL — long enough to type a 6-digit code, short
 * enough that an abandoned password-only login can't be resumed later.
 */
final class PendingLoginStore
{
    private const TTL_SECONDS = 300;

    private readonly FileRecordStore $store;

    public function __construct(string $directory)
    {
        $this->store = new FileRecordStore($directory);
    }

    public function create(string $username): PendingLogin
    {
        $pending = new PendingLogin(bin2hex(random_bytes(32)), $username, time());
        $this->persist($pending);

        return $pending;
    }

    public function find(string $id): ?PendingLogin
    {
        $data = $this->store->read($id);
        if (
            $data === null
            || !isset($data['username'], $data['createdAt'])
            || !is_string($data['username'])
            || !is_int($data['createdAt'])
        ) {
            return null;
        }

        $pending = new PendingLogin($id, $data['username'], $data['createdAt']);
        if ($pending->isExpired(time(), self::TTL_SECONDS)) {
            $this->delete($id);

            return null;
        }

        return $pending;
    }

    public function delete(string $id): void
    {
        $this->store->delete($id);
    }

    private function persist(PendingLogin $pending): void
    {
        $this->store->write($pending->id, [
            'username'  => $pending->username,
            'createdAt' => $pending->createdAt,
        ]);
    }
}
