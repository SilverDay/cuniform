<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * Server-side session store (SPEC §13.1): "30 min idle / 12 h absolute,
 * server-side store in var/". Deliberately not PHP's built-in session
 * handling — that relies on superglobal state and ambient `session_start()`
 * side effects, at odds with this project's "no static mutable state,
 * constructor injection" convention (php-style.md) and hard to unit test
 * without a real request lifecycle. This is instead an explicit,
 * constructor-injected store, the same shape as PendingLoginStore.
 */
final class SessionStore
{
    private const IDLE_SECONDS     = 30 * 60;
    private const ABSOLUTE_SECONDS = 12 * 60 * 60;

    private readonly FileRecordStore $store;

    public function __construct(string $directory)
    {
        $this->store = new FileRecordStore($directory);
    }

    public function create(string $username): Session
    {
        $now     = time();
        $session = new Session(bin2hex(random_bytes(32)), $username, $now, $now);
        $this->persist($session);

        return $session;
    }

    /**
     * Reading a valid session also refreshes its idle window (a touch) —
     * every authenticated request "uses" the session, which is exactly
     * what SPEC's 30-minute *idle* timeout means: idle since the last
     * activity, not since login.
     */
    public function find(string $id): ?Session
    {
        $session = $this->load($id);
        if ($session === null) {
            return null;
        }

        $touched = new Session($session->id, $session->username, $session->createdAt, time());
        $this->persist($touched);

        return $touched;
    }

    /**
     * New session ID, same username and creation time — SPEC §13.1's
     * "regenerated on privilege change": LoginService calls this exactly
     * once, turning a just-verified TOTP step into the real session, so the
     * ID a browser held during the unauthenticated password/TOTP exchange
     * is never the same ID that goes on to represent a logged-in operator
     * (session fixation defence).
     */
    public function regenerate(Session $session): Session
    {
        $regenerated = new Session(bin2hex(random_bytes(32)), $session->username, $session->createdAt, time());
        $this->persist($regenerated);
        $this->store->delete($session->id);

        return $regenerated;
    }

    public function destroy(string $id): void
    {
        $this->store->delete($id);
    }

    private function load(string $id): ?Session
    {
        $data = $this->store->read($id);
        if (
            $data === null
            || !isset($data['username'], $data['createdAt'], $data['lastActivityAt'])
            || !is_string($data['username'])
            || !is_int($data['createdAt'])
            || !is_int($data['lastActivityAt'])
        ) {
            return null;
        }

        $session = new Session($id, $data['username'], $data['createdAt'], $data['lastActivityAt']);
        if ($session->isExpired(time(), self::IDLE_SECONDS, self::ABSOLUTE_SECONDS)) {
            $this->store->delete($id);

            return null;
        }

        return $session;
    }

    private function persist(Session $session): void
    {
        $this->store->write($session->id, [
            'username'       => $session->username,
            'createdAt'      => $session->createdAt,
            'lastActivityAt' => $session->lastActivityAt,
        ]);
    }
}
