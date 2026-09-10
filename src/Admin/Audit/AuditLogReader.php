<?php

declare(strict_types=1);

namespace Cuniform\Admin\Audit;

/**
 * Read side of `var/log/audit.jsonl` — the admin dashboard's own use of
 * SPEC §13.2's audit log. A line that isn't valid JSON, or doesn't carry
 * every required field, is skipped rather than failing the whole read —
 * the log is append-only and this reader is never what decides whether a
 * write succeeded, so a single malformed line (hand-edited, truncated by a
 * crash mid-write) shouldn't make every other entry unreadable.
 */
final class AuditLogReader
{
    public function __construct(private readonly string $logPath)
    {
    }

    /**
     * @return list<AuditLogEntry> Newest first.
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

            $entry = AuditLogEntry::fromArray($decoded);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return array_slice(array_reverse($entries), 0, max(0, $limit));
    }
}
