<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * What BuildCache persists per document (SPEC §10.2) — everything needed
 * to skip both Render (stage 5) and that document's own Template (stage 6)
 * work on a later build: the rendered body (for feeds/search-index, which
 * always run in full — see ArtifactStage) and the already-assembled final
 * page (post.php/page.php wrapped in layout.php), reused byte-for-byte
 * rather than re-templated. `translationKey` is carried so a later build
 * can detect a translation group's membership changing without re-parsing
 * every document that *didn't* change.
 */
final class CachedDocument
{
    /**
     * @param list<array{level: int, id: string, text: string}> $headings
     */
    public function __construct(
        public readonly string $baseKey,
        public readonly ?string $translationKey,
        public readonly string $bodyHtml,
        public readonly array $headings,
        public readonly string $pageHtml,
        public readonly string $routePath,
    ) {
    }
}
