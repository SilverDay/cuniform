<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\RedirectEntry;
use Cuniform\Build\RedirectTargetChecker;
use PHPUnit\Framework\TestCase;

final class RedirectTargetCheckerTest extends TestCase
{
    public function testARedirectToAnExistingPathProducesNoError(): void
    {
        $entries = [new RedirectEntry('/feed.xml', '/de/feed.xml', 'content/redirects.map')];
        $known   = ['de/feed.xml' => true];

        self::assertSame([], (new RedirectTargetChecker())->check($entries, $known));
    }

    public function testARedirectToAMissingPathIsAnError(): void
    {
        $entries = [new RedirectEntry('/old/', '/de/does-not-exist/', 'content/redirects.map')];

        $errors = (new RedirectTargetChecker())->check($entries, []);

        self::assertNotSame([], $errors);
        self::assertStringContainsString('/de/does-not-exist/', $errors[0]);
        self::assertStringContainsString('does not exist in this release', $errors[0]);
    }
}
