<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * One imported document's review state (SPEC §A.5) — "a checkbox per
 * source_id in a file under var/," here `decision` rather than a bare
 * boolean, since SPEC's own fifth review point ("content genuinely worth
 * keeping... the cheapest moment to delete posts that have not aged
 * well") is itself a decision with two outcomes, not just a yes/no on
 * whether someone looked at it. `Pending` and "reviewed" are therefore
 * the same fact seen two ways — there is no separate reviewed flag that
 * could disagree with `decision`.
 */
final class ReviewEntry
{
    /**
     * @param list<string> $flags Migration-report entries (SPEC §A.4)
     *                      attributable to this specific document —
     *                      what makes SPEC's own "documents with flags
     *                      first" ordering possible.
     */
    public function __construct(
        public readonly string $sourceId,
        public readonly string $relativePath,
        public readonly string $title,
        public readonly array $flags,
        public readonly ReviewDecision $decision,
    ) {
    }

    public function withDecision(ReviewDecision $decision): self
    {
        return new self($this->sourceId, $this->relativePath, $this->title, $this->flags, $decision);
    }
}
