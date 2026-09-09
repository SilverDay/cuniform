<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\Render\RenderException;

/**
 * Resolves a template name to a file path against an allow-list — a page's
 * `template` front matter key is never used as a path directly (SPEC §9,
 * §6.2). index/tag/series/archive/search/feed.xml aren't in the allow-list
 * yet: nothing generates them (T18/T19), so resolving one would only ever
 * be a bug, not a legitimate call.
 */
final class TemplateResolver
{
    private const ALLOWED = [
        'layout.php',
        'post.php',
        'page.php',
    ];

    public function __construct(private readonly string $templatesDir)
    {
    }

    public function resolve(string $templateName): string
    {
        if (!in_array($templateName, self::ALLOWED, true)) {
            throw RenderException::unknownTemplate($templateName);
        }

        return rtrim($this->templatesDir, '/') . '/' . $templateName;
    }
}
