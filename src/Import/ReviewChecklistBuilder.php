<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * Builds the SPEC §A.5 review checklist from one import run: one
 * `ReviewEntry` per **imported** document (never a skipped item — there
 * is nothing staged for those to review), each carrying whatever T38
 * migration-report lines (SPEC §A.4) are attributable to it — the same
 * signal BUILD-ORDER's own T39 line and SPEC's "the migration report
 * drives the order" both point at.
 *
 * Deliberately a plain in-memory build + merge, no filesystem access —
 * `ReviewChecklistStore` owns reading and writing the tracking file
 * itself, matching this codebase's existing `WxrImporter`/
 * `ImportedDocumentWriter` split (T37: computation stays separate from
 * where a result actually gets persisted).
 */
final class ReviewChecklistBuilder
{
    /**
     * @param array{documents: list<ImportedDocument>, warnings: list<string>} $importResult
     *
     * @return array<int|string, ReviewEntry> Keyed by source_id.
     */
    public function build(WxrDocument $wxr, array $importResult, ImportReport $report): array
    {
        $titlesBySourceId = [];
        foreach ($wxr->items as $item) {
            $titlesBySourceId[(string) $item->postId] = $item->title;
        }

        $flagsBySourceId = $this->flagsBySourceId($report);

        $entries = [];
        foreach ($importResult['documents'] as $document) {
            $entries[$document->sourceId] = new ReviewEntry(
                $document->sourceId,
                $document->relativePath,
                $titlesBySourceId[$document->sourceId] ?? '',
                $flagsBySourceId[$document->sourceId] ?? [],
                ReviewDecision::Pending,
            );
        }

        return $entries;
    }

    /**
     * Carries a prior run's recorded decisions forward onto a fresh
     * build, matched by source_id — SPEC §A.5: "means an interrupted
     * review can resume rather than restart." A source_id no longer
     * present in `$fresh` (an item that stopped importing, e.g. because
     * a fix changed its slug to an invalid one) is dropped: there is no
     * longer a staged document for it to review. A source_id `$existing`
     * has never seen starts at `Pending`, same as a first-time build.
     *
     * Flags are always taken from `$fresh`, never carried over — the
     * whole point of re-running the import between review sessions is
     * often to see whether a fix actually cleared a flag, and an old
     * flag lingering after that would be actively misleading.
     *
     * @param array<int|string, ReviewEntry> $fresh
     * @param array<int|string, ReviewEntry> $existing
     *
     * @return array<int|string, ReviewEntry>
     */
    public function merge(array $fresh, array $existing): array
    {
        $merged = [];
        foreach ($fresh as $sourceId => $entry) {
            $priorDecision = $existing[$sourceId]->decision ?? ReviewDecision::Pending;
            $merged[$sourceId] = $entry->withDecision($priorDecision);
        }

        return $merged;
    }

    /**
     * SPEC §A.4: "The migration report drives the order: documents with
     * flags first, clean ones batched." Applied here as display order
     * only — the tracking file itself stays keyed by source_id (order-
     * independent), so this never has to be re-derived from a stored
     * order that could grow stale as decisions accumulate. `usort()` is
     * stable in PHP 8+, so within each of the two groups, entries keep
     * whatever order they arrived in (import order).
     *
     * @param array<int|string, ReviewEntry> $entries
     *
     * @return list<ReviewEntry>
     */
    public function sortedForReview(array $entries): array
    {
        $list = array_values($entries);
        usort($list, static fn (ReviewEntry $a, ReviewEntry $b): int => ($b->flags !== []) <=> ($a->flags !== []));

        return $list;
    }

    /**
     * @return array<int|string, list<string>> source_id => the migration-report
     *                                      lines that name it. Only the
     *                                      buckets that can ever name an
     *                                      *imported* document — SPEC
     *                                      §A.4's own unresolved-legacy-URL
     *                                      and manual-decision buckets are
     *                                      about items that were *not*
     *                                      imported, so there's never a
     *                                      staged document for one of
     *                                      those to attach a flag to.
     */
    private function flagsBySourceId(ImportReport $report): array
    {
        $bySourceId = [];
        $messages   = [...$report->wordCountOutliers, ...$report->unknownShortcodes, ...$report->unsupportedConstructs, ...$report->otherNotices];

        foreach ($messages as $message) {
            if (preg_match('/^post_id=(\d+)/', $message, $matches) === 1) {
                $bySourceId[$matches[1]][] = $message;
            }
        }

        return $bySourceId;
    }
}
