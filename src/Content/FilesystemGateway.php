<?php

declare(strict_types=1);

namespace Cuniform\Content;

/**
 * The one gateway for reading Markdown source files (SPEC §4.6, CLAUDE.md conventions).
 *
 * The vendored/forked renderer's own convertFile() guards are not used — the adapter
 * calls convert(string), so this class reimplements the same protections: realpath()
 * containment under the content root, an extension allow-list, and a size cap.
 */
final class FilesystemGateway
{
    private const ALLOWED_EXTENSIONS = ['md', 'markdown'];

    private readonly string $contentRoot;

    public function __construct(string $contentRoot, private readonly int $maxBytes = 2 * 1024 * 1024)
    {
        $realRoot = realpath($contentRoot);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw ContentException::rootNotFound($contentRoot);
        }

        $this->contentRoot = $realRoot;
    }

    /**
     * Validate a path and return its resolved, real path.
     *
     * @throws ContentException
     */
    public function resolve(string $path): string
    {
        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath)) {
            throw ContentException::notFound($path);
        }

        if (!is_readable($realPath)) {
            throw ContentException::unreadable($path);
        }

        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ContentException::disallowedExtension($path, $extension, implode(', ', self::ALLOWED_EXTENSIONS));
        }

        $this->assertContained($realPath);

        $size = filesize($realPath);
        if ($size === false || $size > $this->maxBytes) {
            throw ContentException::tooLarge($path, $this->maxBytes);
        }

        return $realPath;
    }

    /**
     * Validate a path (see resolve()) and return its contents.
     *
     * @throws ContentException
     */
    public function read(string $path): string
    {
        $realPath = $this->resolve($path);

        $contents = file_get_contents($realPath);
        if ($contents === false) {
            throw ContentException::unreadable($path);
        }

        return $contents;
    }

    private function assertContained(string $realPath): void
    {
        // Trailing separator avoids a partial-directory-name match, e.g.
        // content root "/x/content" matching a sibling "/x/content-other/...".
        $root = rtrim($this->contentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (strncmp($realPath, $root, strlen($root)) !== 0) {
            throw ContentException::outsideContentRoot($realPath, $this->contentRoot);
        }
    }
}
