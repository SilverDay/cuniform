<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\DenylistBreachedPasswordChecker;
use PHPUnit\Framework\TestCase;

final class DenylistBreachedPasswordCheckerTest extends TestCase
{
    public function testFlagsAWellKnownCommonPassword(): void
    {
        self::assertTrue((new DenylistBreachedPasswordChecker())->isBreached('password'));
    }

    public function testFlaggingIsCaseInsensitive(): void
    {
        self::assertTrue((new DenylistBreachedPasswordChecker())->isBreached('PaSSwOrd'));
    }

    public function testDoesNotFlagAHighEntropyPassword(): void
    {
        self::assertFalse((new DenylistBreachedPasswordChecker())->isBreached('xk8-fQ2!mZp9-Ltrq7'));
    }

    public function testMissingListFileIsTreatedAsAnEmptyDenylistNotAFailure(): void
    {
        $checker = new DenylistBreachedPasswordChecker(sys_get_temp_dir() . '/cuniform_no_such_file_' . uniqid() . '.txt');

        self::assertFalse($checker->isBreached('password'));
    }
}
