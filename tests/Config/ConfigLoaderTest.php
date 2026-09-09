<?php

declare(strict_types=1);

namespace Cuniform\Tests\Config;

use Cuniform\Config\ConfigException;
use Cuniform\Config\ConfigLoader;
use Cuniform\Config\UrlPrefix;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadReadsAValidFileFromDisk(): void
    {
        $config = (new ConfigLoader())->load(__DIR__ . '/../fixtures/Config/valid.php');

        self::assertSame('https://blog.silverday.de', $config->baseUrl);
        self::assertSame(['de', 'en'], $config->languages);
        self::assertSame('de', $config->defaultLanguage);
        self::assertSame(UrlPrefix::Always, $config->urlPrefix);
        self::assertSame('/{slug}/', $config->permalink);
        self::assertSame(5, $config->build->retainReleases);
        self::assertTrue($config->mail->enabled);
    }

    public function testLoadRejectsAMissingFile(): void
    {
        $this->expectException(ConfigException::class);
        (new ConfigLoader())->load('/tmp/this-config-does-not-exist.php');
    }

    public function testLoadRejectsAFileThatDoesNotReturnAnArray(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cuniform_config_') . '.php';
        file_put_contents($tmp, "<?php\nreturn 'not an array';\n");

        try {
            $this->expectException(ConfigException::class);
            (new ConfigLoader())->load($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function testFromArrayAcceptsAFullyValidConfig(): void
    {
        $config = (new ConfigLoader())->fromArray($this->validConfig());

        self::assertSame('/{slug}/', $config->permalink);
    }

    public function testMissingTopLevelKeyFails(): void
    {
        $raw = $this->validConfig();
        unset($raw['base_url']);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/'base_url'/");
        (new ConfigLoader())->fromArray($raw);
    }

    public function testInvalidTimezoneFails(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/timezone/');
        (new ConfigLoader())->fromArray($this->validConfig(['timezone' => 'Not/AZone']));
    }

    public function testEmptyLanguagesListFails(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/'languages'/");
        (new ConfigLoader())->fromArray($this->validConfig(['languages' => []]));
    }

    public function testNonListLanguagesFails(): void
    {
        $this->expectException(ConfigException::class);
        (new ConfigLoader())->fromArray($this->validConfig(['languages' => ['a' => 'de']]));
    }

    public function testInvalidLanguageSubtagFails(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/BCP-47/');
        (new ConfigLoader())->fromArray($this->validConfig(['languages' => ['DE']]));
    }

    public function testDuplicateLanguageFails(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/more than once/");
        (new ConfigLoader())->fromArray($this->validConfig(['languages' => ['de', 'de']]));
    }

    public function testDefaultLanguageNotInLanguagesFails(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/default_language/');
        (new ConfigLoader())->fromArray($this->validConfig([
            'languages'        => ['de', 'en'],
            'default_language' => 'fr',
        ]));
    }

    public function testUnknownUrlPrefixFails(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/'url_prefix'/");
        (new ConfigLoader())->fromArray($this->validConfig(['url_prefix' => 'sometimes']));
    }

    public function testNeverUrlPrefixWithTwoLanguagesFails(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/cannot be .never./');
        (new ConfigLoader())->fromArray($this->validConfig([
            'languages'  => ['de', 'en'],
            'url_prefix' => 'never',
        ]));
    }

    public function testNeverUrlPrefixWithOneLanguageSucceeds(): void
    {
        $config = (new ConfigLoader())->fromArray($this->validConfig([
            'languages'        => ['de'],
            'default_language' => 'de',
            'url_prefix'       => 'never',
        ]));

        self::assertSame(UrlPrefix::Never, $config->urlPrefix);
    }

    public function testPermalinkMustStartWithSlash(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/must start with/");
        (new ConfigLoader())->fromArray($this->validConfig(['permalink' => '{slug}/']));
    }

    public function testPermalinkRejectsUnknownToken(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/unknown token/');
        (new ConfigLoader())->fromArray($this->validConfig(['permalink' => '/{title}/']));
    }

    public function testPermalinkAcceptsAllDocumentedTokens(): void
    {
        $config = (new ConfigLoader())->fromArray(
            $this->validConfig(['permalink' => '/{year}/{month}/{day}/{slug}/'])
        );

        self::assertSame('/{year}/{month}/{day}/{slug}/', $config->permalink);
    }

    public function testMultipleErrorsAreReportedTogether(): void
    {
        try {
            (new ConfigLoader())->fromArray($this->validConfig([
                'languages'  => ['DE'],
                'url_prefix' => 'sometimes',
            ]));
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $e) {
            self::assertMatchesRegularExpression('/BCP-47/', $e->getMessage());
            self::assertMatchesRegularExpression("/'url_prefix'/", $e->getMessage());
        }
    }

    public function testPathsSubArrayErrorsUseADottedKey(): void
    {
        $raw = $this->validConfig();
        unset($raw['paths']['content']);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/'paths\.content'/");
        (new ConfigLoader())->fromArray($raw);
    }

    public function testBuildThresholdOutOfRangeFails(): void
    {
        $raw                                        = $this->validConfig();
        $raw['build']['page_count_drop_threshold'] = 1.5;

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/between 0 and 1/');
        (new ConfigLoader())->fromArray($raw);
    }

    public function testMailEnabledMustBeBoolean(): void
    {
        $raw                    = $this->validConfig();
        $raw['mail']['enabled'] = 'yes';

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/'mail\.enabled'/");
        (new ConfigLoader())->fromArray($raw);
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validConfig(array $overrides = []): array
    {
        return array_replace([
            'base_url'          => 'https://blog.silverday.de',
            'title'             => 'SilverDay',
            'timezone'          => 'Europe/Berlin',
            'languages'         => ['de', 'en'],
            'default_language'  => 'de',
            'url_prefix'        => 'always',
            'permalink'         => '/{slug}/',
            'posts_per_page'    => 10,
            'feed_items'        => 20,
            'paths' => [
                'content'   => '/tmp/cuniform-fixture/content',
                'templates' => '/tmp/cuniform-fixture/templates',
                'releases'  => '/tmp/cuniform-fixture/releases',
                'public'    => '/tmp/cuniform-fixture/public',
                'var'       => '/tmp/cuniform-fixture/var',
            ],
            'build' => [
                'retain_releases'           => 5,
                'max_document_bytes'        => 2 * 1024 * 1024,
                'page_count_drop_threshold' => 0.10,
                'search_index_warn_bytes'   => 750 * 1024,
            ],
            'mail' => [
                'enabled'         => true,
                'from'            => 'cuniform@silverday.de',
                'notify'          => 'notify@example.com',
                'envelope_sender' => 'cuniform@silverday.de',
            ],
        ], $overrides);
    }
}
