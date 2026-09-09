<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\BuildCacheKey;
use Cuniform\Template\NavItem;
use PHPUnit\Framework\TestCase;

final class BuildCacheKeyTest extends TestCase
{
    private string $templatesDir;
    private string $langDir;

    protected function setUp(): void
    {
        $this->templatesDir = sys_get_temp_dir() . '/cuniform_key_tpl_' . uniqid();
        $this->langDir      = sys_get_temp_dir() . '/cuniform_key_lang_' . uniqid();
        mkdir($this->templatesDir, 0o755, true);
        mkdir($this->langDir, 0o755, true);
        file_put_contents($this->templatesDir . '/layout.php', '<?php // v1');
        file_put_contents($this->langDir . '/en.php', "<?php\nreturn [];\n");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->templatesDir);
        $this->removeDirectory($this->langDir);
    }

    public function testSameFileHashAndEnvironmentProducesTheSameKey(): void
    {
        $a = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);
        $b = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);

        self::assertSame($a->forDocument('abc123'), $b->forDocument('abc123'));
    }

    public function testDifferentFileHashProducesADifferentKey(): void
    {
        $key = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);

        self::assertNotSame($key->forDocument('abc123'), $key->forDocument('xyz789'));
    }

    public function testChangingATemplateFileChangesEveryDocumentsKey(): void
    {
        $before = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);
        $beforeKey = $before->forDocument('abc123');

        file_put_contents($this->templatesDir . '/layout.php', '<?php // v2');
        $after = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);

        self::assertNotSame($beforeKey, $after->forDocument('abc123'));
    }

    public function testChangingAStaticAssetInTemplatesChangesEveryDocumentsKey(): void
    {
        // style.css/search.js are referenced by every cached page's <head> —
        // a changed asset must invalidate cached pages too (BuildCacheKey's
        // own docblock explains why).
        $before = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);
        $beforeKey = $before->forDocument('abc123');

        file_put_contents($this->templatesDir . '/style.css', 'body{color:red}');
        $after = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);

        self::assertNotSame($beforeKey, $after->forDocument('abc123'));
    }

    public function testChangingAUiStringFileChangesEveryDocumentsKey(): void
    {
        $before = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);
        $beforeKey = $before->forDocument('abc123');

        file_put_contents($this->langDir . '/en.php', "<?php\nreturn ['x' => 'y'];\n");
        $after = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);

        self::assertNotSame($beforeKey, $after->forDocument('abc123'));
    }

    public function testChangingConfigTitleChangesEveryDocumentsKey(): void
    {
        $before = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);
        $after  = new BuildCacheKey(ConfigFixture::make(baseUrl: 'https://example.test'), $this->templatesDir, $this->langDir);

        self::assertNotSame($before->forDocument('abc123'), $after->forDocument('abc123'));
    }

    public function testNavHashIsStableForTheSameTree(): void
    {
        $key = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);
        $nav = ['en' => ['primary' => [new NavItem('About', '/en/about/')], 'footer' => []]];

        self::assertSame($key->navHash($nav), $key->navHash($nav));
    }

    public function testNavHashChangesWhenTheTreeChanges(): void
    {
        $key = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);

        $before = $key->navHash(['en' => ['primary' => [new NavItem('About', '/en/about/')], 'footer' => []]]);
        $after  = $key->navHash(['en' => ['primary' => [new NavItem('About', '/en/about/'), new NavItem('Talks', '/en/talks/')], 'footer' => []]]);

        self::assertNotSame($before, $after);
    }

    public function testNavHashIsOrderIndependentAcrossLanguages(): void
    {
        $key = new BuildCacheKey(ConfigFixture::make(), $this->templatesDir, $this->langDir);

        $a = ['en' => ['primary' => [], 'footer' => []], 'de' => ['primary' => [], 'footer' => []]];
        $b = ['de' => ['primary' => [], 'footer' => []], 'en' => ['primary' => [], 'footer' => []]];

        self::assertSame($key->navHash($a), $key->navHash($b));
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}
