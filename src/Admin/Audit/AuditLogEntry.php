<?php

declare(strict_types=1);

namespace Cuniform\Admin\Audit;

/**
 * One line of `var/log/audit.jsonl` (SPEC §13.2: "append-only JSONL audit
 * log ... recording actor, action, timestamp, and resulting commit SHA").
 * Scoped to the content-writing actions that actually produce a commit —
 * `EditorDocumentStore::save()`/`move()` — matching that phrase literally;
 * media uploads and logins are deliberately not recorded here (see
 * AuditLogWriter's own docblock).
 */
final class AuditLogEntry
{
    public function __construct(
        public readonly \DateTimeImmutable $timestamp,
        public readonly string $actor,
        public readonly string $action,
        public readonly string $identifier,
        public readonly string $commitSha,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'timestamp'  => $this->timestamp->format(DATE_ATOM),
            'actor'      => $this->actor,
            'action'     => $this->action,
            'identifier' => $this->identifier,
            'commit_sha' => $this->commitSha,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $timestamp = isset($data['timestamp']) && is_string($data['timestamp'])
            ? self::parseTimestamp($data['timestamp'])
            : null;

        if (
            $timestamp === null
            || !isset($data['actor'], $data['action'], $data['identifier'], $data['commit_sha'])
            || !is_string($data['actor']) || !is_string($data['action'])
            || !is_string($data['identifier']) || !is_string($data['commit_sha'])
        ) {
            return null;
        }

        return new self($timestamp, $data['actor'], $data['action'], $data['identifier'], $data['commit_sha']);
    }

    private static function parseTimestamp(string $value): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
