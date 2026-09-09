<?php

declare(strict_types=1);

namespace Cuniform\Template;

/**
 * What `partials/pagination.php` needs to render prev/next links and a
 * "page X of Y" label (SPEC §8.1). `previousUrl`/`nextUrl` are null at
 * the respective end of the run.
 */
final class Pagination
{
    public function __construct(
        public readonly int $currentPage,
        public readonly int $totalPages,
        public readonly ?string $previousUrl,
        public readonly ?string $nextUrl,
    ) {
    }
}
