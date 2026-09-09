<?php

declare(strict_types=1);

namespace Cuniform\Tests\Template;

use Cuniform\I18n\HreflangEntry;
use Cuniform\I18n\HreflangSet;
use Cuniform\I18n\UiStringCatalogue;
use Cuniform\Template\LayoutContext;
use Cuniform\Template\NavItem;
use Cuniform\Template\PageViewModel;
use Cuniform\Template\PostViewModel;
use Cuniform\Template\TemplateRenderer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Template/helpers.php';

final class TemplateRendererTest extends TestCase
{
    private const TEMPLATES_DIR = __DIR__ . '/../../templates';

    private string $langDir;

    protected function setUp(): void
    {
        $this->langDir = sys_get_temp_dir() . '/cuniform_tpl_' . uniqid();
        mkdir($this->langDir, 0o755, true);
        file_put_contents(
            $this->langDir . '/de.php',
            "<?php\nreturn ['updated_on' => 'Aktualisiert am'];\n"
        );
    }

    protected function tearDown(): void
    {
        unlink($this->langDir . '/de.php');
        rmdir($this->langDir);
    }

    public function testRendersAPostWithTagsAndDate(): void
    {
        $renderer = new TemplateRenderer();
        $doc      = $this->postViewModel();

        $html = $renderer->render(self::TEMPLATES_DIR . '/post.php', $doc);

        self::assertStringContainsString('<h1>Sicherheitskultur</h1>', $html);
        self::assertStringContainsString('14. März 2026', $html);
        self::assertStringContainsString('<li>awareness</li>', $html);
        self::assertStringContainsString('<p>Body content.</p>', $html);
    }

    public function testEscapesTitleAndTags(): void
    {
        $renderer = new TemplateRenderer();
        $doc      = $this->postViewModel(title: '<script>1</script>', tags: ['<b>x</b>']);

        $html = $renderer->render(self::TEMPLATES_DIR . '/post.php', $doc);

        self::assertStringNotContainsString('<script>1</script>', $html);
        self::assertStringContainsString('&lt;script&gt;1&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }

    public function testShowsUpdatedWhenPresent(): void
    {
        $renderer = new TemplateRenderer();
        $doc      = $this->postViewModel(formattedUpdated: '20. März 2026');

        $html = $renderer->render(self::TEMPLATES_DIR . '/post.php', $doc);

        self::assertStringContainsString('Aktualisiert am 20. März 2026', $html);
    }

    public function testTocPartialRendersOnlyWhenFlagIsSet(): void
    {
        $renderer = new TemplateRenderer();

        $withToc = $this->postViewModel(toc: true, headings: [
            ['level' => 2, 'id' => 'details', 'text' => 'Details'],
        ]);
        $htmlWithToc = $renderer->render(self::TEMPLATES_DIR . '/post.php', $withToc);
        self::assertStringContainsString('class="toc"', $htmlWithToc);
        self::assertStringContainsString('href="#details"', $htmlWithToc);

        $withoutToc = $this->postViewModel(toc: false, headings: [
            ['level' => 2, 'id' => 'details', 'text' => 'Details'],
        ]);
        $htmlWithoutToc = $renderer->render(self::TEMPLATES_DIR . '/post.php', $withoutToc);
        self::assertStringNotContainsString('class="toc"', $htmlWithoutToc);
    }

    public function testRendersAPage(): void
    {
        $renderer = new TemplateRenderer();
        $strings  = UiStringCatalogue::load($this->langDir, ['de']);
        $doc      = new PageViewModel(
            'de',
            'Impressum',
            'Anbieterkennzeichnung',
            'https://blog.silverday.de/de/impressum/',
            null,
            $strings,
            '<p>Angaben gemäß § 5 DDG.</p>',
            false,
            false,
            []
        );

        $html = $renderer->render(self::TEMPLATES_DIR . '/page.php', $doc);

        self::assertStringContainsString('<h1>Impressum</h1>', $html);
        self::assertStringContainsString('<p>Angaben gemäß § 5 DDG.</p>', $html);
    }

    public function testLayoutWrapsRenderedContentWithHeadNavAndFooter(): void
    {
        $renderer = new TemplateRenderer();
        $doc      = $this->postViewModel();
        $inner    = $renderer->render(self::TEMPLATES_DIR . '/post.php', $doc);

        $layout = new LayoutContext(
            $doc,
            $inner,
            'SilverDay',
            [new NavItem('Blog', '/de/')],
            [new NavItem('Impressum', '/de/impressum/')]
        );

        $html = $renderer->render(self::TEMPLATES_DIR . '/layout.php', $layout);

        self::assertStringStartsWith('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<html lang="de">', $html);
        self::assertStringContainsString('<title>Sicherheitskultur — SilverDay</title>', $html);
        self::assertStringContainsString('nav-primary', $html);
        self::assertStringContainsString('href="/de/impressum/"', $html);
        self::assertStringContainsString('<h1>Sicherheitskultur</h1>', $html);
        self::assertStringNotContainsString('%%CFSC', $html);
    }

    public function testSingleLanguageSiteEmitsNoLangSwitcherOrHreflangLinks(): void
    {
        $renderer = new TemplateRenderer();
        $doc      = $this->postViewModel(hreflang: null);
        $inner    = $renderer->render(self::TEMPLATES_DIR . '/post.php', $doc);
        $layout   = new LayoutContext($doc, $inner, 'SilverDay');

        $html = $renderer->render(self::TEMPLATES_DIR . '/layout.php', $layout);

        self::assertStringNotContainsString('lang-switcher', $html);
        self::assertStringNotContainsString('rel="alternate"', $html);
        self::assertStringNotContainsString('og:locale', $html);
    }

    public function testMultiLanguageSiteRendersSelfReferencingHreflangAndSwitcher(): void
    {
        $renderer = new TemplateRenderer();
        $hreflang = new HreflangSet([
            new HreflangEntry('de', 'https://blog.silverday.de/de/sicherheitskultur/'),
            new HreflangEntry('en', 'https://blog.silverday.de/en/security-culture/'),
            new HreflangEntry('x-default', 'https://blog.silverday.de/en/'),
        ], 'https://blog.silverday.de/de/sicherheitskultur/');
        $doc    = $this->postViewModel(hreflang: $hreflang);
        $inner  = $renderer->render(self::TEMPLATES_DIR . '/post.php', $doc);
        $layout = new LayoutContext($doc, $inner, 'SilverDay');

        $html = $renderer->render(self::TEMPLATES_DIR . '/layout.php', $layout);

        self::assertStringContainsString('hreflang="de" href="https://blog.silverday.de/de/sicherheitskultur/"', $html);
        self::assertStringContainsString('hreflang="en" href="https://blog.silverday.de/en/security-culture/"', $html);
        self::assertStringContainsString('hreflang="x-default"', $html);
        self::assertStringContainsString('lang-switcher', $html);
        self::assertStringContainsString('aria-current="true"', $html);
    }

    /**
     * @param list<string>                                      $tags
     * @param list<array{level: int, id: string, text: string}> $headings
     */
    private function postViewModel(
        string $title = 'Sicherheitskultur',
        array $tags = ['awareness'],
        ?string $formattedUpdated = null,
        bool $toc = false,
        array $headings = [],
        HreflangSet|false|null $hreflang = false,
    ): PostViewModel {
        $strings = UiStringCatalogue::load($this->langDir, ['de']);

        return new PostViewModel(
            'de',
            $title,
            'Warum Kultur wichtiger ist als Compliance.',
            'https://blog.silverday.de/de/sicherheitskultur/',
            $hreflang === false ? new HreflangSet([new HreflangEntry('de', 'https://blog.silverday.de/de/sicherheitskultur/')], 'https://blog.silverday.de/de/sicherheitskultur/') : $hreflang,
            $strings,
            '<p>Body content.</p>',
            '14. März 2026',
            $formattedUpdated,
            $tags,
            null,
            false,
            $toc,
            $headings
        );
    }
}
