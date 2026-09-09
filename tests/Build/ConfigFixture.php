<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Config\BuildSettings;
use Cuniform\Config\Config;
use Cuniform\Config\ConfigPaths;
use Cuniform\Config\MailSettings;
use Cuniform\Config\UrlPrefix;

/**
 * A minimal, valid Config for tests that need one but aren't testing
 * ConfigLoader itself — every T20 artifact generator test builds a Config
 * this shape, so this is the shared version rather than six near-identical
 * copies.
 */
final class ConfigFixture
{
    /**
     * @param list<string> $languages
     */
    public static function make(
        string $baseUrl = 'https://blog.silverday.de',
        array $languages = ['de'],
        string $notify = 'a@example.com',
        int $searchIndexWarnBytes = 750 * 1024,
        int $feedItems = 20,
        ?string $defaultLanguage = null,
        UrlPrefix $urlPrefix = UrlPrefix::Always,
    ): Config {
        return new Config(
            baseUrl: $baseUrl,
            title: 'SilverDay',
            timezone: 'Europe/Berlin',
            languages: $languages,
            defaultLanguage: $defaultLanguage ?? $languages[0],
            urlPrefix: $urlPrefix,
            permalink: '/{slug}/',
            postsPerPage: 10,
            feedItems: $feedItems,
            paths: new ConfigPaths('/tmp/content', '/tmp/templates', '/tmp/releases', '/tmp/public', '/tmp/var'),
            build: new BuildSettings(5, 2 * 1024 * 1024, 0.10, $searchIndexWarnBytes),
            mail: new MailSettings(false, 'a@example.com', $notify, 'a@example.com'),
        );
    }
}
