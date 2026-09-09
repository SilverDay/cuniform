<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * `ImportVerifier`'s output — SPEC §A.4's migration report: "a migration
 * report listing unknown shortcodes, unsupported constructs, footnotes,
 * failed media downloads, and posts awaiting a decision." Every list here
 * carries plain, already-formatted strings (`post_id=N: ...`), the same
 * shape `WxrImporter`'s own warnings already use — this class only sorts
 * them into SPEC's named buckets, it doesn't reformat them.
 *
 * `footnotes` and `failedMediaDownloads` are always empty in this
 * implementation, not omitted — both are named explicitly in §A.4, so
 * the field exists for forward compatibility even though nothing
 * currently populates it: no footnote-specific detection is built (a
 * footnote plugin's shortcode would still surface under
 * `unknownShortcodes`, generically), and T36 (media downloader) isn't
 * built yet, so there is nothing to attempt a download with, let alone
 * fail one.
 */
final class ImportReport
{
    /**
     * @param list<string> $unresolvedLegacyUrls A real pretty-permalink URL
     *                      (SPEC §7.11/§A.3) belonging to a `post`/`page`
     *                      item that was not imported — it will have no
     *                      redirect once the reviewed documents ship, and
     *                      that's worth a human decision, not a silent gap.
     * @param list<string> $wordCountOutliers
     * @param list<string> $unknownShortcodes
     * @param list<string> $unsupportedConstructs
     * @param list<string> $footnotes Always empty — see class docblock.
     * @param list<string> $failedMediaDownloads Always empty — see class docblock.
     * @param list<string> $manualDecisionItems Items SPEC §A.3 itself calls
     *                      out as needing a human call (`private` status,
     *                      not-yet-supported `page` items) rather than an
     *                      automatic mapping.
     * @param list<string> $otherNotices Every other importer warning that
     *                      doesn't fit one of §A.4's named buckets (an
     *                      empty-slug fallback, a pubDate fallback, ...) —
     *                      kept visible rather than discarded once
     *                      categorized.
     * @param array<string, int> $countsByStatus wp:status => item count,
     *                      informational only (SPEC §A.4 "count
     *                      reconciliation per status") — not itself a
     *                      pass/fail signal; see `ImportVerifier`'s own
     *                      docblock for what actually makes reconciliation
     *                      a hard failure.
     */
    public function __construct(
        public readonly int $totalItems,
        public readonly int $importedCount,
        public readonly array $countsByStatus,
        public readonly array $unresolvedLegacyUrls,
        public readonly array $wordCountOutliers,
        public readonly array $unknownShortcodes,
        public readonly array $unsupportedConstructs,
        public readonly array $footnotes,
        public readonly array $failedMediaDownloads,
        public readonly array $manualDecisionItems,
        public readonly array $otherNotices,
    ) {
    }

    /**
     * SPEC §A.4: "The import is idempotent and re-runnable until the
     * report is clean." A clean report has nothing left for a human to
     * look at — every list below is empty. `countsByStatus` is
     * deliberately excluded: a nonzero count of skipped drafts, say, is
     * normal and expected, not something re-running the import can ever
     * make go away.
     */
    public function isClean(): bool
    {
        return $this->unresolvedLegacyUrls === []
            && $this->wordCountOutliers === []
            && $this->unknownShortcodes === []
            && $this->unsupportedConstructs === []
            && $this->footnotes === []
            && $this->failedMediaDownloads === []
            && $this->manualDecisionItems === []
            && $this->otherNotices === [];
    }
}
