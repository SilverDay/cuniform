<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\Base32;
use Cuniform\Admin\Auth\Totp;
use Cuniform\Admin\Auth\TotpSecret;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    private TotpSecret $rfcSecret;

    protected function setUp(): void
    {
        // RFC 6238 Appendix B's own test secret, ASCII "12345678901234567890"
        // (20 bytes, matching its SHA1 test vectors).
        $this->rfcSecret = TotpSecret::fromBase32((new Base32())->encode('12345678901234567890'));
    }

    /**
     * RFC 6238 Appendix B's test vectors are published as 8-digit codes;
     * a 6-digit code is exactly the last 6 digits of the same computation
     * (`mod 10^6` of a value already known to equal a given `mod 10^8`
     * result is just that result's low 6 digits — 10^6 divides 10^8), so
     * these are derived directly from the RFC table, not independently
     * sourced.
     *
     * @return array<string, array{int, string}>
     */
    public static function rfcVectors(): array
    {
        return [
            'T=59 (1970-01-01 00:00:59)'          => [59, '287082'],
            'T=1111111109 (2005-03-18 01:58:29)'  => [1111111109, '081804'],
            'T=1111111111 (2005-03-18 01:58:31)'  => [1111111111, '050471'],
            'T=1234567890 (2009-02-13 23:31:30)'  => [1234567890, '005924'],
            'T=2000000000 (2033-05-18 03:33:20)'  => [2000000000, '279037'],
        ];
    }

    #[DataProvider('rfcVectors')]
    public function testCurrentCodeMatchesRfc6238TestVectors(int $timestamp, string $expected): void
    {
        self::assertSame($expected, (new Totp())->currentCode($this->rfcSecret, $timestamp));
    }

    #[DataProvider('rfcVectors')]
    public function testVerifyAcceptsTheExactMatchingCode(int $timestamp, string $expected): void
    {
        self::assertTrue((new Totp())->verify($this->rfcSecret, $expected, $timestamp));
    }

    public function testVerifyRejectsAWrongCode(): void
    {
        $totp = new Totp();
        self::assertFalse($totp->verify($this->rfcSecret, '000000', 59));
    }

    public function testVerifyAcceptsOneStepBeforeAndAfterForClockDrift(): void
    {
        $totp = new Totp();
        // T=59 is step 1 (59 / 30 = 1); step 0 covers [0,29], step 2 covers [60,89].
        $oneStepBefore = $totp->currentCode($this->rfcSecret, 29);  // step 0
        $oneStepAfter  = $totp->currentCode($this->rfcSecret, 89);  // step 2

        self::assertTrue($totp->verify($this->rfcSecret, $oneStepBefore, 59));
        self::assertTrue($totp->verify($this->rfcSecret, $oneStepAfter, 59));
    }

    public function testVerifyRejectsTwoStepsAway(): void
    {
        $totp = new Totp();
        $twoStepsAfter = $totp->currentCode($this->rfcSecret, 149); // step 4, vs. step 1 at T=59

        self::assertFalse($totp->verify($this->rfcSecret, $twoStepsAfter, 59));
    }

    public function testDifferentSecretsProduceDifferentCodes(): void
    {
        $totp    = new Totp();
        $secretA = TotpSecret::generate();
        $secretB = TotpSecret::generate();

        self::assertNotSame($totp->currentCode($secretA, 1000), $totp->currentCode($secretB, 1000));
    }

    public function testCurrentCodeIsSixDigitsZeroPadded(): void
    {
        $code = (new Totp())->currentCode($this->rfcSecret, 59);

        self::assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testProvisioningUriCarriesTheExpectedParameters(): void
    {
        $uri = (new Totp())->provisioningUri($this->rfcSecret, 'operator', 'Cuniform');

        self::assertStringStartsWith('otpauth://totp/Cuniform:operator?', $uri);
        self::assertStringContainsString('secret=' . $this->rfcSecret->toBase32(), $uri);
        self::assertStringContainsString('issuer=Cuniform', $uri);
        self::assertStringContainsString('algorithm=SHA1', $uri);
        self::assertStringContainsString('digits=6', $uri);
        self::assertStringContainsString('period=30', $uri);
    }
}
