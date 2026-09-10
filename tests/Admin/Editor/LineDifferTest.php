<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Editor;

use Cuniform\Admin\Editor\LineDiffer;
use Cuniform\Admin\Editor\LineDiffOp;
use PHPUnit\Framework\TestCase;

final class LineDifferTest extends TestCase
{
    public function testIdenticalTextProducesOnlySameEntries(): void
    {
        $entries = (new LineDiffer())->diff("a\nb\nc", "a\nb\nc");

        foreach ($entries as $entry) {
            self::assertSame(LineDiffOp::Same, $entry->op);
        }
        self::assertCount(3, $entries);
    }

    public function testAChangedMiddleLineIsRemovedThenAdded(): void
    {
        $entries = (new LineDiffer())->diff("a\nb\nc", "a\nx\nc");

        $ops = array_map(static fn ($e) => [$e->op, $e->line], $entries);

        self::assertSame([
            [LineDiffOp::Same, 'a'],
            [LineDiffOp::Removed, 'b'],
            [LineDiffOp::Added, 'x'],
            [LineDiffOp::Same, 'c'],
        ], $ops);
    }

    public function testEntirelyDifferentTextIsAllRemovedThenAllAdded(): void
    {
        $entries = (new LineDiffer())->diff('before', 'after');

        self::assertSame(LineDiffOp::Removed, $entries[0]->op);
        self::assertSame('before', $entries[0]->line);
        self::assertSame(LineDiffOp::Added, $entries[1]->op);
        self::assertSame('after', $entries[1]->line);
    }

    public function testVeryLargeInputFallsBackToACoarseDiffInsteadOfExhaustingMemory(): void
    {
        $before = implode("\n", array_fill(0, 2500, 'same line'));
        $after  = implode("\n", array_fill(0, 2500, 'same line')) . "\nextra";

        $entries = (new LineDiffer())->diff($before, $after);

        // Coarse fallback: everything from $before is Removed, everything
        // from $after is Added — no attempt at a precise line-level match.
        self::assertSame(LineDiffOp::Removed, $entries[0]->op);
        self::assertSame(LineDiffOp::Added, $entries[count($entries) - 1]->op);
    }
}
