<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * The "one-time setup" SPEC §10.4 and §3.3 describe: on a freshly
 * provisioned host, `public/` typically already exists as a real
 * directory (whatever the hosting provider's default placeholder is),
 * not the symlink `ReleaseDeployer` expects to swap. `ReleaseDeployer`
 * itself already handles the *empty*-directory case silently (nothing to
 * lose), but refuses a non-empty one outright — deciding what to do with
 * real content there is exactly the §15.5 cutover decision (freeze-and-
 * serve / delayed cutover / accept-the-gap), which this class does not
 * make. What it does is the safe, mechanical, reversible part: get a
 * non-empty `public/` out of the way by *moving* it aside — never
 * deleting it — so the first real deploy can proceed, and the operator
 * still has the original content to decide what to do with (this
 * project's own convention: prefer a reversible move-aside over deletion
 * for anything not created this session).
 */
final class PublicDirectorySetup
{
    public function __construct(private readonly string $publicPath)
    {
    }

    /**
     * @throws BuildException When a filesystem operation fails.
     */
    public function run(): string
    {
        if (is_link($this->publicPath)) {
            return "'{$this->publicPath}' is already a symlink — nothing to do.";
        }

        if (!is_dir($this->publicPath)) {
            return "'{$this->publicPath}' does not exist yet — the next `bin/cuniform build` will create it.";
        }

        $entries = array_diff(scandir($this->publicPath) ?: [], ['.', '..']);
        if ($entries === []) {
            if (!rmdir($this->publicPath)) {
                throw BuildException::fromErrors(["could not remove empty directory: {$this->publicPath}"]);
            }

            return "'{$this->publicPath}' was an empty directory — removed. "
                . 'The next `bin/cuniform build` will create the symlink.';
        }

        $backup = $this->publicPath . '.provisioned-' . date('YmdHis');
        if (!rename($this->publicPath, $backup)) {
            throw BuildException::fromErrors(["could not move '{$this->publicPath}' to '{$backup}'"]);
        }

        return "'{$this->publicPath}' had existing content — moved to '{$backup}' rather than deleted "
            . '(SPEC §10.4, §3.3, §15.5). Nothing was deleted. Decide what to do with it — freeze-and-serve, '
            . "delayed cutover, or accept-the-gap (SPEC §15.5) — before the next build:\n"
            . "  - freeze-and-serve: this is your source tree for T26's legacy snapshot.\n"
            . '  - otherwise: archive or discard it once you no longer need it.';
    }
}
