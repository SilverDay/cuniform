<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * Stage 9 — Deploy (SPEC §10.1, §10.4): atomic swap of the `public/`
 * symlink onto a freshly-built release, then prune to the configured
 * `build.retain_releases` count (SPEC §15.1's "housekeeping" — a full site
 * copy per build otherwise accumulates without bound). `--rollback`
 * (SPEC §10.4, `bin/cuniform build --rollback`) re-points the symlink at
 * the release immediately before the current one, without building or
 * pruning anything.
 *
 * The swap itself: symlink the target release at `public.new`, then
 * `rename()` that over `public/`. `rename()` on a symlink is a single
 * filesystem operation — the same atomicity SPEC §10.4's shown
 * `mv -T public.new public` gets from `mv`, just called directly rather
 * than shelled out to, since PHP's own `rename()` already gives it. No
 * request ever observes `public/` missing or pointing at a half-written
 * release, because the release directory is fully written (SPEC §10.1
 * stages 1-7, plus Verify) before deploy() is ever called.
 *
 * SPEC's own swap line (`ln -sfn "releases/$TS" public.new`) targets a
 * path relative to the vhost root, since `public` and `releases` are
 * siblings there. This class is handed whatever `paths.public` /
 * `paths.releases` the config resolves to — true for the real deployment
 * layout, but not guaranteed for every caller (a test fixture, say) — so
 * it symlinks to the release's own path as given (normally already
 * absolute) rather than computing a relative one. Functionally
 * equivalent; `Options FollowSymLinks` doesn't care which form it's given.
 */
final class ReleaseDeployer
{
    public function __construct(
        private readonly string $publicPath,
        private readonly string $releasesRoot,
        private readonly int $retainReleases,
    ) {
    }

    /**
     * @throws BuildException When `public/` exists as a non-empty real
     *                        directory (the one-time setup SPEC §10.4 and
     *                        §3.3 describe — moving existing content out is
     *                        T27's job, not something to do silently here)
     *                        or the swap itself fails.
     */
    public function deploy(string $releaseDir): void
    {
        $this->replaceProvisionedDirectoryIfEmpty();
        $this->swap($releaseDir);
        $this->prune();
    }

    /**
     * @return string The release directory now live.
     *
     * @throws BuildException When `public/` isn't a deployed symlink, or
     *                        there is no release before the current one.
     */
    public function rollback(): string
    {
        $current = $this->currentTarget();
        if ($current === null) {
            throw BuildException::fromErrors([
                "'{$this->publicPath}' is not a deployed release (no symlink found) — nothing to roll back (SPEC §10.4)",
            ]);
        }

        $releases = $this->listReleases();
        $index    = array_search($current, $releases, true);

        if ($index === false || $index === 0) {
            throw BuildException::fromErrors([
                "no release before '{$current}' to roll back to (SPEC §10.4)",
            ]);
        }

        $previous = $releases[$index - 1];
        $this->swap($previous);

        return $previous;
    }

    /**
     * A directory left by provisioning (SPEC §10.4's "one-time setup") is
     * only ever removed here when it's empty — nothing to preserve, so
     * nothing to move out first. A non-empty one means real content is
     * sitting there (the §3.3/§15.5 cutover case), and this class doesn't
     * decide what happens to it; that's a deliberate, reviewed step
     * (T27), not a silent deploy-time deletion.
     */
    private function replaceProvisionedDirectoryIfEmpty(): void
    {
        if (is_link($this->publicPath) || !is_dir($this->publicPath)) {
            return;
        }

        $entries = array_diff(scandir($this->publicPath) ?: [], ['.', '..']);
        if ($entries !== []) {
            throw BuildException::fromErrors([
                "'{$this->publicPath}' exists as a non-empty real directory, not a symlink — "
                . 'one-time setup (SPEC §10.4, §3.3, §15.5) must move its contents out before the first deploy',
            ]);
        }

        if (!rmdir($this->publicPath)) {
            throw BuildException::fromErrors(["could not remove empty directory: {$this->publicPath}"]);
        }
    }

    private function swap(string $target): void
    {
        $tmp = $this->publicPath . '.new';

        // A leftover from an interrupted deploy — never the live symlink
        // itself, since rename() only ever lands on $this->publicPath.
        if (is_link($tmp) || file_exists($tmp)) {
            @unlink($tmp);
        }

        if (!@symlink($target, $tmp)) {
            throw BuildException::fromErrors(["could not create symlink: {$tmp} -> {$target}"]);
        }

        if (!@rename($tmp, $this->publicPath)) {
            @unlink($tmp);

            throw BuildException::fromErrors(["could not swap symlink into place: {$this->publicPath}"]);
        }
    }

    private function currentTarget(): ?string
    {
        if (!is_link($this->publicPath)) {
            return null;
        }

        $target = readlink($this->publicPath);

        return $target === false ? null : $target;
    }

    /**
     * Keeps the most recent `retainReleases` directories under `releases/`.
     * Only ever called from deploy(), immediately after swap() — so the
     * release just deployed is always the newest one and always inside
     * that window; rollback() never prunes, so an old release a rollback
     * points `public/` back at is never deleted out from under it either.
     */
    private function prune(): void
    {
        $releases = $this->listReleases();
        $keep     = array_slice($releases, -max($this->retainReleases, 1));

        foreach ($releases as $release) {
            if (!in_array($release, $keep, true)) {
                $this->removeDirectory($release);
            }
        }
    }

    /**
     * @return list<string> Release directory paths, ascending — release
     *                      directory names are `YmdHis` timestamps
     *                      (BuildPipeline::writeRelease()), so lexical and
     *                      chronological order coincide.
     */
    private function listReleases(): array
    {
        if (!is_dir($this->releasesRoot)) {
            return [];
        }

        $releases = array_values(array_filter(
            glob(rtrim($this->releasesRoot, '/') . '/*') ?: [],
            static fn (string $path): bool => is_dir($path) && !is_link($path)
        ));

        sort($releases);

        return $releases;
    }

    private function removeDirectory(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) && !is_link($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}
