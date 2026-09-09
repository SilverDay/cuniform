<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * Splits a list into pages of at most `$perPage` items (SPEC §8.1's
 * `/{L}` + `/{L}page/2/` pair, and the same pattern reused for a tag
 * archive). An empty list still produces exactly one — empty — page,
 * never zero: the language home route must always resolve (SPEC §7.4.1,
 * §7.5's `x-default`), even for a brand-new site with no posts yet.
 */
final class Paginator
{
    /**
     * @template T
     *
     * @param  list<T>       $items
     * @return list<list<T>>
     */
    public function paginate(array $items, int $perPage): array
    {
        if ($items === []) {
            return [[]];
        }

        return array_chunk($items, max($perPage, 1));
    }
}
