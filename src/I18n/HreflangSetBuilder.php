<?php

declare(strict_types=1);

namespace Cuniform\I18n;

use Cuniform\Content\FrontMatter\DocumentStatus;

/**
 * Builds one page's hreflang set (SPEC §7.5). The self-reference is always
 * present regardless of $current's own status — calling this at all implies
 * $current is being rendered into this build (a draft is never rendered
 * into a release at all, per §5.6, so this is never called for one).
 */
final class HreflangSetBuilder
{
    /**
     * @param list<string> $languages Configured languages — a single-language
     *                                 site emits none of this at all (§7.5).
     */
    public function __construct(
        private readonly array $languages,
        private readonly string $defaultLanguageHomeUrl,
    ) {
    }

    /**
     * @param list<TranslatedDocument> $otherTranslations The rest of $current's
     *                                  translation group — not including $current
     *                                  itself. Only Published members are advertised.
     */
    public function build(TranslatedDocument $current, array $otherTranslations): ?HreflangSet
    {
        if (count($this->languages) <= 1) {
            return null;
        }

        $alternates = [new HreflangEntry($current->language, $current->url)];
        $seen       = [$current->language => true];

        foreach ($otherTranslations as $translation) {
            if ($translation->status !== DocumentStatus::Published) {
                continue;
            }

            if (isset($seen[$translation->language])) {
                continue;
            }

            $seen[$translation->language]  = true;
            $alternates[]                  = new HreflangEntry($translation->language, $translation->url);
        }

        $alternates[] = new HreflangEntry('x-default', $this->defaultLanguageHomeUrl);

        return new HreflangSet($alternates, $current->url);
    }
}
