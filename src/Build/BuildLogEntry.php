<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * One line of `var/log/build.jsonl` (SPEC §15.4: "build log ... with
 * duration, document count per language, and outcome").
 */
final class BuildLogEntry
{
    /**
     * @param array<string, int> $documentCountByLanguage
     */
    public function __construct(
        public readonly \DateTimeImmutable $timestamp,
        public readonly BuildOutcome $outcome,
        public readonly float $durationSeconds,
        public readonly array $documentCountByLanguage,
        public readonly int $routeCount,
        public readonly ?string $releaseDir,
        public readonly ?string $message,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'timestamp'                  => $this->timestamp->format(DATE_ATOM),
            'outcome'                    => $this->outcome->value,
            'duration_seconds'           => round($this->durationSeconds, 3),
            'document_count_by_language' => $this->documentCountByLanguage,
            'route_count'                => $this->routeCount,
            'release_dir'                => $this->releaseDir,
            'message'                    => $this->message,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        if (!isset($data['timestamp']) || !is_string($data['timestamp'])) {
            return null;
        }

        try {
            $timestamp = new \DateTimeImmutable($data['timestamp']);
        } catch (\Exception) {
            return null;
        }

        $outcome = isset($data['outcome']) && is_string($data['outcome']) ? BuildOutcome::tryFrom($data['outcome']) : null;
        if ($outcome === null) {
            return null;
        }

        $duration = isset($data['duration_seconds']) && is_numeric($data['duration_seconds']) ? (float) $data['duration_seconds'] : null;
        if ($duration === null) {
            return null;
        }

        $counts = [];
        if (isset($data['document_count_by_language']) && is_array($data['document_count_by_language'])) {
            foreach ($data['document_count_by_language'] as $language => $count) {
                if (is_string($language) && is_int($count)) {
                    $counts[$language] = $count;
                }
            }
        }

        $routeCount = isset($data['route_count']) && is_int($data['route_count']) ? $data['route_count'] : 0;
        $releaseDir = isset($data['release_dir']) && is_string($data['release_dir']) ? $data['release_dir'] : null;
        $message    = isset($data['message']) && is_string($data['message']) ? $data['message'] : null;

        return new self($timestamp, $outcome, $duration, $counts, $routeCount, $releaseDir, $message);
    }
}
