<?php

declare(strict_types=1);

namespace Cuniform\Tests\I18n;

use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\I18n\HreflangSetBuilder;
use Cuniform\I18n\TranslatedDocument;
use PHPUnit\Framework\TestCase;

final class HreflangSetBuilderTest extends TestCase
{
    public function testSingleLanguageSiteEmitsNothing(): void
    {
        $builder = new HreflangSetBuilder(['en'], 'https://example.test/en/');
        $current = new TranslatedDocument('en', 'https://example.test/en/post/', DocumentStatus::Published);

        self::assertNull($builder->build($current, []));
    }

    public function testAlwaysSelfReferencesRegardlessOfOtherTranslations(): void
    {
        $builder = new HreflangSetBuilder(['de', 'en'], 'https://example.test/en/');
        $current = new TranslatedDocument('de', 'https://example.test/de/post/', DocumentStatus::Published);

        $set = $builder->build($current, []);

        self::assertNotNull($set);
        $languages = array_map(static fn ($e) => $e->hreflang, $set->alternates);
        self::assertContains('de', $languages);
    }

    public function testIncludesAPublishedOtherTranslation(): void
    {
        $builder = new HreflangSetBuilder(['de', 'en'], 'https://example.test/en/');
        $current = new TranslatedDocument('de', 'https://example.test/de/sicherheitskultur/', DocumentStatus::Published);
        $other   = new TranslatedDocument('en', 'https://example.test/en/security-culture/', DocumentStatus::Published);

        $set = $builder->build($current, [$other]);

        self::assertNotNull($set);
        $byLang = [];
        foreach ($set->alternates as $entry) {
            $byLang[$entry->hreflang] = $entry->url;
        }

        self::assertSame('https://example.test/de/sicherheitskultur/', $byLang['de']);
        self::assertSame('https://example.test/en/security-culture/', $byLang['en']);
        self::assertSame('https://example.test/en/', $byLang['x-default']);
    }

    public function testExcludesADraftOtherTranslation(): void
    {
        $builder = new HreflangSetBuilder(['de', 'en'], 'https://example.test/en/');
        $current = new TranslatedDocument('de', 'https://example.test/de/x/', DocumentStatus::Published);
        $draft   = new TranslatedDocument('en', 'https://example.test/en/x/', DocumentStatus::Draft);

        $set = $builder->build($current, [$draft]);

        self::assertNotNull($set);
        $languages = array_map(static fn ($e) => $e->hreflang, $set->alternates);
        self::assertNotContains('en', $languages);
    }

    public function testExcludesAScheduledOtherTranslation(): void
    {
        $builder = new HreflangSetBuilder(['de', 'en'], 'https://example.test/en/');
        $current = new TranslatedDocument('de', 'https://example.test/de/x/', DocumentStatus::Published);
        $scheduled = new TranslatedDocument('en', 'https://example.test/en/x/', DocumentStatus::Scheduled);

        $set = $builder->build($current, [$scheduled]);

        self::assertNotNull($set);
        $languages = array_map(static fn ($e) => $e->hreflang, $set->alternates);
        self::assertNotContains('en', $languages);
    }

    public function testCanonicalIsTheCurrentDocumentsOwnUrl(): void
    {
        $builder = new HreflangSetBuilder(['de', 'en'], 'https://example.test/en/');
        $current = new TranslatedDocument('de', 'https://example.test/de/x/', DocumentStatus::Published);

        $set = $builder->build($current, []);

        self::assertNotNull($set);
        self::assertSame('https://example.test/de/x/', $set->canonical);
    }

    public function testXDefaultPointsAtTheDefaultLanguageHomeNotASpecificTranslation(): void
    {
        $builder = new HreflangSetBuilder(['de', 'en'], 'https://example.test/en/');
        $current = new TranslatedDocument('de', 'https://example.test/de/sicherheitskultur/', DocumentStatus::Published);
        $other   = new TranslatedDocument('en', 'https://example.test/en/security-culture/', DocumentStatus::Published);

        $set = $builder->build($current, [$other]);

        self::assertNotNull($set);
        $xDefault = array_values(array_filter($set->alternates, static fn ($e) => $e->hreflang === 'x-default'));
        self::assertCount(1, $xDefault);
        self::assertSame('https://example.test/en/', $xDefault[0]->url);
    }
}
