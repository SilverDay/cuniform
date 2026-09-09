<?php

declare(strict_types=1);

namespace Cuniform\I18n;

use Cuniform\Build\BuildException;
use Cuniform\Config\ConfigException;

/**
 * Template chrome strings, loaded from config/lang/<code>.php per configured
 * language (SPEC §7.8), accessed via get() — what a template's t('key')
 * eventually delegates to, once T16's ViewModel exists to hold "which
 * language is this page" without resorting to global mutable state.
 *
 * A key missing from any configured language is a build error, never a
 * silent fallback to the default language (§7.8) — enforced once, at load
 * time, so get() can never actually observe a missing key for a language
 * this catalogue was successfully built for.
 */
final class UiStringCatalogue
{
    /**
     * @param array<string, array<string, string>> $stringsByLanguage
     */
    private function __construct(private readonly array $stringsByLanguage)
    {
    }

    /**
     * @param list<string> $languages
     *
     * @throws ConfigException When a language file is missing, isn't an
     *                         array, or has a non-string key or value.
     * @throws BuildException  When a key exists for one configured language
     *                         but not another.
     */
    public static function load(string $langDir, array $languages): self
    {
        $stringsByLanguage = [];
        foreach ($languages as $language) {
            $stringsByLanguage[$language] = self::loadOne($langDir, $language);
        }

        self::assertNoMissingKeys($stringsByLanguage);

        return new self($stringsByLanguage);
    }

    /**
     * @throws ConfigException
     */
    public function get(string $language, string $key): string
    {
        if (!isset($this->stringsByLanguage[$language])) {
            throw ConfigException::fromErrors(["'{$language}' is not a language this catalogue was loaded for"]);
        }

        if (!array_key_exists($key, $this->stringsByLanguage[$language])) {
            throw ConfigException::fromErrors(["unknown UI string key '{$key}'"]);
        }

        return $this->stringsByLanguage[$language][$key];
    }

    /**
     * @return array<string, string>
     */
    private static function loadOne(string $langDir, string $language): array
    {
        $path = rtrim($langDir, '/') . "/{$language}.php";

        if (!is_file($path)) {
            throw ConfigException::fromErrors(["UI string file not found: {$path}"]);
        }

        $raw = require $path;

        if (!is_array($raw)) {
            throw ConfigException::fromErrors(["UI string file must return an array: {$path}"]);
        }

        $strings = [];
        $errors  = [];

        foreach ($raw as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                $errors[] = "'{$path}' has a non-string key or value at '" . (string) $key . "'";

                continue;
            }

            $strings[$key] = $value;
        }

        if ($errors !== []) {
            throw ConfigException::fromErrors($errors);
        }

        return $strings;
    }

    /**
     * @param array<string, array<string, string>> $stringsByLanguage
     *
     * @throws BuildException
     */
    private static function assertNoMissingKeys(array $stringsByLanguage): void
    {
        $allKeys = [];
        foreach ($stringsByLanguage as $strings) {
            foreach (array_keys($strings) as $key) {
                $allKeys[$key] = true;
            }
        }

        $errors = [];
        foreach ($stringsByLanguage as $language => $strings) {
            foreach (array_keys($allKeys) as $key) {
                if (!array_key_exists($key, $strings)) {
                    $errors[] = "missing UI string '{$key}' for language '{$language}' (SPEC §7.8)";
                }
            }
        }

        if ($errors !== []) {
            throw BuildException::fromErrors($errors);
        }
    }
}
