<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * Reads and writes the SPEC §A.5 review tracking file — "a checkbox per
 * source_id in a file under var/" — as JSON keyed by source_id, so a
 * decision survives being merged with a fresh `ReviewChecklistBuilder`
 * build without needing anything beyond a simple key lookup. Never
 * written to `content/` or any release artifact — this is operator
 * bookkeeping, the same category as `var/build-cache.json`
 * (T24)/`var/last-build-meta.json` (T22), not something a build ever reads.
 */
final class ReviewChecklistStore
{
    /**
     * A missing file is a normal, expected first-run state — SPEC §A.5's
     * own "an interrupted review can resume" only means something once a
     * file already exists; before that, there is nothing to resume.
     *
     * @return array<int|string, ReviewEntry>
     *
     * @throws ImportException When the file exists but isn't valid JSON
     *                          in the expected shape.
     */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw ImportException::fromErrors(["could not read review tracking file: {$path}"]);
        }

        try {
            $data = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ImportException::fromErrors(["review tracking file is not valid JSON: {$path} ({$e->getMessage()})"]);
        }

        if (!is_array($data)) {
            throw ImportException::fromErrors(["review tracking file is not a JSON object: {$path}"]);
        }

        $entries = [];
        foreach ($data as $sourceId => $value) {
            // PHP array keys are always int|string, so $sourceId needs no
            // type check of its own — cast below to a string either way.
            // (JSON object keys are always strings, but a numeric one
            // like "11" comes back as the int 11 once decoded into a PHP
            // array — exactly what a WordPress post ID-derived source_id
            // hits every time.)
            if (
                !is_array($value)
                || !isset($value['relativePath'], $value['title'], $value['flags'], $value['decision'])
                || !is_string($value['relativePath'])
                || !is_string($value['title'])
                || !is_array($value['flags'])
                || !is_string($value['decision'])
            ) {
                throw ImportException::fromErrors(["malformed entry in review tracking file: {$path}"]);
            }

            $sourceIdString = (string) $sourceId;

            $decision = ReviewDecision::tryFrom($value['decision']);
            if ($decision === null) {
                throw ImportException::fromErrors(["malformed entry in review tracking file: {$path} (source_id={$sourceIdString})"]);
            }

            $flags = [];
            foreach ($value['flags'] as $flag) {
                if (!is_string($flag)) {
                    throw ImportException::fromErrors(["malformed entry in review tracking file: {$path} (source_id={$sourceIdString})"]);
                }

                $flags[] = $flag;
            }

            $entries[$sourceIdString] = new ReviewEntry($sourceIdString, $value['relativePath'], $value['title'], $flags, $decision);
        }

        return $entries;
    }

    /**
     * @param array<int|string, ReviewEntry> $entries
     *
     * @throws ImportException When the file can't be written.
     */
    public function save(string $path, array $entries): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw ImportException::fromErrors(["could not create directory: {$dir}"]);
        }

        $data = [];
        foreach ($entries as $sourceId => $entry) {
            $data[$sourceId] = [
                'relativePath' => $entry->relativePath,
                'title'        => $entry->title,
                'flags'        => $entry->flags,
                'decision'     => $entry->decision->value,
            ];
        }

        $json = json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $json . "\n") === false) {
            throw ImportException::fromErrors(["could not write review tracking file: {$path}"]);
        }
    }
}
