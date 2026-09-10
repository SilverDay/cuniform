<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

use Cuniform\Admin\AdminException;

/**
 * Persists admin accounts to a single JSON file, `<var>/admin/accounts.json`
 * — this project has no database, so this is the credential store, the same
 * pattern `Cuniform\Build\BuildCache` already uses for `var/build-cache.json`
 * (atomic tmp-then-rename write). Kept separate from sessions/pending-logins/
 * rate-limits (their own stores) since accounts are long-lived and rare to
 * write, while the others churn on every request.
 *
 * File permissions are tightened to 0600 (account records include a
 * password hash and a TOTP secret) and the containing directory to 0700 —
 * defence in depth on a host where `var/` is already not web-accessible.
 */
final class AdminAccountStore
{
    public function __construct(private readonly string $path)
    {
    }

    public function find(string $username): ?AdminAccount
    {
        $accounts = $this->load();

        return $accounts[$username] ?? null;
    }

    public function save(AdminAccount $account): void
    {
        $accounts = $this->load();
        $accounts[$account->username] = $account;
        $this->write($accounts);
    }

    /**
     * @return array<string, AdminAccount>
     */
    private function load(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            throw AdminException::storeUnavailable($this->path);
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw AdminException::storeUnavailable($this->path);
        }

        if (!is_array($decoded)) {
            throw AdminException::storeUnavailable($this->path);
        }

        $accounts = [];
        foreach ($decoded as $username => $entry) {
            if (
                !is_string($username)
                || !is_array($entry)
                || !isset($entry['passwordHash'], $entry['totpSecretBase32'], $entry['recoveryCodeHashes'], $entry['createdAt'])
                || !is_string($entry['passwordHash'])
                || !is_string($entry['totpSecretBase32'])
                || !is_array($entry['recoveryCodeHashes'])
                || !is_string($entry['createdAt'])
            ) {
                throw AdminException::storeUnavailable($this->path);
            }

            /** @var list<string> $recoveryCodeHashes */
            $recoveryCodeHashes = array_values(array_filter($entry['recoveryCodeHashes'], 'is_string'));

            $accounts[$username] = new AdminAccount(
                $username,
                $entry['passwordHash'],
                $entry['totpSecretBase32'],
                $recoveryCodeHashes,
                $entry['createdAt'],
            );
        }

        return $accounts;
    }

    /**
     * @param array<string, AdminAccount> $accounts
     */
    private function write(array $accounts): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0o700, true) && !is_dir($dir)) {
            throw AdminException::storeUnavailable($this->path);
        }

        $encoded = [];
        foreach ($accounts as $username => $account) {
            $encoded[$username] = [
                'passwordHash'       => $account->passwordHash,
                'totpSecretBase32'   => $account->totpSecretBase32,
                'recoveryCodeHashes' => $account->recoveryCodeHashes,
                'createdAt'          => $account->createdAt,
            ];
        }

        $json = json_encode($encoded, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        $tmp = $this->path . '.tmp';
        if (file_put_contents($tmp, $json) === false || !chmod($tmp, 0o600) || !rename($tmp, $this->path)) {
            throw AdminException::storeUnavailable($this->path);
        }
    }
}
