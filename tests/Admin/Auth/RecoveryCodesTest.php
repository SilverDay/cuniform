<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\RecoveryCodes;
use PHPUnit\Framework\TestCase;

final class RecoveryCodesTest extends TestCase
{
    public function testGenerateProducesTenCodesByDefault(): void
    {
        self::assertCount(10, (new RecoveryCodes())->generate());
    }

    public function testGenerateProducesTheRequestedCount(): void
    {
        self::assertCount(3, (new RecoveryCodes())->generate(3));
    }

    public function testGeneratedCodesAreFormattedAndUnique(): void
    {
        $codes = (new RecoveryCodes())->generate();

        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[A-Z2-7]{8}-[A-Z2-7]{8}$/', $code);
        }

        self::assertCount(count($codes), array_unique($codes));
    }

    public function testVerifyAcceptsTheMatchingPlaintextAgainstItsHash(): void
    {
        $recoveryCodes = new RecoveryCodes();
        $code          = $recoveryCodes->generate(1)[0];

        self::assertTrue($recoveryCodes->verify($code, $recoveryCodes->hash($code)));
    }

    public function testVerifyRejectsADifferentCode(): void
    {
        $recoveryCodes = new RecoveryCodes();
        [$first, $second] = $recoveryCodes->generate(2);

        self::assertFalse($recoveryCodes->verify($second, $recoveryCodes->hash($first)));
    }

    public function testVerifyIsCaseAndDashInsensitive(): void
    {
        $recoveryCodes = new RecoveryCodes();
        $code          = $recoveryCodes->generate(1)[0];
        $hash          = $recoveryCodes->hash($code);

        self::assertTrue($recoveryCodes->verify(strtolower(str_replace('-', ' ', $code)), $hash));
    }

    public function testHashNeverStoresThePlaintext(): void
    {
        $recoveryCodes = new RecoveryCodes();
        $code          = $recoveryCodes->generate(1)[0];

        self::assertStringNotContainsString($code, $recoveryCodes->hash($code));
    }
}
