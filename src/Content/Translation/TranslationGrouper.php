<?php

declare(strict_types=1);

namespace Cuniform\Content\Translation;

use Cuniform\Content\ContentException;

/**
 * Groups documents by translation_key across languages (SPEC §7.3). Not by
 * filename or slug — slugs must differ across languages (§5.4), so linking
 * by slug would be self-defeating.
 */
final class TranslationGrouper
{
    /**
     * A candidate with no translation_key is simply left out of every
     * group — that is normal, not a warning; most documents will have none.
     *
     * @param  list<TranslationCandidate> $candidates
     * @return list<TranslationGroup>
     *
     * @throws ContentException When the same key appears twice within one language.
     */
    public function group(array $candidates): array
    {
        /** @var array<string, array<string, string>> $byKey */
        $byKey = [];

        foreach ($candidates as $candidate) {
            if ($candidate->translationKey === null) {
                continue;
            }

            $key      = $candidate->translationKey;
            $language = $candidate->language;

            if (isset($byKey[$key][$language])) {
                throw ContentException::duplicateTranslationKey(
                    $key,
                    $language,
                    $byKey[$key][$language],
                    $candidate->identifier
                );
            }

            $byKey[$key][$language] = $candidate->identifier;
        }

        $groups = [];
        foreach ($byKey as $key => $identifiersByLanguage) {
            $groups[] = new TranslationGroup($key, $identifiersByLanguage);
        }

        return $groups;
    }
}
