<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * The WXR `<channel>` metadata surrounding every `<item>` (SPEC §A.1) —
 * site title/URL and the author roster, read once per export.
 */
final class WxrChannel
{
    /**
     * @param list<WxrAuthor> $authors
     */
    public function __construct(
        public readonly string $title,
        public readonly string $link,
        public readonly string $description,
        public readonly string $language,
        public readonly string $wxrVersion,
        public readonly string $baseSiteUrl,
        public readonly string $baseBlogUrl,
        public readonly array $authors,
    ) {
    }
}
