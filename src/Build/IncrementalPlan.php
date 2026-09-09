<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * IncrementalPlanner's output: which documents (by
 * `ParsedDocument::identifier()`) need Render+Template work redone this
 * build, and every document's freshly-computed base cache key (needed
 * either way, to write the next CacheManifest — see BuildPipeline).
 */
final class IncrementalPlan
{
    /**
     * @param array<string, true>  $dirty
     * @param array<string, string> $baseKeyByIdentifier
     */
    public function __construct(
        public readonly array $dirty,
        public readonly bool $fullRebuild,
        public readonly array $baseKeyByIdentifier,
    ) {
    }

    public function isDirty(string $identifier): bool
    {
        return $this->fullRebuild || isset($this->dirty[$identifier]);
    }
}
