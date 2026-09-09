<?php

declare(strict_types=1);

namespace Cuniform\Content;

use Cuniform\CuniformException;

final class ContentException extends CuniformException
{
    public static function rootNotFound(string $root): self
    {
        return new self("Content root does not exist or is not a directory: {$root}");
    }

    public static function notFound(string $path): self
    {
        return new self("File not found or is not a regular file: {$path}");
    }

    public static function unreadable(string $path): self
    {
        return new self("File is not readable: {$path}");
    }

    public static function disallowedExtension(string $path, string $extension, string $allowed): self
    {
        return new self("Disallowed file extension \"{$extension}\" for {$path}. Allowed: {$allowed}.");
    }

    public static function outsideContentRoot(string $path, string $root): self
    {
        return new self("Access denied: {$path} is outside the content root {$root}.");
    }

    public static function tooLarge(string $path, int $maxBytes): self
    {
        return new self("File exceeds the maximum allowed size of {$maxBytes} bytes: {$path}");
    }

    public static function notUnderPostsOrPages(string $relativePath): self
    {
        return new self("Path is not under posts/ or pages/: {$relativePath}");
    }

    /**
     * @param list<string> $configuredLanguages
     */
    public static function unknownLanguageDirectory(string $relativePath, string $language, array $configuredLanguages): self
    {
        $allowed = implode(', ', $configuredLanguages);

        return new self(
            "'{$relativePath}' is under language directory '{$language}', which is not in the "
            . "configured languages ({$allowed}) — SPEC §7.2"
        );
    }

    public static function duplicateTranslationKey(string $key, string $language, string $firstIdentifier, string $secondIdentifier): self
    {
        return new self(
            "translation_key '{$key}' appears twice within language '{$language}': "
            . "'{$firstIdentifier}' and '{$secondIdentifier}' — SPEC §7.3"
        );
    }

    /**
     * @param list<string> $errors
     */
    public static function invalidFrontMatter(string $path, array $errors): self
    {
        $lines = array_map(static fn (string $error): string => '- ' . $error, $errors);
        $label = $path === '' ? '(no path given)' : $path;

        return new self("Invalid front matter in {$label}:\n" . implode("\n", $lines));
    }
}
