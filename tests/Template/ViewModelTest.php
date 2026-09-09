<?php

declare(strict_types=1);

namespace Cuniform\Tests\Template;

use Cuniform\I18n\HreflangEntry;
use Cuniform\I18n\HreflangSet;
use Cuniform\I18n\UiStringCatalogue;
use Cuniform\Template\ViewModel;
use PHPUnit\Framework\TestCase;

final class ViewModelTest extends TestCase
{
    private string $langDir;

    protected function setUp(): void
    {
        $this->langDir = sys_get_temp_dir() . '/cuniform_viewmodel_' . uniqid();
        mkdir($this->langDir, 0o755, true);
        file_put_contents($this->langDir . '/de.php', "<?php\nreturn ['read_more' => 'Weiterlesen'];\n");
    }

    protected function tearDown(): void
    {
        unlink($this->langDir . '/de.php');
        rmdir($this->langDir);
    }

    public function testExposesLanguageCanonicalAndHreflangDirectly(): void
    {
        $hreflang = new HreflangSet([new HreflangEntry('de', '/de/x/')], '/de/x/');
        $view     = $this->makeViewModel('de', '/de/x/', $hreflang);

        self::assertSame('de', $view->language);
        self::assertSame('/de/x/', $view->canonicalUrl);
        self::assertSame($hreflang, $view->hreflang);
    }

    public function testHreflangIsNullableForASingleLanguageSite(): void
    {
        $view = $this->makeViewModel('de', '/de/x/', null);

        self::assertNull($view->hreflang);
    }

    public function testTDelegatesToTheCatalogueForItsOwnLanguage(): void
    {
        $view = $this->makeViewModel('de', '/de/x/', null);

        self::assertSame('Weiterlesen', $view->t('read_more'));
    }

    private function makeViewModel(string $language, string $canonicalUrl, ?HreflangSet $hreflang): ViewModel
    {
        $strings = UiStringCatalogue::load($this->langDir, ['de']);

        return new class ($language, 'Title', 'Summary', $canonicalUrl, $hreflang, $strings, false, false, []) extends ViewModel {
        };
    }
}
