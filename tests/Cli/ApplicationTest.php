<?php

declare(strict_types=1);

namespace Cuniform\Tests\Cli;

use Cuniform\Cli\Application;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testNoArgumentsFailsWithUsage(): void
    {
        self::assertSame(2, (new Application())->run([]));
    }

    public function testUnknownCommandFails(): void
    {
        self::assertSame(2, (new Application())->run(['publish']));
    }

    public function testUnknownFlagFails(): void
    {
        self::assertSame(2, (new Application())->run(['build', '--bogus']));
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('validBuildInvocations')]
    public function testRecognizedBuildInvocationsAreNotYetImplemented(array $arguments): void
    {
        self::assertSame(1, (new Application())->run($arguments));
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function validBuildInvocations(): iterable
    {
        yield 'plain build' => [['build']];
        yield 'full build' => [['build', '--full']];
        yield 'dry run' => [['build', '--dry-run']];
        yield 'rollback' => [['build', '--rollback']];
    }
}
