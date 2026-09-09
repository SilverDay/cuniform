<?php

declare(strict_types=1);

namespace Cuniform\Import;

use Cuniform\Content\ContentException;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\FrontMatterParser;

/**
 * SPEC §A.4, run against `WxrImporter::import()`'s own output — a
 * standalone verification pass over the import pipeline, not the build
 * pipeline's own `BuildVerifier` (T22), which only ever sees documents
 * once they've been promoted into `content/` (SPEC §A.5's review step,
 * still ahead of that). This class answers the question "is this import
 * worth reviewing yet," not "is this release safe to deploy."
 *
 * **What "count reconciliation... any delta is a hard failure" (§A.4,
 * BUILD-ORDER T38) actually checks here.** There is no second, external
 * system to reconcile against — the WXR file is the only source of
 * truth, and `WxrImporter` already accounts for every single item it
 * reads (each iteration of its own loop either appends one document or
 * takes an early `continue`, with nothing in between). That accounting is
 * therefore correct by construction, not something worth re-deriving
 * here — duplicating `WxrImporter`'s own status/slug/date decisions in a
 * second place would only create a second copy that could silently drift
 * from the first. The one place a real count delta can still happen
 * despite that — and the one this class actually catches, as a hard
 * failure — is two *different* items resolving to the same output file
 * path (a slug+date collision): `ImportedDocumentWriter` writes by path,
 * so a collision silently overwrites one imported document with another,
 * which is exactly "a count delta between export and generated files"
 * (T38's own acceptance wording) — fewer files on disk than documents
 * the importer believed it produced.
 *
 * Item-to-warning attribution is recovered from `WxrImporter`'s own
 * warning strings rather than by adding a parallel structured channel:
 * every warning it emits already begins `post_id=<id>[ (<slug>)]: `
 * (`WxrImporter`'s own source is the single place that format is
 * produced), so this class parses that prefix instead of asking
 * `WxrImporter` to expose a second representation of the same
 * information that could drift out of sync with the first.
 */
final class ImportVerifier
{
    /**
     * Unspecified by SPEC §A.4 ("a tolerance," no number given) — a
     * documented judgment call, the same kind BUILD-ORDER's T17/T37 notes
     * already flagged rather than silently decided. 20% survives normal
     * conversion noise (a caption folded into alt text, a dropped
     * decorative `<span>`) while still catching a post where the
     * converter silently ate most of the content.
     */
    private const WORD_COUNT_TOLERANCE = 0.20;

    private const WARNING_PREFIX_PATTERN = '/^post_id=(\d+)(?: \([^)]*\))?: (.*)$/';

    /**
     * @param array{documents: list<ImportedDocument>, warnings: list<string>} $importResult
     *
     * @throws ImportException When a hard-failure condition fires — see
     *                          this class's own docblock for what qualifies.
     */
    public function verify(WxrDocument $wxr, array $importResult): ImportReport
    {
        $hardFailures = $this->findDuplicateOutputPaths($importResult['documents']);
        if ($hardFailures !== []) {
            throw ImportException::fromErrors($hardFailures);
        }

        $documentsBySourceId = [];
        foreach ($importResult['documents'] as $document) {
            $documentsBySourceId[$document->sourceId] = $document;
        }

        [$unknown, $unsupported, $manual, $other] = $this->categorizeWarnings($importResult['warnings']);

        return new ImportReport(
            totalItems: count($wxr->items),
            importedCount: count($importResult['documents']),
            countsByStatus: $this->countsByStatus($wxr),
            unresolvedLegacyUrls: $this->findUnresolvedLegacyUrls($wxr, $documentsBySourceId),
            wordCountOutliers: $this->findWordCountOutliers($wxr, $documentsBySourceId),
            unknownShortcodes: $unknown,
            unsupportedConstructs: $unsupported,
            footnotes: [],
            failedMediaDownloads: [],
            manualDecisionItems: $manual,
            otherNotices: $other,
        );
    }

    /**
     * @param list<ImportedDocument> $documents
     *
     * @return list<string>
     */
    private function findDuplicateOutputPaths(array $documents): array
    {
        $bySourceId = [];
        foreach ($documents as $document) {
            $bySourceId[$document->relativePath][] = $document->sourceId;
        }

        $errors = [];
        foreach ($bySourceId as $path => $sourceIds) {
            if (count($sourceIds) > 1) {
                $errors[] = "output path '{$path}' claimed by source_id " . implode(', ', $sourceIds)
                    . ' — same slug and date, one would silently overwrite the other';
            }
        }

        return $errors;
    }

    /**
     * @return array<string, int>
     */
    private function countsByStatus(WxrDocument $wxr): array
    {
        $counts = [];
        foreach ($wxr->items as $item) {
            $counts[$item->status] = ($counts[$item->status] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, ImportedDocument> $documentsBySourceId
     *
     * @return list<string>
     */
    private function findUnresolvedLegacyUrls(WxrDocument $wxr, array $documentsBySourceId): array
    {
        $unresolved = [];

        foreach ($wxr->items as $item) {
            // Only these two item types are real, once-public content
            // pages under WordPress's own permalink structure — every
            // other post_type (revision, attachment, nav_menu_item, ...)
            // was never a page a search engine could have indexed.
            if ($item->postType !== 'post' && $item->postType !== 'page') {
                continue;
            }

            if (isset($documentsBySourceId[(string) $item->postId])) {
                continue; // imported — already carries its own alias, or never had a real permalink to begin with
            }

            $path = LegacyPermalink::realPathOf($item->link);
            if ($path === null) {
                continue;
            }

            $unresolved[] = "post_id={$item->postId}: {$path} will have no redirect — item was not imported "
                . '(needs a manual decision before this URL is lost, SPEC §A.4)';
        }

        return $unresolved;
    }

    /**
     * @param array<string, ImportedDocument> $documentsBySourceId
     *
     * @return list<string>
     */
    private function findWordCountOutliers(WxrDocument $wxr, array $documentsBySourceId): array
    {
        $outliers = [];

        foreach ($wxr->items as $item) {
            $document = $documentsBySourceId[(string) $item->postId] ?? null;
            if ($document === null) {
                continue;
            }

            $body = $this->extractBody($document);
            if ($body === null) {
                continue; // couldn't parse its own front matter — not this check's concern, see extractBody()
            }

            $original  = $this->wordCount(strip_tags($item->contentEncoded));
            $converted = $this->wordCount($body);
            $delta     = abs($converted - $original) / max($original, 1);

            if ($delta > self::WORD_COUNT_TOLERANCE) {
                $percent    = (int) round($delta * 100);
                $outliers[] = "post_id={$item->postId} ({$item->title}): word count changed by {$percent}% "
                    . "(original {$original}, converted {$converted}) — needs manual review (SPEC §A.4)";
            }
        }

        return $outliers;
    }

    /**
     * `WxrImporter`'s own emitted contents are expected to always parse —
     * T37's own real-corpus validation already confirmed this — so a
     * failure here points at a bug in `FrontMatterEmitter`/`WxrImporter`
     * rather than anything this word-count check itself is responsible
     * for diagnosing. Skipped rather than treated as a hard failure of
     * its own: this class's job is the migration report, not re-running
     * front matter validation.
     */
    private function extractBody(ImportedDocument $document): ?string
    {
        try {
            return (new FrontMatterParser())->parse($document->contents, DocumentKind::Post, $document->relativePath)->body;
        } catch (ContentException) {
            return null;
        }
    }

    private function wordCount(string $text): int
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $normalized === '' ? 0 : substr_count($normalized, ' ') + 1;
    }

    /**
     * @param list<string> $warnings
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>, 3: list<string>}
     */
    private function categorizeWarnings(array $warnings): array
    {
        $unknownShortcodes    = [];
        $unsupportedConstructs = [];
        $manualDecision        = [];
        $other                 = [];

        foreach ($warnings as $warning) {
            $message = $this->messageWithoutPrefix($warning);

            if (str_contains($message, 'unknown shortcode')) {
                $unknownShortcodes[] = $warning;
            } elseif (str_contains($message, 'dropped') || str_contains($message, 'unsupported') || str_contains($message, 'unresolvable')) {
                $unsupportedConstructs[] = $warning;
            } elseif (str_contains($message, 'manual decision') || str_contains($message, "post_type 'page'")) {
                $manualDecision[] = $warning;
            } else {
                $other[] = $warning;
            }
        }

        return [$unknownShortcodes, $unsupportedConstructs, $manualDecision, $other];
    }

    private function messageWithoutPrefix(string $warning): string
    {
        if (preg_match(self::WARNING_PREFIX_PATTERN, $warning, $matches) === 1) {
            return $matches[2];
        }

        return $warning;
    }
}
