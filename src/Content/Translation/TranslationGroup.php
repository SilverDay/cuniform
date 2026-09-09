<?php

declare(strict_types=1);

namespace Cuniform\Content\Translation;

final class TranslationGroup
{
    /**
     * @param array<string, string> $identifiersByLanguage One entry per language
     *                                                      that has a document for
     *                                                      this key — absent
     *                                                      languages have no
     *                                                      translation, which is
     *                                                      normal (SPEC §7.3).
     */
    public function __construct(
        public readonly string $translationKey,
        public readonly array $identifiersByLanguage,
    ) {
    }
}
