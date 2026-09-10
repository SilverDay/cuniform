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

    private const ALLOWED_PARTIALS = [
        'head.php',
        'nav-primary.php',
        'nav-footer.php',
        'lang-switcher.php',
        'post-card.php',
        'pagination.php',
        'toc.php',
    ];

    public function __construct(
        private readonly string $templatesDir,
        private readonly string $templateSet = 'default',
    ) {
    }

    public function resolve(string $templateName): string
    {
        $this->assertSafeTemplateName($templateName);
        if (!in_array($templateName, self::ALLOWED, true)) {
            throw RenderException::unknownTemplate($templateName);
        }

        foreach ($this->candidatePaths($templateName) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $this->defaultPath($templateName);
    }

    public function resolvePartial(string $partialName): string
    {
        $normalized = str_starts_with($partialName, 'partials/')
            ? substr($partialName, strlen('partials/'))
            : $partialName;

        $this->assertSafeTemplateName($normalized);
        if (!in_array($normalized, self::ALLOWED_PARTIALS, true)) {
            throw RenderException::unknownPartial($partialName);
        }

        $relative = 'partials/' . $normalized;
        foreach ($this->candidatePaths($relative) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $this->defaultPath($relative);
    }

    public function resolveAsset(string $assetName): string
    {
        $this->assertSafeTemplateName($assetName);

        foreach ($this->candidatePaths($assetName) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $this->defaultPath($assetName);
    }

    /**
     * @return list<string>
     */
    private function candidatePaths(string $name): array
    {
        $paths = [];

        if ($this->templateSet !== '' && $this->templateSet !== 'default') {
            $paths[] = $this->join($this->templatesDir, $this->templateSet, $name);
        }

        $paths[] = $this->join($this->templatesDir, $name);

        return $paths;
    }

    private function defaultPath(string $name): string
    {
        return $this->join($this->templatesDir, $name);
    }

    private function join(string ...$segments): string
    {
        $path = '';
        foreach ($segments as $segment) {
            $segment = trim((string) $segment, '/');
            if ($segment === '') {
                continue;
            }

            $path = $path === '' ? $segment : $path . '/' . $segment;
        }

        return $path === '' ? '' : '/' . $path;
    }

    private function assertSafeTemplateName(string $name): void
    {
        if ($name === '' || preg_match('/^[A-Za-z0-9._-]+$/', $name) !== 1) {
            throw RenderException::unknownTemplate($name);
        }
    }
}
