<?php

declare(strict_types=1);

namespace Cuniform\Tests\Content;

use Cuniform\Content\ContentException;
use Cuniform\Content\FrontMatter\NavGroup;
use Cuniform\Content\NavCandidate;
use Cuniform\Content\NavTreeBuilder;
use PHPUnit\Framework\TestCase;

final class NavTreeBuilderTest extends TestCase
{
    public function testPageWithoutNavOrderIsReachableButNotInNav(): void
    {
        $result = (new NavTreeBuilder())->build([
            $this->candidate('about', navOrder: null),
        ]);

        self::assertSame([], $result['primary']);
        self::assertSame([], $result['footer']);
    }

    public function testNavOrderWithoutNavGroupDefaultsToPrimary(): void
    {
        $result = (new NavTreeBuilder())->build([
            $this->candidate('about', navOrder: 1, navGroup: null),
        ]);

        self::assertCount(1, $result['primary']);
        self::assertSame('About', $result['primary'][0]->label);
    }

    public function testNavGroupNoneExcludesEvenWithNavOrderSet(): void
    {
        $result = (new NavTreeBuilder())->build([
            $this->candidate('about', navOrder: 1, navGroup: NavGroup::None),
        ]);

        self::assertSame([], $result['primary']);
        self::assertSame([], $result['footer']);
    }

    public function testSeparatesPrimaryAndFooterGroups(): void
    {
        $result = (new NavTreeBuilder())->build([
            $this->candidate('about', navOrder: 1, navGroup: NavGroup::Primary),
            $this->candidate('impressum', navOrder: 1, navGroup: NavGroup::Footer),
        ]);

        self::assertCount(1, $result['primary']);
        self::assertCount(1, $result['footer']);
        self::assertSame('About', $result['primary'][0]->label);
        self::assertSame('Impressum', $result['footer'][0]->label);
    }

    public function testOrdersSiblingsByNavOrder(): void
    {
        $result = (new NavTreeBuilder())->build([
            $this->candidate('b', navOrder: 2, label: 'B'),
            $this->candidate('a', navOrder: 1, label: 'A'),
        ]);

        self::assertSame(['A', 'B'], array_map(static fn ($item) => $item->label, $result['primary']));
    }

    public function testNestsByDirectoryPositionWhenNavParentIsAbsent(): void
    {
        $result = (new NavTreeBuilder())->build([
            $this->candidate('vortraege', navOrder: 1, label: 'Talks'),
            $this->candidate('vortraege/coffee-factor', navOrder: 1, label: 'Coffee Factor'),
        ]);

        self::assertCount(1, $result['primary']);
        self::assertSame('Talks', $result['primary'][0]->label);
        self::assertCount(1, $result['primary'][0]->children);
        self::assertSame('Coffee Factor', $result['primary'][0]->children[0]->label);
    }

    public function testExplicitNavParentOverridesDirectoryPosition(): void
    {
        $result = (new NavTreeBuilder())->build([
            $this->candidate('about', navOrder: 1, label: 'About'),
            $this->candidate('vortraege/coffee-factor', navOrder: 1, label: 'Coffee Factor', navParent: 'about'),
        ]);

        self::assertCount(1, $result['primary']);
        self::assertSame('About', $result['primary'][0]->label);
        self::assertSame('Coffee Factor', $result['primary'][0]->children[0]->label);
    }

    public function testWarnsWhenADirectoryHasNoIndexPage(): void
    {
        $result = (new NavTreeBuilder())->build([
            $this->candidate('vortraege/coffee-factor', navOrder: null),
        ]);

        self::assertCount(1, $result['warnings']);
        self::assertStringContainsString("'vortraege' has no index.md", $result['warnings'][0]);
    }

    public function testNoWarningWhenTheDirectoryHasAnIndexPage(): void
    {
        $result = (new NavTreeBuilder())->build([
            $this->candidate('vortraege', navOrder: null),
            $this->candidate('vortraege/coffee-factor', navOrder: null),
        ]);

        self::assertSame([], $result['warnings']);
    }

    public function testRejectsNestingDeeperThanThreeLevels(): void
    {
        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches('/more than 3 levels/');

        (new NavTreeBuilder())->build([
            $this->candidate('a/b/c/d', navOrder: null),
        ]);
    }

    public function testExactlyThreeLevelsIsAllowed(): void
    {
        // Depth 3 must not throw — unlike testRejectsNestingDeeperThanThreeLevels,
        // which uses depth 4. The missing 'a' and 'a/b' index pages are a
        // separate, expected warning (testWarnsWhenADirectoryHasNoIndexPage
        // already covers that mechanism), not a failure here.
        $result = (new NavTreeBuilder())->build([
            $this->candidate('a/b/c', navOrder: null),
        ]);

        self::assertSame([], $result['primary']);
    }

    private function candidate(
        string $path,
        ?int $navOrder,
        ?string $label = null,
        ?NavGroup $navGroup = NavGroup::Primary,
        ?string $navParent = null,
    ): NavCandidate {
        return new NavCandidate(
            'de',
            $path,
            "/de/{$path}/",
            $label ?? ucfirst($path),
            $navOrder,
            $navParent,
            $navOrder === null ? null : $navGroup,
        );
    }
}
