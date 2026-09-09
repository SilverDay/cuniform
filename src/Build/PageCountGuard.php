<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * "The page count drops more than 10% versus the current release" (SPEC
 * §10.3, threshold configurable via `build.page_count_drop_threshold`).
 * The "current release" is the most recent existing directory under
 * `paths.releases` — there's no deploy yet (T23) to point at `public/`'s
 * symlink target, so this is the closest available stand-in and the one
 * T23 can switch to later without changing this class's contract. A first
 * build, with no prior release to compare against, always passes.
 */
final class PageCountGuard
{
    public function __construct(private readonly string $releasesRoot)
    {
    }

    /**
     * @throws BuildException When the drop exceeds the threshold.
     */
    public function assert(int $newPageCount, float $dropThreshold): void
    {
        $message = $this->check($newPageCount, $dropThreshold);
        if ($message !== null) {
            throw BuildException::fromErrors([$message]);
        }
    }

    /**
     * Same check as assert(), but returns the violation message instead of
     * throwing — what BuildVerifier uses so it can collect this alongside
     * every other condition and wrap them all in one BuildException, rather
     * than wrapping an already-wrapped message a second time.
     */
    public function check(int $newPageCount, float $dropThreshold): ?string
    {
        $previousCount = $this->previousReleasePageCount();
        if ($previousCount === null || $previousCount === 0) {
            return null;
        }

        $drop = ($previousCount - $newPageCount) / $previousCount;
        if ($drop <= $dropThreshold) {
            return null;
        }

        $percent = round($drop * 100, 1);

        return "page count dropped {$percent}% ({$previousCount} -> {$newPageCount}), "
            . 'over the ' . round($dropThreshold * 100, 1) . '% threshold (SPEC §10.3)';
    }

    private function previousReleasePageCount(): ?int
    {
        $previous = $this->mostRecentRelease();
        if ($previous === null) {
            return null;
        }

        $count    = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($previous, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            \assert($fileInfo instanceof \SplFileInfo);
            if ($fileInfo->isFile() && $fileInfo->getFilename() === 'index.html') {
                $count++;
            }
        }

        return $count;
    }

    private function mostRecentRelease(): ?string
    {
        if (!is_dir($this->releasesRoot)) {
            return null;
        }

        $releases = array_values(array_filter(
            glob(rtrim($this->releasesRoot, '/') . '/*') ?: [],
            static fn (string $path): bool => is_dir($path)
        ));

        if ($releases === []) {
            return null;
        }

        sort($releases);

        return $releases[count($releases) - 1];
    }
}
