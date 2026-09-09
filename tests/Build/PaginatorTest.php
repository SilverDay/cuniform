<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\Paginator;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    public function testEmptyListStillProducesExactlyOnePage(): void
    {
        self::assertSame([[]], (new Paginator())->paginate([], 10));
    }

    public function testItemsFittingOnOnePageProduceOnePage(): void
    {
        $items = ['a', 'b', 'c'];

        self::assertSame([$items], (new Paginator())->paginate($items, 10));
    }

    public function testItemsAreSplitAcrossMultiplePages(): void
    {
        $items = ['a', 'b', 'c', 'd', 'e'];

        self::assertSame([['a', 'b'], ['c', 'd'], ['e']], (new Paginator())->paginate($items, 2));
    }

    public function testPerPageBelowOneIsTreatedAsOne(): void
    {
        $items = ['a', 'b'];

        self::assertSame([['a'], ['b']], (new Paginator())->paginate($items, 0));
    }
}
