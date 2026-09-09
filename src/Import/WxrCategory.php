<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * One `<category>` element on a WXR item — WordPress overloads this single
 * element for both categories and tags (and any custom taxonomy), told
 * apart by `domain` (`category`, `post_tag`, or a plugin's own taxonomy
 * name). `nicename` is the slug; `name` is the display text a later
 * importer stage decides whether to keep.
 */
final class WxrCategory
{
    public function __construct(
        public readonly string $domain,
        public readonly string $nicename,
        public readonly string $name,
    ) {
    }
}
