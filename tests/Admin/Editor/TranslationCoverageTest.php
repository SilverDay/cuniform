<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Editor;

use Cuniform\Admin\Editor\DocumentSummary;
use Cuniform\Admin\Editor\TranslationCoverage;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use PHPUnit\Framework\TestCase;

final class TranslationCoverageTest extends TestCase
{
    public function testADocumentWithNoTranslationKeyIsUntranslated(): void
    {
        $doc = $this->summary('posts/en/2026/a.md', 'en', null);

        self::assertSame(['posts/en/2026/a.md' => false], TranslationCoverage::compute([$doc]));
    }

    public function testADocumentWithATranslationKeyButNoSiblingIsUntranslated(): void
    {
        $doc = $this->summary('posts/en/2026/a.md', 'en', 'lonely-key');

        self::assertSame(['posts/en/2026/a.md' => false], TranslationCoverage::compute([$doc]));
    }

    public function testTwoDocumentsSharingAKeyInDifferentLanguagesAreBothTranslated(): void
    {
        $en = $this->summary('posts/en/2026/a.md', 'en', 'shared-key');
        $de = $this->summary('posts/de/2026/a.md', 'de', 'shared-key');

        $coverage = TranslationCoverage::compute([$en, $de]);

        self::assertTrue($coverage['posts/en/2026/a.md']);
        self::assertTrue($coverage['posts/de/2026/a.md']);
    }

    public function testTwoDocumentsSharingAKeyInTheSameLanguageAreNotConsideredTranslated(): void
    {
        // Not a realistic corpus (SPEC §7.3 forbids a duplicate key within
        // one language, caught elsewhere) — but this class only counts
        // *languages*, not document counts, so two same-language entries
        // sharing a key still resolve to "one language has it."
        $a = $this->summary('posts/en/2026/a.md', 'en', 'dup-key');
        $b = $this->summary('posts/en/2026/b.md', 'en', 'dup-key');

        $coverage = TranslationCoverage::compute([$a, $b]);

        self::assertFalse($coverage['posts/en/2026/a.md']);
        self::assertFalse($coverage['posts/en/2026/b.md']);
    }

    private function summary(string $identifier, string $language, ?string $translationKey): DocumentSummary
    {
        return new DocumentSummary($identifier, DocumentKind::Post, $language, 'Title', 'slug', DocumentStatus::Published, $translationKey, 0);
    }
}
