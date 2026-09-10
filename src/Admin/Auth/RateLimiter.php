<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * SPEC §13.1: "Login rate limiting with exponential backoff per account and
 * per source address." LoginService calls this once per attempt with two
 * separate keys — `account:<username>` and `ip:<address>` — so a distributed
 * attempt against many accounts from one address is still throttled by IP,
 * and credential stuffing against one account from many addresses is still
 * throttled by account.
 *
 * The first FREE_ATTEMPTS failures cost nothing (a mistyped password
 * shouldn't lock anyone out), then delay doubles per additional failure
 * up to MAX_DELAY_SECONDS. A success resets the counter to zero.
 */
final class RateLimiter
{
    private const FREE_ATTEMPTS     = 3;
    private const BASE_DELAY_SECONDS = 1;
    private const MAX_DELAY_SECONDS  = 900; // 15 min

    private readonly FileRecordStore $store;

    public function __construct(string $directory)
    {
        $this->store = new FileRecordStore($directory);
    }

    public function recordFailure(string $key): void
    {
        [$failures] = $this->state($key);
        $this->store->write($key, ['failures' => $failures + 1, 'lastFailureAt' => time()]);
    }

    public function recordSuccess(string $key): void
    {
        $this->store->delete($key);
    }

    public function isLocked(string $key): bool
    {
        return $this->retryAfterSeconds($key) > 0;
    }

    /**
     * Seconds until the next attempt is allowed; 0 when not locked.
     */
    public function retryAfterSeconds(string $key): int
    {
        [$failures, $lastFailureAt] = $this->state($key);
        if ($failures <= self::FREE_ATTEMPTS) {
            return 0;
        }

        $delay = min(self::MAX_DELAY_SECONDS, self::BASE_DELAY_SECONDS * 2 ** ($failures - self::FREE_ATTEMPTS));
        $elapsed = time() - $lastFailureAt;

        return max(0, $delay - $elapsed);
    }

    /**
     * @return array{0: int, 1: int} [failures, lastFailureAt]
     */
    private function state(string $key): array
    {
        $data = $this->store->read($key);
        if ($data === null || !isset($data['failures'], $data['lastFailureAt']) || !is_int($data['failures']) || !is_int($data['lastFailureAt'])) {
            return [0, 0];
        }

        return [$data['failures'], $data['lastFailureAt']];
    }
}
