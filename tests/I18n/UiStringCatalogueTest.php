<?php

declare(strict_types=1);

namespace Cuniform\Tests\I18n;

use Cuniform\Build\BuildException;
use Cuniform\Config\ConfigException;
use Cuniform\I18n\UiStringCatalogue;
use PHPUnit\Framework\TestCase;

final class UiStringCatalogueTest extends TestCase
{
    private string $langDir;

    protected function setUp(): void
    {
        $this->langDir = sys_get_temp_dir() . '/cuniform_lang_' . uniqid();
        mkdir($this->langDir, 0o755, true);
    }

    protected function tearDown(): void
    {
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

    public function testLoadsAndReturnsStringsPerLanguage(): void
    {
        $this->writeLang('de', ['read_more' => 'Weiterlesen']);
        $this->writeLang('en', ['read_more' => 'Read more']);

        $catalogue = UiStringCatalogue::load($this->langDir, ['de', 'en']);

        self::assertSame('Weiterlesen', $catalogue->get('de', 'read_more'));
        self::assertSame('Read more', $catalogue->get('en', 'read_more'));
    }

    public function testMissingKeyInOneLanguageIsABuildError(): void
    {
        $this->writeLang('de', ['read_more' => 'Weiterlesen', 'published_on' => 'Veröffentlicht am']);
        $this->writeLang('en', ['read_more' => 'Read more']);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessageMatches("/missing UI string 'published_on' for language 'en'/");
        UiStringCatalogue::load($this->langDir, ['de', 'en']);
    }

    public function testDoesNotFallBackToTheDefaultLanguage(): void
    {
        // A missing key must fail the build, never silently resolve from
        // another language's catalogue (SPEC §7.8).
        $this->writeLang('de', ['only_in_de' => 'x']);
        $this->writeLang('en', []);

        $this->expectException(BuildException::class);
        UiStringCatalogue::load($this->langDir, ['de', 'en']);
    }

    public function testSingleLanguageSiteStillLoadsAndUsesACatalogue(): void
    {
        $this->writeLang('en', ['read_more' => 'Read more']);

        $catalogue = UiStringCatalogue::load($this->langDir, ['en']);

        self::assertSame('Read more', $catalogue->get('en', 'read_more'));
    }

    public function testMissingLanguageFileIsAConfigException(): void
    {
        $this->writeLang('de', ['x' => 'y']);
        // 'en' file intentionally not written.

        $this->expectException(ConfigException::class);
        UiStringCatalogue::load($this->langDir, ['de', 'en']);
    }

    public function testFileNotReturningAnArrayIsAConfigException(): void
    {
        file_put_contents($this->langDir . '/de.php', "<?php\nreturn 'not an array';\n");

        $this->expectException(ConfigException::class);
        UiStringCatalogue::load($this->langDir, ['de']);
    }

    public function testNonStringValueIsAConfigException(): void
    {
        file_put_contents($this->langDir . '/de.php', "<?php\nreturn ['count' => 5];\n");

        $this->expectException(ConfigException::class);
        UiStringCatalogue::load($this->langDir, ['de']);
    }

    public function testGetRejectsALanguageNotInTheCatalogue(): void
    {
        $this->writeLang('de', ['x' => 'y']);
        $catalogue = UiStringCatalogue::load($this->langDir, ['de']);

        $this->expectException(ConfigException::class);
        $catalogue->get('fr', 'x');
    }

    public function testGetRejectsAnUnknownKey(): void
    {
        $this->writeLang('de', ['x' => 'y']);
        $catalogue = UiStringCatalogue::load($this->langDir, ['de']);

        $this->expectException(ConfigException::class);
        $catalogue->get('de', 'does_not_exist');
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
