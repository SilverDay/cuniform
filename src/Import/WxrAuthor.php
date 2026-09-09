<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * One `<wp:author>` entry from the WXR channel (SPEC §A.1) — the site's
 * WordPress user accounts, not necessarily the same identity for every
 * post (`dc:creator` on each `WxrItem` is the per-post byline; this is
 * the account roster the export declares).
 */
final class WxrAuthor
{
    public function __construct(
        public readonly int $id,
        public readonly string $login,
        public readonly string $email,
        public readonly string $displayName,
        public readonly string $firstName,
        public readonly string $lastName,
    ) {
    }
}
