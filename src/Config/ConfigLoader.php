<?php

declare(strict_types=1);

namespace Cuniform\Config;

/**
 * Loads and validates config/site.php (SPEC §7.1, §8.1).
 *
 * Every error is collected and reported together, not just the first one
 * found — the same principle the build pipeline uses for content documents.
 */
final class ConfigLoader
{
    private const ALLOWED_URL_PREFIXES = ['always', 'auto', 'never'];

    private const ALLOWED_PERMALINK_TOKENS = ['slug', 'year', 'month', 'day'];

    /** @var list<string> */
    private array $errors = [];

    public function load(string $path): Config
    {
        if (!is_file($path)) {
            throw ConfigException::fromErrors(["Config file not found: {$path}"]);
        }

        $raw = require $path;

        if (!is_array($raw)) {
            throw ConfigException::fromErrors(["Config file must return an array: {$path}"]);
        }

        return $this->fromArray($raw);
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    public function fromArray(array $raw): Config
    {
        $this->errors = [];

        $baseUrl  = $this->requireString($raw, 'base_url');
        $title    = $this->requireString($raw, 'title');
        $timezone = $this->requireTimezone($raw, 'timezone');

        $languages       = $this->requireLanguages($raw, 'languages');
        $defaultLanguage = $this->requireString($raw, 'default_language');
        if ($defaultLanguage !== '' && $languages !== [] && !in_array($defaultLanguage, $languages, true)) {
            $this->errors[] = "default_language '{$defaultLanguage}' is not in languages ("
                . implode(', ', $languages) . ')';
        }

        $urlPrefix = $this->requireUrlPrefix($raw, 'url_prefix', $languages);
        $permalink = $this->requirePermalink($raw, 'permalink');

        $postsPerPage = $this->requirePositiveInt($raw, 'posts_per_page');
        $feedItems    = $this->requirePositiveInt($raw, 'feed_items');

        $paths = $this->requirePaths($raw, 'paths');
        $build = $this->requireBuild($raw, 'build');
        $mail  = $this->requireMail($raw, 'mail');

        if ($this->errors !== []) {
            throw ConfigException::fromErrors($this->errors);
        }

        return new Config(
            baseUrl: $baseUrl,
            title: $title,
            timezone: $timezone,
            languages: $languages,
            defaultLanguage: $defaultLanguage,
            urlPrefix: $urlPrefix ?? UrlPrefix::Always,
            permalink: $permalink,
            postsPerPage: $postsPerPage,
            feedItems: $feedItems,
            paths: $paths,
            build: $build,
            mail: $mail,
        );
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function requireString(array $raw, string $key): string
    {
        if (!array_key_exists($key, $raw)) {
            $this->errors[] = "missing required key '{$key}'";

            return '';
        }

        $value = $raw[$key];
        if (!is_string($value) || $value === '') {
            $this->errors[] = "'{$key}' must be a non-empty string";

            return '';
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function requireTimezone(array $raw, string $key): string
    {
        $value = $this->requireString($raw, $key);
        if ($value !== '' && !in_array($value, \DateTimeZone::listIdentifiers(), true)) {
            $this->errors[] = "'{$key}' is not a known timezone identifier: {$value}";
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function requirePositiveInt(array $raw, string $key): int
    {
        if (!array_key_exists($key, $raw)) {
            $this->errors[] = "missing required key '{$key}'";

            return 0;
        }

        $value = $raw[$key];
        if (!is_int($value) || $value < 1) {
            $this->errors[] = "'{$key}' must be a positive integer";

            return 0;
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function requireBool(array $raw, string $key): bool
    {
        if (!array_key_exists($key, $raw)) {
            $this->errors[] = "missing required key '{$key}'";

            return false;
        }

        $value = $raw[$key];
        if (!is_bool($value)) {
            $this->errors[] = "'{$key}' must be a boolean";

            return false;
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed> $raw
     * @return list<string>
     */
    private function requireLanguages(array $raw, string $key): array
    {
        if (!array_key_exists($key, $raw)) {
            $this->errors[] = "missing required key '{$key}'";

            return [];
        }

        $value = $raw[$key];
        if (!is_array($value) || $value === [] || !array_is_list($value)) {
            $this->errors[] = "'{$key}' must be a non-empty list of language codes";

            return [];
        }

        $languages = [];
        foreach ($value as $i => $language) {
            if (!is_string($language) || !preg_match('/^[a-z]{2,8}$/', $language)) {
                $this->errors[] = "'{$key}[{$i}]' must be a lower-case BCP-47 primary subtag (e.g. 'de')";

                continue;
            }

            if (in_array($language, $languages, true)) {
                $this->errors[] = "'{$key}' lists '{$language}' more than once";

                continue;
            }

            $languages[] = $language;
        }

        return $languages;
    }

    /**
     * @param  array<array-key, mixed> $raw
     * @param  list<string>            $languages
     */
    private function requireUrlPrefix(array $raw, string $key, array $languages): ?UrlPrefix
    {
        $value = $this->requireString($raw, $key);
        if ($value === '') {
            return null;
        }

        if (!in_array($value, self::ALLOWED_URL_PREFIXES, true)) {
            $this->errors[] = "'{$key}' must be one of "
                . implode(', ', self::ALLOWED_URL_PREFIXES) . ", got '{$value}'";

            return null;
        }

        if ($value === 'never' && count($languages) > 1) {
            $this->errors[] = "'{$key}' cannot be 'never' with more than one configured language "
                . '(every URL would collide across languages) — SPEC §7.1';

            return null;
        }

        return UrlPrefix::from($value);
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function requirePermalink(array $raw, string $key): string
    {
        $value = $this->requireString($raw, $key);
        if ($value === '') {
            return '';
        }

        if (!str_starts_with($value, '/')) {
            $this->errors[] = "'{$key}' must start with '/'";
        }

        preg_match_all('/\{([a-zA-Z_]+)\}/', $value, $matches);
        foreach ($matches[1] as $token) {
            if (!in_array($token, self::ALLOWED_PERMALINK_TOKENS, true)) {
                $this->errors[] = "'{$key}' uses unknown token '{{$token}}' — allowed: "
                    . implode(', ', self::ALLOWED_PERMALINK_TOKENS);
            }
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function requirePaths(array $raw, string $key): ConfigPaths
    {
        $sub = $this->requireSubArray($raw, $key);

        return new ConfigPaths(
            content: $this->requireString($sub, "{$key}.content"),
            templates: $this->requireString($sub, "{$key}.templates"),
            releases: $this->requireString($sub, "{$key}.releases"),
            public: $this->requireString($sub, "{$key}.public"),
            var: $this->requireString($sub, "{$key}.var"),
        );
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function requireBuild(array $raw, string $key): BuildSettings
    {
        $sub = $this->requireSubArray($raw, $key);

        $thresholdKey = "{$key}.page_count_drop_threshold";
        $threshold    = 0.0;
        if (!array_key_exists($thresholdKey, $sub)) {
            $this->errors[] = "missing required key '{$thresholdKey}'";
        } elseif (!is_float($sub[$thresholdKey]) && !is_int($sub[$thresholdKey])) {
            $this->errors[] = "'{$thresholdKey}' must be a number";
        } else {
            $threshold = (float) $sub[$thresholdKey];
            if ($threshold < 0.0 || $threshold > 1.0) {
                $this->errors[] = "'{$thresholdKey}' must be between 0 and 1";
            }
        }

        return new BuildSettings(
            retainReleases: $this->requirePositiveInt($sub, "{$key}.retain_releases"),
            maxDocumentBytes: $this->requirePositiveInt($sub, "{$key}.max_document_bytes"),
            pageCountDropThreshold: $threshold,
            searchIndexWarnBytes: $this->requirePositiveInt($sub, "{$key}.search_index_warn_bytes"),
        );
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function requireMail(array $raw, string $key): MailSettings
    {
        $sub = $this->requireSubArray($raw, $key);

        return new MailSettings(
            enabled: $this->requireBool($sub, "{$key}.enabled"),
            from: $this->requireString($sub, "{$key}.from"),
            notify: $this->requireString($sub, "{$key}.notify"),
            envelopeSender: $this->requireString($sub, "{$key}.envelope_sender"),
        );
    }

    /**
     * Read a nested config array, keying its own errors by the dotted path
     * (e.g. "paths.content") rather than the bare sub-key.
     *
     * @param  array<array-key, mixed>  $raw
     * @return array<array-key, mixed>
     */
    private function requireSubArray(array $raw, string $key): array
    {
        if (!array_key_exists($key, $raw)) {
            $this->errors[] = "missing required key '{$key}'";

            return [];
        }

        $value = $raw[$key];
        if (!is_array($value)) {
            $this->errors[] = "'{$key}' must be an array";

            return [];
        }

        $reKeyed = [];
        foreach ($value as $subKey => $subValue) {
            $reKeyed["{$key}." . (string) $subKey] = $subValue;
        }

        return $reKeyed;
    }
}
