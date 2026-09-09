<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Content\ContentException;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\LanguageResolver;

/**
 * Stage 2 — Discover (SPEC §10.1): a recursive scan of `content/posts/` and
 * `content/pages/`, skipping any `*.example.md` file (§6.4 — an inert
 * template, never content). Every problem across the whole scan is collected
 * and reported together (§5.5), not just the first — a directory whose
 * language segment isn't configured is a build error, but one bad directory
 * shouldn't hide a second one three files later.
 */
final class ContentDiscoverer
{
    private const CONTENT_TYPES = ['posts' => DocumentKind::Post, 'pages' => DocumentKind::Page];

    private readonly LanguageResolver $languageResolver;

    /**
     * @param list<string> $languages
     */
    public function __construct(private readonly string $contentRoot, array $languages)
    {
        $this->languageResolver = new LanguageResolver($languages);
    }

    /**
     * @return list<DiscoveredDocument>
     *
     * @throws ContentException When any file's language directory is unconfigured —
     *                          message lists every offending file.
     */
    public function discover(): array
    {
        $documents = [];
        $errors    = [];

        foreach (self::CONTENT_TYPES as $dirName => $kind) {
            $root = rtrim($this->contentRoot, '/') . '/' . $dirName;
            if (!is_dir($root)) {
                continue;
            }

            foreach ($this->findMarkdownFiles($root) as $absolutePath) {
                $relativePath = $dirName . '/' . ltrim(substr($absolutePath, strlen($root)), '/');

                try {
                    $language = $this->languageResolver->resolve($relativePath);
                } catch (ContentException $e) {
                    $errors[] = $e->getMessage();

                    continue;
                }

                $sha256 = hash_file('sha256', $absolutePath);
                $mtime  = filemtime($absolutePath);

                $documents[] = new DiscoveredDocument(
                    $absolutePath,
                    $relativePath,
                    $kind,
                    $language,
                    $mtime === false ? 0 : $mtime,
                    $sha256 === false ? '' : $sha256,
                );
            }
        }

        if ($errors !== []) {
            throw ContentException::discoveryFailed($errors);
        }

        return $documents;
    }

    /**
     * @return list<string>
     */
    private function findMarkdownFiles(string $root): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            \assert($fileInfo instanceof \SplFileInfo);

            if (!$fileInfo->isFile()) {
                continue;
            }

            $filename = $fileInfo->getFilename();
            if (str_ends_with($filename, '.example.md')) {
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($extension, ['md', 'markdown'], true)) {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }

        sort($files);

        return $files;
    }
}
