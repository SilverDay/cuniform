<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\AdminException;
use Cuniform\Admin\Auth\AdminAccount;
use Cuniform\Admin\Auth\AdminAccountStore;
use PHPUnit\Framework\TestCase;

final class AdminAccountStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/cuniform_accounts_' . uniqid() . '/accounts.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @rmdir(dirname($this->path));
    }

    public function testFindReturnsNullBeforeTheFileExists(): void
    {
        self::assertNull((new AdminAccountStore($this->path))->find('operator'));
    }

    public function testSaveThenFindRoundTrips(): void
    {
        $store   = new AdminAccountStore($this->path);
        $account = new AdminAccount('operator', 'hash', 'secretbase32', ['codehash1', 'codehash2'], '2026-09-10T00:00:00+00:00');
        $store->save($account);

        $found = $store->find('operator');

        self::assertNotNull($found);
        self::assertSame('operator', $found->username);
        self::assertSame('hash', $found->passwordHash);
        self::assertSame('secretbase32', $found->totpSecretBase32);
        self::assertSame(['codehash1', 'codehash2'], $found->recoveryCodeHashes);
        self::assertSame('2026-09-10T00:00:00+00:00', $found->createdAt);
    }

    public function testSaveCreatesTheDirectoryWithRestrictedPermissions(): void
    {
        $store = new AdminAccountStore($this->path);
        $store->save(new AdminAccount('operator', 'hash', 'secret', [], '2026-09-10T00:00:00+00:00'));

        self::assertSame(0o700, fileperms(dirname($this->path)) & 0o777);
        self::assertSame(0o600, fileperms($this->path) & 0o777);
    }

    public function testSaveUpsertsAnExistingAccountWithoutLosingOtherAccounts(): void
    {
        $store = new AdminAccountStore($this->path);
        $store->save(new AdminAccount('alice', 'hash-a', 'secret-a', [], '2026-09-10T00:00:00+00:00'));
        $store->save(new AdminAccount('bob', 'hash-b', 'secret-b', [], '2026-09-10T00:00:00+00:00'));
        $store->save(new AdminAccount('alice', 'hash-a2', 'secret-a', [], '2026-09-10T00:00:00+00:00'));

        self::assertSame('hash-a2', $store->find('alice')?->passwordHash);
        self::assertSame('hash-b', $store->find('bob')?->passwordHash);
    }

    public function testWithPasswordHashAndWithRecoveryCodeHashesReturnNewImmutableInstances(): void
    {
        $account = new AdminAccount('operator', 'hash', 'secret', ['a', 'b'], '2026-09-10T00:00:00+00:00');

        $rehashed = $account->withPasswordHash('new-hash');
        self::assertSame('new-hash', $rehashed->passwordHash);
        self::assertSame('hash', $account->passwordHash);

        $consumed = $account->withRecoveryCodeHashes(['b']);
        self::assertSame(['b'], $consumed->recoveryCodeHashes);
        self::assertSame(['a', 'b'], $account->recoveryCodeHashes);
    }

    public function testACorruptFileRaisesAdminException(): void
    {
        mkdir(dirname($this->path), 0o700, true);
        file_put_contents($this->path, 'not valid json');

        $this->expectException(AdminException::class);
        (new AdminAccountStore($this->path))->find('operator');
    }
}
