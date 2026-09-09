<?php

declare(strict_types=1);

namespace Cuniform\Tests\I18n;

use Cuniform\I18n\DateFormatter;
use Cuniform\I18n\UiStringCatalogue;
use PHPUnit\Framework\TestCase;

final class DateFormatterTest extends TestCase
{
    private string $langDir;

    protected function setUp(): void
    {
        if (!extension_loaded('intl')) {
            self::markTestSkipped('ext-intl not available; the intl-path tests need it.');
        }

        $this->langDir = sys_get_temp_dir() . '/cuniform_dateformat_' . uniqid();
        mkdir($this->langDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->langDir)) {
            return;
        }

        $items = scandir($this->langDir);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..') {
                    unlink($this->langDir . '/' . $item);
                }
            }
        }

        rmdir($this->langDir);
    }

    public function testFormatsAGermanDateWithTheTrailingPeriodAfterTheDay(): void
    {
        $formatter = new DateFormatter($this->catalogue());

        self::assertSame('14. März 2026', $formatter->format(new \DateTimeImmutable('2026-03-14'), 'de'));
    }

    public function testFormatsAnEnglishDateDayFirstWithoutAPeriod(): void
    {
        $formatter = new DateFormatter($this->catalogue());

        self::assertSame('14 March 2026', $formatter->format(new \DateTimeImmutable('2026-03-14'), 'en'));
    }

    public function testFallbackFormatsGermanWithMonthNameFromTheCatalogueAndATrailingPeriod(): void
    {
        $formatter = new DateFormatter($this->catalogue(), forceFallback: true);

        self::assertSame('14. März 2026', $formatter->format(new \DateTimeImmutable('2026-03-14'), 'de'));
    }

    public function testFallbackFormatsEnglishWithMonthNameFromTheCatalogueAndNoPeriod(): void
    {
        $formatter = new DateFormatter($this->catalogue(), forceFallback: true);

        self::assertSame('14 March 2026', $formatter->format(new \DateTimeImmutable('2026-03-14'), 'en'));
    }

    public function testFallbackUsesTheCorrectMonthForEveryMonthNumber(): void
    {
        $formatter = new DateFormatter($this->catalogue(), forceFallback: true);

        self::assertSame('1 January 2026', $formatter->format(new \DateTimeImmutable('2026-01-01'), 'en'));
        self::assertSame('25 December 2026', $formatter->format(new \DateTimeImmutable('2026-12-25'), 'en'));
    }

    private function catalogue(): UiStringCatalogue
    {
        $deMonths = [
            'month_01' => 'Januar', 'month_02' => 'Februar', 'month_03' => 'März',
            'month_04' => 'April', 'month_05' => 'Mai', 'month_06' => 'Juni',
            'month_07' => 'Juli', 'month_08' => 'August', 'month_09' => 'September',
            'month_10' => 'Oktober', 'month_11' => 'November', 'month_12' => 'Dezember',
        ];
        $enMonths = [
            'month_01' => 'January', 'month_02' => 'February', 'month_03' => 'March',
            'month_04' => 'April', 'month_05' => 'May', 'month_06' => 'June',
            'month_07' => 'July', 'month_08' => 'August', 'month_09' => 'September',
            'month_10' => 'October', 'month_11' => 'November', 'month_12' => 'December',
        ];

        $this->writeLang('de', $deMonths);
        $this->writeLang('en', $enMonths);

        return UiStringCatalogue::load($this->langDir, ['de', 'en']);
    }

    /**
     * @param array<string, string> $strings
     */
    private function writeLang(string $language, array $strings): void
    {
        $export = var_export($strings, true);
        file_put_contents($this->langDir . "/{$language}.php", "<?php\nreturn {$export};\n");
    }
}
