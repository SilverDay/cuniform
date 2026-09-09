<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * Stage 1 — Lock (SPEC §10.1): `flock` on `var/build.lock`. A concurrent
 * build is rejected outright, never queued — the caller gets a BuildException
 * immediately rather than waiting for the first build to finish.
 */
final class BuildLock
{
    /** @var resource|null */
    private $handle;

    public function __construct(private readonly string $lockPath)
    {
    }

    /**
     * @throws BuildException When the lock is already held by another process.
     */
    public function acquire(): void
    {
        $directory = dirname($this->lockPath);
        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw BuildException::fromErrors(["could not create lock directory: {$directory}"]);
        }

        $handle = fopen($this->lockPath, 'c');
        if ($handle === false) {
            throw BuildException::fromErrors(["could not open lock file: {$this->lockPath}"]);
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            throw BuildException::alreadyLocked($this->lockPath);
        }

        $this->handle = $handle;
    }

    public function release(): void
    {
        if ($this->handle === null) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }
}
