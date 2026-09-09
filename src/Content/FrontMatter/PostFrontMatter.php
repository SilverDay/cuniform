<?php

declare(strict_types=1);

namespace Cuniform\Content\FrontMatter;

/**
 * A post's front matter (SPEC §5.2 shared keys + §5.3 post-only keys) plus its
 * Markdown body, with the front matter block already stripped (§4.6 guard note:
 * front matter is never passed to the renderer).
 */
final class PostFrontMatter
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public readonly SharedFrontMatter $shared,
        public readonly \DateTimeImmutable $date,
        public readonly array $tags,
        public readonly ?string $series,
        public readonly string $body,
    ) {
    }
}
