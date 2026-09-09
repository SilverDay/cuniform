<?php

declare(strict_types=1);

namespace Cuniform\Content;

/**
 * A document's language is the first path segment under posts/ or pages/,
 * never front matter (SPEC §7.2). Takes a path relative to the content
 * root — computing that relative path from an absolute one is the caller's
 * job, same division of labour as FrontMatterParser's $sourcePath.
 */
final class LanguageResolver
{
    private const CONTENT_TYPES = ['posts', 'pages'];

    /**
     * @param list<string> $languages Configured languages (Config::$languages, T2).
     */
    public function __construct(private readonly array $languages)
    {
    }

    /**
     * @throws ContentException When the path isn't under posts/ or pages/, or
     *                          its language segment isn't a configured language
     *                          — a build error either way, never a silently
     *                          ignored folder.
     */
    public function resolve(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', ltrim($relativePath, '/'));
        $segments   = explode('/', $normalized);

        if (count($segments) < 2 || !in_array($segments[0], self::CONTENT_TYPES, true)) {
            throw ContentException::notUnderPostsOrPages($relativePath);
        }

        $language = $segments[1];

        if (!in_array($language, $this->languages, true)) {
            throw ContentException::unknownLanguageDirectory($relativePath, $language, $this->languages);
        }

        return $language;
    }
}
