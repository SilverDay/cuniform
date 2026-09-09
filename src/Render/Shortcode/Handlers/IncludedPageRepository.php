<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode\Handlers;

/**
 * How IncludeHandler resolves `page="slug"` to actual content (SPEC §6.5).
 * No concrete implementation exists yet — it needs the whole-corpus content
 * index the discover/resolve build stages build (T10-T12, T19), which come
 * after this task. IncludeHandler depends on this interface, not a concrete
 * repository, exactly so it can be fully implemented and tested now against
 * a fake, and wired to the real thing later without changing this handler.
 */
interface IncludedPageRepository
{
    public function find(string $slug, string $language): ?IncludedPage;
}
