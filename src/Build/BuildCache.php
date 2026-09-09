<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * Persists a CacheManifest to `var/build-cache.json` (build-internal
 * bookkeeping, like `var/last-build-meta.json` — never served, never part
 * of a release). A missing or corrupt file is treated as no cache at all
 * rather than a build failure: "any ambiguity resolves toward a full
 * rebuild" (SPEC §10.2) applies here too — a build must never fail
 * because its *own* cache is unreadable, it should just do more work.
 */
final class BuildCache
{
    public function __construct(private readonly string $path)
    {
    }

    public function load(): CacheManifest
    {
        if (!is_file($this->path)) {
            return CacheManifest::empty();
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            return CacheManifest::empty();
        }

        try {
            $decoded = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return CacheManifest::empty();
        }

        if (!is_array($decoded) || !isset($decoded['navHash'], $decoded['documents']) || !is_string($decoded['navHash']) || !is_array($decoded['documents'])) {
            return CacheManifest::empty();
        }

        $documents = [];
        foreach ($decoded['documents'] as $identifier => $entry) {
            $document = $this->decodeDocument($entry);
            if ($document !== null && is_string($identifier)) {
                $documents[$identifier] = $document;
            }
        }

        return new CacheManifest($decoded['navHash'], $documents);
    }

    public function save(CacheManifest $manifest): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw BuildException::fromErrors(["could not create directory: {$dir}"]);
        }

        $documents = [];
        foreach ($manifest->documents as $identifier => $document) {
            $documents[$identifier] = [
                'baseKey'        => $document->baseKey,
                'translationKey' => $document->translationKey,
                'bodyHtml'       => $document->bodyHtml,
                'headings'       => $document->headings,
                'pageHtml'       => $document->pageHtml,
                'routePath'      => $document->routePath,
            ];
        }

        $json = json_encode(
            ['navHash' => $manifest->navHash, 'documents' => $documents],
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
        );

        // Atomic-ish write: a build reading this file mid-write (another
        // process's dry run, say) should never see a truncated JSON file.
        $tmp = $this->path . '.tmp';
        if (file_put_contents($tmp, $json) === false || !rename($tmp, $this->path)) {
            throw BuildException::fromErrors(["could not write: {$this->path}"]);
        }
    }

    private function decodeDocument(mixed $entry): ?CachedDocument
    {
        if (!is_array($entry)) {
            return null;
        }

        if (
            !isset($entry['baseKey'], $entry['bodyHtml'], $entry['headings'], $entry['pageHtml'], $entry['routePath'])
            || !is_string($entry['baseKey'])
            || !is_string($entry['bodyHtml'])
            || !is_array($entry['headings'])
            || !is_string($entry['pageHtml'])
            || !is_string($entry['routePath'])
            || !(($entry['translationKey'] ?? null) === null || is_string($entry['translationKey']))
        ) {
            return null;
        }

        $headings = [];
        foreach ($entry['headings'] as $heading) {
            if (
                is_array($heading)
                && isset($heading['level'], $heading['id'], $heading['text'])
                && is_int($heading['level'])
                && is_string($heading['id'])
                && is_string($heading['text'])
            ) {
                $headings[] = ['level' => $heading['level'], 'id' => $heading['id'], 'text' => $heading['text']];
            }
        }

        return new CachedDocument(
            $entry['baseKey'],
            $entry['translationKey'] ?? null,
            $entry['bodyHtml'],
            $headings,
            $entry['pageHtml'],
            $entry['routePath'],
        );
    }
}
