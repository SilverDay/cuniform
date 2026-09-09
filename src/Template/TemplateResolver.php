<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\Render\RenderException;

/**
 * Resolves a template name to a file path against an allow-list — a page's
 * `template` front matter key is never used as a path directly (SPEC §9,
 * §6.2). `feed.xml.php` is deliberately absent: FeedGenerator (T20) builds
 * RSS/Atom directly with DOMDocument, so no PHP template ever renders a
 * feed (see BuildPipeline's docblock). `404-root.php` isn't one of §9's
 * named templates either — it's the neutral, no-language-assumption root
 * `/404.html` (§7.12), which can't go through layout.php at all (that
 * file requires a single `<html lang>`), so it's a small template of its
 * own rather than a special-cased branch inside `404.php`.
 */
final class TemplateResolver
{
    private const ALLOWED = [
        'layout.php',
        'post.php',
        'page.php',
        'index.php',
        'tag.php',
        'series.php',
        'archive.php',
        'search.php',
        '404.php',
        '404-root.php',
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
