<?php

declare(strict_types=1);

namespace Cuniform\Tests\Cutover;

use Cuniform\Cutover\RobotsTxt;
use PHPUnit\Framework\TestCase;

final class RobotsTxtTest extends TestCase
{
    public function testAllowAllAllowsEverything(): void
    {
        self::assertTrue(RobotsTxt::allowAll()->isAllowed('/anything/'));
    }

    public function testDisallowedPrefixIsRejected(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /wp-admin/\n");

        self::assertFalse($robots->isAllowed('/wp-admin/edit.php'));
        self::assertTrue($robots->isAllowed('/blog/post/'));
    }

    public function testOnlyStarAgentRulesApply(): void
    {
        $robots = RobotsTxt::parse("User-agent: Googlebot\nDisallow: /googlebot-only/\n");

        self::assertTrue($robots->isAllowed('/googlebot-only/'), 'this crawler identifies as * only');
    }

    public function testCommentsAndBlankLinesAreIgnored(): void
    {
        $robots = RobotsTxt::parse("# comment\n\nUser-agent: *\n# another comment\nDisallow: /private/\n");

        self::assertFalse($robots->isAllowed('/private/x'));
    }

    public function testEmptyDisallowMeansNoRestriction(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow:\n");

        self::assertTrue($robots->isAllowed('/anything/'));
    }

    public function testMultipleDisallowLinesAllApply(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /a/\nDisallow: /b/\n");

        self::assertFalse($robots->isAllowed('/a/x'));
        self::assertFalse($robots->isAllowed('/b/x'));
        self::assertTrue($robots->isAllowed('/c/x'));
    }
}
