<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;

/**
 * One row of the editor's document list / translation group (SPEC §12,
 * §13.3) — enough to render a listing and detect a forgotten translation
 * without parsing every document's full front matter a second time.
 */
final class DocumentSummary
{
    public function __construct(
        public readonly string $identifier,
        public readonly DocumentKind $kind,
        public readonly string $language,
        public readonly string $title,
        public readonly string $slug,
        public readonly DocumentStatus $status,
        public readonly ?string $translationKey,
        public readonly int $mtime,
    ) {
    }
}
