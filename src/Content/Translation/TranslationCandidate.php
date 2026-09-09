<?php

declare(strict_types=1);

namespace Cuniform\Content\Translation;

/**
 * One document's grouping-relevant facts (SPEC §7.3): its language, its
 * translation_key (null means "no translations", which is normal, not an
 * error), and an identifier used only for error messages and as the group's
 * per-language value — a slug or path, whichever the caller finds useful.
 */
final class TranslationCandidate
{
    public function __construct(
        public readonly string $language,
        public readonly ?string $translationKey,
        public readonly string $identifier,
    ) {
    }
}
