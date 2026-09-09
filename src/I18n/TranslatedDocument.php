<?php

declare(strict_types=1);

namespace Cuniform\I18n;

use Cuniform\Content\FrontMatter\DocumentStatus;

/**
 * One member of a translation group, as HreflangSetBuilder needs it: enough
 * to place it in the alternates list and decide whether it's advertised.
 */
final class TranslatedDocument
{
    public function __construct(
        public readonly string $language,
        public readonly string $url,
        public readonly DocumentStatus $status,
    ) {
    }
}
