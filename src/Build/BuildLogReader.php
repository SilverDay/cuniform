<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * Read side of `var/log/build.jsonl` — the admin build-log screen's own use
 * of SPEC §15.4. A malformed line is skipped rather than failing the whole
 * read, same reasoning as Admin\Audit\AuditLogReader.
 */
final class BuildLogReader
{
    public function __construct(private readonly string $logPath)
    {
    }

    /**
     * @return list<BuildLogEntry> Newest first.
     */
    public function recent(int $limit = 20): array
    {
        if (!is_file($this->logPath)) {
            return [];
        }

        $contents = (string) file_get_contents($this->logPath);

        $entries = [];
        foreach (explode("\n", $contents) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $entry = BuildLogEntry::fromArray($decoded);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return array_slice(array_reverse($entries), 0, max(0, $limit));
    }

    public function latest(): ?BuildLogEntry
    {
        return $this->recent(1)[0] ?? null;
    }
}
