<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * "Any entry in redirects.map points at a path that does not exist in the
 * new release" (SPEC §10.3) — a redirect to a 404 is worse than no
 * redirect, and with every legacy URL now redirected (§7.4, §8.3), this is
 * what keeps a cutover honest.
 */
final class RedirectTargetChecker
{
    /**
     * @param  list<RedirectEntry> $entries
     * @param  array<string, true> $knownReleasePaths
     * @return list<string>
     */
    public function check(array $entries, array $knownReleasePaths): array
    {
        $errors = [];

        foreach ($entries as $entry) {
            $releasePath = RoutePathResolver::toReleaseFilePath($entry->newPath);
            if (!isset($knownReleasePaths[$releasePath])) {
                $errors[] = "redirect '{$entry->oldPath}' -> '{$entry->newPath}' points at a path that "
                    . 'does not exist in this release (SPEC §10.3)';
            }
        }

        return $errors;
    }
}
