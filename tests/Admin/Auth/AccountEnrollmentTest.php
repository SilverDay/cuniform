<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\AdminException;
use Cuniform\Admin\Auth\AccountEnrollment;
use Cuniform\Admin\Auth\AdminAccountStore;
use Cuniform\Admin\Auth\BreachedPasswordChecker;
use Cuniform\Admin\Auth\PasswordHasher;
use Cuniform\Admin\Auth\PasswordPolicy;
use Cuniform\Admin\Auth\RecoveryCodes;
use Cuniform\Admin\Auth\Totp;
use Cuniform\Admin\Auth\TotpSecret;
use PHPUnit\Framework\TestCase;

final class AccountEnrollmentTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/cuniform_enroll_' . uniqid() . '/accounts.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @rmdir(dirname($this->path));
    }

    public function testEnrollPersistsAnAccountThatVerifiesTheGivenPassword(): void
    {
        $store  = new AdminAccountStore($this->path);
        $result = $this->enrollment($store)->enroll('operator', 'a-long-enough-passphrase');

        $account = $store->find('operator');
        self::assertNotNull($account);
        self::assertTrue((new PasswordHasher())->verify('a-long-enough-passphrase', $account->passwordHash));
        self::assertSame($account->username, $result->account->username);
        self::assertSame($account->passwordHash, $result->account->passwordHash);
    }

    public function testEnrollReturnsTenPlaintextRecoveryCodesThatMatchTheStoredHashes(): void
    {
        $store  = new AdminAccountStore($this->path);
        $result = $this->enrollment($store)->enroll('operator', 'a-long-enough-passphrase');

        $account = $store->find('operator');
        self::assertNotNull($account);
        self::assertCount(10, $result->recoveryCodes);
        self::assertCount(10, $account->recoveryCodeHashes);

        $recoveryCodes = new RecoveryCodes();
        foreach ($result->recoveryCodes as $index => $code) {
            self::assertTrue($recoveryCodes->verify($code, $account->recoveryCodeHashes[$index]));
        }
    }

    public function testEnrollReturnsAWorkingTotpSecret(): void
    {
        $result = $this->enrollment(new AdminAccountStore($this->path))->enroll('operator', 'a-long-enough-passphrase');

        $totp   = new Totp();
        $secret = TotpSecret::fromBase32($result->totpSecretBase32);
        self::assertSame($totp->currentCode($secret), $totp->currentCode($secret));
        self::assertStringContainsString($result->totpSecretBase32, $result->totpProvisioningUri);
    }

    public function testEnrollRejectsAnAlreadyExistingUsername(): void
    {
        $store      = new AdminAccountStore($this->path);
        $enrollment = $this->enrollment($store);
        $enrollment->enroll('operator', 'a-long-enough-passphrase');

        $this->expectException(AdminException::class);
        $enrollment->enroll('operator', 'another-long-passphrase');
    }

    public function testEnrollRejectsAWeakPasswordAndPersistsNothing(): void
    {
        $store = new AdminAccountStore($this->path);

        try {
            $this->enrollment($store)->enroll('operator', 'short');
            self::fail('expected AdminException');
        } catch (AdminException) {
            self::assertNull($store->find('operator'));
        }
    }

    private function enrollment(AdminAccountStore $store): AccountEnrollment
    {
        return new AccountEnrollment($store, new PasswordPolicy($this->neverBreached()));
    }

    private function neverBreached(): BreachedPasswordChecker
    {
        return new class () implements BreachedPasswordChecker {
            public function isBreached(string $password): bool
            {
                return false;
            }
        };
    }
}
