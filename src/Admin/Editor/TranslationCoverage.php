<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

/**
 * Which documents have a translation and which don't (SPEC §13.3's
 * dashboard "untranslated-document count," and the post list's "filterable
 * by ... translation status") — one pass over a DocumentSummary listing,
 * the same shared definition both screens read from rather than two
 * independently-written notions of "translated" drifting apart.
 *
 * "Has a translation" means: carries a `translation_key` (§7.3) *and* at
 * least one other document in a different language shares it. A document
 * with no `translation_key` at all is untranslated by definition (most
 * posts, §7.3: "a key present in only one language is normal"), same as
 * one whose key nothing else currently shares.
 */
final class TranslationCoverage
{
    /**
     * @param  list<DocumentSummary> $documents
     * @return array<string, bool>   Keyed by DocumentSummary::$identifier.
     */
    public static function compute(array $documents): array
    {
        /** @var array<string, array<string, true>> $languagesByKey */
        $languagesByKey = [];
        foreach ($documents as $document) {
            if ($document->translationKey === null) {
                continue;
            }

            $languagesByKey[$document->translationKey][$document->language] = true;
        }

        $coverage = [];
        foreach ($documents as $document) {
            $languages = $document->translationKey === null
                ? []
                : ($languagesByKey[$document->translationKey] ?? []);

            $coverage[$document->identifier] = count($languages) > 1;
        }

        return $coverage;
    }
}
