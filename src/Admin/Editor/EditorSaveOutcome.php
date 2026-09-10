<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

final class EditorSaveOutcome
{
    /**
     * @param list<string> $errors
     */
    private function __construct(
        public readonly EditorSaveStatus $status,
        public readonly ?EditorDocument $document = null,
        public readonly ?EditorDocument $current = null,
        public readonly ?string $currentRaw = null,
        public readonly array $errors = [],
        public readonly ?string $commitSha = null,
        public readonly ?string $notFoundIdentifier = null,
    ) {
    }

    public static function saved(EditorDocument $document, ?string $commitSha): self
    {
        return new self(EditorSaveStatus::Saved, document: $document, commitSha: $commitSha);
    }

    /**
     * $current is the on-disk version as it stands right now — SPEC §12:
     * "on mismatch refuse the write and show a diff." $currentRaw is its
     * exact file bytes (front matter + body), for the caller to diff
     * against what it was about to write — $current itself is already
     * parsed into typed fields, which would only let the caller reconstruct
     * an approximation.
     */
    public static function conflict(EditorDocument $current, string $currentRaw): self
    {
        return new self(EditorSaveStatus::Conflict, current: $current, currentRaw: $currentRaw);
    }

    /**
     * @param list<string> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(EditorSaveStatus::Invalid, errors: $errors);
    }

    public static function notFound(string $identifier): self
    {
        return new self(EditorSaveStatus::NotFound, notFoundIdentifier: $identifier);
    }
}
