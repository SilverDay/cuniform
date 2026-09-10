<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\AccountEnrollment;
use Cuniform\Admin\Auth\AdminAccountStore;
use Cuniform\Admin\Auth\BreachedPasswordChecker;
use Cuniform\Admin\Auth\EnrollmentResult;
use Cuniform\Admin\Auth\LoginService;
use Cuniform\Admin\Auth\LoginStatus;
use Cuniform\Admin\Auth\PasswordPolicy;
use Cuniform\Admin\Auth\PendingLoginStore;
use Cuniform\Admin\Auth\RateLimiter;
use Cuniform\Admin\Auth\SessionStore;
use Cuniform\Admin\Auth\Totp;
use Cuniform\Admin\Auth\TotpSecret;
use PHPUnit\Framework\TestCase;

final class LoginServiceTest extends TestCase
{
    private string $directory;
    private const USERNAME = 'operator';
    private const PASSWORD = 'a-long-enough-passphrase';
    private const IP       = '203.0.113.5';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cuniform_loginservice_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testTheFullTwoStepLoginSucceeds(): void
    {
        [$service, $enrollment] = $this->wired();

        $step1 = $service->startLogin(self::USERNAME, self::PASSWORD, self::IP);
        self::assertSame(LoginStatus::PendingTotp, $step1->status);
        self::assertNotNull($step1->pendingLoginId);

        $code  = (new Totp())->currentCode(TotpSecret::fromBase32($enrollment->totpSecretBase32));
        $step2 = $service->completeSecondFactor($step1->pendingLoginId, $code, self::IP);

        self::assertSame(LoginStatus::Authenticated, $step2->status);
        self::assertNotNull($step2->session);
        self::assertSame(self::USERNAME, $step2->session->username);
    }

    public function testAWrongPasswordIsRejectedWithoutCreatingAPendingLogin(): void
    {
        [$service] = $this->wired();

        $outcome = $service->startLogin(self::USERNAME, 'totally-wrong-password', self::IP);

        self::assertSame(LoginStatus::InvalidCredentials, $outcome->status);
        self::assertNull($outcome->pendingLoginId);
    }

    public function testAnUnknownUsernameIsRejectedTheSameWayAsAWrongPassword(): void
    {
        [$service] = $this->wired();

        $outcome = $service->startLogin('no-such-operator', 'whatever-password-12', self::IP);

        self::assertSame(LoginStatus::InvalidCredentials, $outcome->status);
    }

    public function testRepeatedPasswordFailuresEventuallyRateLimit(): void
    {
        [$service] = $this->wired();

        $last = null;
        for ($i = 0; $i < 6; $i++) {
            $last = $service->startLogin(self::USERNAME, 'wrong-password-attempt', self::IP);
        }

        self::assertSame(LoginStatus::RateLimited, $last->status);
        self::assertGreaterThan(0, $last->retryAfterSeconds);
    }

    public function testACorrectPasswordIsStillRateLimitedAfterTooManyFailures(): void
    {
        [$service] = $this->wired();

        for ($i = 0; $i < 6; $i++) {
            $service->startLogin(self::USERNAME, 'wrong-password-attempt', self::IP);
        }

        $outcome = $service->startLogin(self::USERNAME, self::PASSWORD, self::IP);

        self::assertSame(LoginStatus::RateLimited, $outcome->status);
    }

    public function testAWrongTotpCodeIsRejected(): void
    {
        [$service] = $this->wired();

        $pendingId = $service->startLogin(self::USERNAME, self::PASSWORD, self::IP)->pendingLoginId;
        self::assertNotNull($pendingId);
        $outcome = $service->completeSecondFactor($pendingId, '000000', self::IP);

        self::assertSame(LoginStatus::InvalidCode, $outcome->status);
    }

    public function testAnUnknownPendingLoginIdIsExpired(): void
    {
        [$service] = $this->wired();

        $outcome = $service->completeSecondFactor('does-not-exist', '123456', self::IP);

        self::assertSame(LoginStatus::Expired, $outcome->status);
    }

    public function testARecoveryCodeCompletesLoginAndIsConsumed(): void
    {
        [$service, $enrollment, $accounts] = $this->wired();
        $recoveryCode = $enrollment->recoveryCodes[0];

        $pendingId = $service->startLogin(self::USERNAME, self::PASSWORD, self::IP)->pendingLoginId;
        self::assertNotNull($pendingId);
        $outcome = $service->completeSecondFactor($pendingId, $recoveryCode, self::IP);

        self::assertSame(LoginStatus::Authenticated, $outcome->status);

        $account = $accounts->find(self::USERNAME);
        self::assertNotNull($account);
        self::assertCount(9, $account->recoveryCodeHashes);

        // Single-use: the same code can't complete a second login.
        $pendingId2 = $service->startLogin(self::USERNAME, self::PASSWORD, self::IP)->pendingLoginId;
        self::assertNotNull($pendingId2);
        $outcome2 = $service->completeSecondFactor($pendingId2, $recoveryCode, self::IP);
        self::assertSame(LoginStatus::InvalidCode, $outcome2->status);
    }

    public function testLogoutInvalidatesTheSession(): void
    {
        [$service, $enrollment] = $this->wired();

        $pendingId = $service->startLogin(self::USERNAME, self::PASSWORD, self::IP)->pendingLoginId;
        self::assertNotNull($pendingId);
        $code    = (new Totp())->currentCode(TotpSecret::fromBase32($enrollment->totpSecretBase32));
        $session = $service->completeSecondFactor($pendingId, $code, self::IP)->session;
        self::assertNotNull($session);

        self::assertNotNull($service->validateSession($session->id));

        $service->logout($session->id);

        self::assertNull($service->validateSession($session->id));
    }

    public function testASuccessfulLoginResetsTheRateLimitForThatAccount(): void
    {
        [$service, $enrollment] = $this->wired();

        for ($i = 0; $i < 3; $i++) {
            $service->startLogin(self::USERNAME, 'wrong-password-attempt', self::IP);
        }

        $pendingId = $service->startLogin(self::USERNAME, self::PASSWORD, self::IP)->pendingLoginId;
        self::assertNotNull($pendingId);
        $code = (new Totp())->currentCode(TotpSecret::fromBase32($enrollment->totpSecretBase32));
        $service->completeSecondFactor($pendingId, $code, self::IP);

        // Free attempts again — the earlier failures were reset by the
        // completed login, so three more failures shouldn't lock it yet.
        $last = null;
        for ($i = 0; $i < 3; $i++) {
            $last = $service->startLogin(self::USERNAME, 'wrong-password-attempt', self::IP);
        }
        self::assertSame(LoginStatus::InvalidCredentials, $last->status);
    }

    /**
     * @return array{0: LoginService, 1: EnrollmentResult, 2: AdminAccountStore}
     */
    private function wired(): array
    {
        $accounts = new AdminAccountStore($this->directory . '/accounts.json');
        $policy   = new PasswordPolicy($this->neverBreached());

        $enrollment = (new AccountEnrollment($accounts, $policy))->enroll(self::USERNAME, self::PASSWORD);

        $service = new LoginService(
            $accounts,
            new PendingLoginStore($this->directory . '/pending-logins'),
            new SessionStore($this->directory . '/sessions'),
            new RateLimiter($this->directory . '/rate-limits'),
        );

        return [$service, $enrollment, $accounts];
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

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) && !is_link($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}
