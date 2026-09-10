<?php

declare(strict_types=1);

namespace Cuniform\Admin\Audit;

use Cuniform\Admin\AdminException;

/**
 * Appends one line to `var/log/audit.jsonl` (SPEC §13.2). Called from
 * EditorDocumentStore right after a real git commit — not from save()/
 * move()'s "nothing actually changed" branch, which produces no commit and
 * so has nothing to attribute a SHA to; not from MediaUploader (uploaded
 * media is never git-committed, SPEC §15.3, so there is no "resulting
 * commit SHA" to record either); not from login/logout (that trail is
 * already `Auth\RateLimiter`/`SessionStore`'s job, a different security
 * concern than "who committed what").
 */
final class AuditLogWriter
{
    public function __construct(private readonly string $logPath)
    {
    }

    public function record(AuditLogEntry $entry): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw AdminException::auditLogWriteFailed($dir);
        }

        if (!is_writable($dir)) {
            throw AdminException::auditLogWriteFailed($dir);
        }

        $line = json_encode($entry->toArray(), JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX) === false) {
            throw AdminException::auditLogWriteFailed($this->logPath);
        }
    }
}
