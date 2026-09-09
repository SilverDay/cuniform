<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * Decides which documents' Render+Template work an incremental build can
 * skip (SPEC §10.2). Two invalidation rules beyond a document's own base
 * key are applied explicitly, both because they can't be derived from a
 * per-document key comparison alone:
 *
 * - **Nav change invalidates everything.** SPEC §10.2: "a nav-affecting
 *   page invalidates everything" — every cached page's `layout.php` chrome
 *   embeds the nav tree, so if it changed at all, every cached page's
 *   bytes are stale regardless of whether that page's own content did.
 *   Detected by comparing `BuildCacheKey::navHash()` build-to-build,
 *   rather than trying to decide which front matter field on which page
 *   actually affects nav shape.
 * - **Translation-group propagation.** SPEC §10.2: "every page in its
 *   translation group [changes, since] its hreflang set changed." A
 *   document's own base key can be unchanged while its hreflang
 *   alternates list is still stale, if a *sibling* was added, removed, or
 *   edited. Detected by comparing each translation group's member set
 *   (this build vs. the cached one) and by checking whether any member is
 *   already directly dirty.
 *
 * One dependency this class does NOT track: `[include]` (SPEC §6.5). A
 * page reached only via `[include]`, not edited directly itself, does not
 * propagate to documents that include it — the render cache has no
 * visibility into which documents were included where (DocumentRenderer's
 * IncludeResolvingPageRepository resolves those independently, always
 * fresh, regardless of this cache). `--full` is the workaround after
 * editing a heavily-included page; a future task could close this gap by
 * having RenderedDocument report which pages it included.
 */
final class IncrementalPlanner
{
    /**
     * @param list<ResolvedDocument> $documents
     */
    public function plan(array $documents, CacheManifest $previous, string $currentNavHash, BuildCacheKey $keyComputer, bool $full): IncrementalPlan
    {
        $baseKeyByIdentifier = [];
        $currentGroups       = [];
        foreach ($documents as $document) {
            $identifier = $document->parsed->identifier();
            $baseKeyByIdentifier[$identifier] = $keyComputer->forDocument($document->parsed->discovered->sha256);

            $translationKey = $document->parsed->frontMatter->shared->translationKey;
            if ($translationKey !== null) {
                $currentGroups[$translationKey][] = $identifier;
            }
        }

        if ($full || $previous->documents === [] || $currentNavHash !== $previous->navHash) {
            return new IncrementalPlan([], fullRebuild: true, baseKeyByIdentifier: $baseKeyByIdentifier);
        }

        $directlyDirty = [];
        foreach ($baseKeyByIdentifier as $identifier => $baseKey) {
            $previousEntry = $previous->documents[$identifier] ?? null;
            if ($previousEntry === null || $previousEntry->baseKey !== $baseKey) {
                $directlyDirty[$identifier] = true;
            }
        }

        $previousGroups = [];
        foreach ($previous->documents as $identifier => $cached) {
            if ($cached->translationKey !== null) {
                $previousGroups[$cached->translationKey][] = $identifier;
            }
        }

        $dirty = $directlyDirty;
        foreach ([...array_keys($currentGroups), ...array_keys($previousGroups)] as $translationKey) {
            $current  = $currentGroups[$translationKey] ?? [];
            $previousMembers = $previousGroups[$translationKey] ?? [];
            sort($current);
            sort($previousMembers);

            $memberSetChanged = $current !== $previousMembers;
            $anyMemberDirty   = (bool) array_filter($current, static fn (string $id): bool => isset($directlyDirty[$id]));

            if ($memberSetChanged || $anyMemberDirty) {
                foreach ($current as $identifier) {
                    $dirty[$identifier] = true;
                }
            }
        }

        return new IncrementalPlan($dirty, fullRebuild: false, baseKeyByIdentifier: $baseKeyByIdentifier);
    }
}
