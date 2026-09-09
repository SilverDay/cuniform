<?php

declare(strict_types=1);

namespace Cuniform\Tests\Content\Translation;

use Cuniform\Content\ContentException;
use Cuniform\Content\Translation\TranslationCandidate;
use Cuniform\Content\Translation\TranslationGrouper;
use PHPUnit\Framework\TestCase;

final class TranslationGrouperTest extends TestCase
{
    public function testGroupsDocumentsAcrossLanguagesBySharedKey(): void
    {
        $groups = (new TranslationGrouper())->group([
            new TranslationCandidate('de', '2026-security-culture', 'sicherheitskultur'),
            new TranslationCandidate('en', '2026-security-culture', 'security-culture'),
        ]);

        self::assertCount(1, $groups);
        self::assertSame('2026-security-culture', $groups[0]->translationKey);
        self::assertSame(
            ['de' => 'sicherheitskultur', 'en' => 'security-culture'],
            $groups[0]->identifiersByLanguage
        );
    }

    public function testDocumentWithNoTranslationKeyIsNotGrouped(): void
    {
        $groups = (new TranslationGrouper())->group([
            new TranslationCandidate('de', null, 'solo-post'),
        ]);

        self::assertSame([], $groups);
    }

    public function testKeyPresentInOnlyOneLanguageIsNormalNotAnError(): void
    {
        $groups = (new TranslationGrouper())->group([
            new TranslationCandidate('de', '2026-only-german', 'nur-deutsch'),
        ]);

        self::assertCount(1, $groups);
        self::assertSame(['de' => 'nur-deutsch'], $groups[0]->identifiersByLanguage);
    }

    public function testDuplicateKeyWithinSameLanguageThrows(): void
    {
        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches("/'2026-dup' appears twice within language 'de'/");

        (new TranslationGrouper())->group([
            new TranslationCandidate('de', '2026-dup', 'first-post'),
            new TranslationCandidate('de', '2026-dup', 'second-post'),
        ]);
    }

    public function testSameKeyAcrossLanguagesIsNotADuplicate(): void
    {
        // A German and an English document sharing a key is the intended
        // grouping, not a duplicate — only *within* one language is an error.
        $groups = (new TranslationGrouper())->group([
            new TranslationCandidate('de', 'shared', 'de-post'),
            new TranslationCandidate('en', 'shared', 'en-post'),
            new TranslationCandidate('fr', 'shared', 'fr-post'),
        ]);

        self::assertCount(1, $groups);
        self::assertSame(
            ['de' => 'de-post', 'en' => 'en-post', 'fr' => 'fr-post'],
            $groups[0]->identifiersByLanguage
        );
    }

    public function testMultipleIndependentGroups(): void
    {
        $groups = (new TranslationGrouper())->group([
            new TranslationCandidate('de', 'key-a', 'a-de'),
            new TranslationCandidate('en', 'key-a', 'a-en'),
            new TranslationCandidate('de', 'key-b', 'b-de'),
        ]);

        self::assertCount(2, $groups);
        $byKey = [];
        foreach ($groups as $group) {
            $byKey[$group->translationKey] = $group->identifiersByLanguage;
        }

        self::assertSame(['de' => 'a-de', 'en' => 'a-en'], $byKey['key-a']);
        self::assertSame(['de' => 'b-de'], $byKey['key-b']);
    }

    public function testEmptyCandidateListProducesNoGroups(): void
    {
        self::assertSame([], (new TranslationGrouper())->group([]));
    }
}
