<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * Combines alias-derived entries (SPEC §5.2 `aliases`, §1.2) with manual
 * entries from `content/redirects.map` (§8.3) into one redirect set.
 *
 * Two things this stage — not stage 4 (Resolve), despite alias handling
 * otherwise living there — is responsible for on its own:
 *  - Dropping a manual entry whose old-path is exactly `/`: §8.3's own
 *    ordering caveat says this is redundant with the root's 302 (§7.4.1)
 *    and "should be omitted rather than allowed to compete" — so it's
 *    dropped with a warning rather than left in to conflict.
 *  - Rejecting two entries (from either source) that claim the same
 *    old-path: an unambiguous compiled map is this stage's whole job, and
 *    two conflicting targets for one source path can't both be right.
 *
 * What this stage deliberately does *not* check: whether a redirect's
 * *target* actually resolves in this build. SPEC §10.3 assigns that to the
 * Verify stage (T22), which runs after the whole release tree exists to
 * check against — this stage only has stage 4's route URLs and the raw
 * manual entries, not the final on-disk output Verify inspects.
 */
final class RedirectMapCompiler
{
    /**
     * @param  list<RedirectEntry> $manualEntries
     * @return array{entries: list<RedirectEntry>, warnings: list<string>}
     *
     * @throws BuildException When two entries claim the same old-path.
     */
    public function compile(ResolvedSite $site, array $manualEntries): array
    {
        $warnings = [];
        $entries  = [...$this->aliasEntries($site), ...$manualEntries];

        $kept = [];
        foreach ($entries as $entry) {
            if ($entry->oldPath === '/') {
                $warnings[] = "redirects.map entry for '/' is redundant with the root's own 302 "
                    . '(SPEC §8.3) and was omitted';

                continue;
            }

            $kept[] = $entry;
        }

        $this->assertNoDuplicates($kept);

        return ['entries' => $kept, 'warnings' => $warnings];
    }

    /**
     * @return list<RedirectEntry>
     */
    private function aliasEntries(ResolvedSite $site): array
    {
        $entries = [];
        foreach ($site->documents as $document) {
            foreach ($document->parsed->frontMatter->shared->aliases as $alias) {
                $entries[] = new RedirectEntry($alias, $document->url, $document->parsed->identifier());
            }
        }

        return $entries;
    }

    /**
     * @param list<RedirectEntry> $entries
     *
     * @throws BuildException
     */
    private function assertNoDuplicates(array $entries): void
    {
        /** @var array<string, RedirectEntry> $bySource */
        $bySource = [];
        $errors   = [];

        foreach ($entries as $entry) {
            $existing = $bySource[$entry->oldPath] ?? null;
            if ($existing !== null) {
                $errors[] = "redirect old-path '{$entry->oldPath}' is claimed by both "
                    . "'{$existing->source}' and '{$entry->source}'";

                continue;
            }

            $bySource[$entry->oldPath] = $entry;
        }

        if ($errors !== []) {
            throw BuildException::fromErrors($errors);
        }
    }
}
