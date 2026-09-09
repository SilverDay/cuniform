<?php

declare(strict_types=1);

namespace Cuniform\Render;

use Cuniform\CuniformException;

/**
 * Failures in the render/shortcode pipeline itself — as opposed to
 * ContentException, which covers the document being read or its front
 * matter being invalid.
 */
final class RenderException extends CuniformException
{
    public static function unclosedShortcode(string $name): self
    {
        return new self("Shortcode [{$name}] has no matching [/{$name}]");
    }

    public static function duplicateHandler(string $name): self
    {
        return new self("Duplicate shortcode handler registered for '{$name}'");
    }

    public static function rendererNotHeadless(): self
    {
        return new self('Md2Html must be constructed with headless=true (SPEC §9)');
    }

    public static function templateNotFound(string $path): self
    {
        return new self("Template not found: {$path}");
    }

    public static function unknownTemplate(string $name): self
    {
        return new self("'{$name}' is not an allow-listed template name (SPEC §9, §6.2)");
    }

    public static function missingIncludeSlug(): self
    {
        return new self("[include] requires a 'page' attribute");
    }

    public static function includeDepthExceeded(string $slug, int $maxDepth): self
    {
        return new self("[include page=\"{$slug}\"] exceeds the maximum include depth of {$maxDepth} (SPEC §6.5)");
    }

    /**
     * @param list<string> $chain
     */
    public static function includeCycle(array $chain): self
    {
        return new self('[include] cycle detected: ' . implode(' → ', $chain));
    }

    public static function includeNotFound(string $slug, string $language): self
    {
        return new self("[include page=\"{$slug}\"] found no page with that slug in language '{$language}'");
    }

    public static function includeCrossLanguage(string $slug, string $fromLanguage, string $targetLanguage): self
    {
        return new self(
            "[include page=\"{$slug}\"] in a '{$fromLanguage}' document resolved to a "
            . "'{$targetLanguage}' page — cross-language includes are a build error (SPEC §6.5)"
        );
    }
}
