<?php

declare(strict_types=1);

namespace Cuniform\Admin\Build;

use Cuniform\Admin\AdminException;

/**
 * The admin side of SPEC §10.5's "Admin publish" trigger: "Admin writes a
 * request file; a systemd path unit runs the build as the build user."
 * `deploy/systemd/cuniform-build.path` (T27) already watches this exact
 * path with `PathExists` — this class is what makes the write side real.
 *
 * This is the entirety of the admin app's involvement in a build. It never
 * shells out to `bin/cuniform build`, never touches `releases/` or
 * `public/`, and holds no path to either — SPEC §10.5's privilege boundary
 * ("the admin PHP process never executes the build directly. It enqueues; a
 * separate unit consumes") is enforced at the OS level by the Unix-user
 * split between the FPM pool (`cuniform-web`) and the build-consumer unit
 * (`cuniform-build`, SPEC §15.1) — this class's own contribution is simply
 * to have no capability to do anything else, by construction: the only
 * path it ever touches is the one given to its constructor.
 *
 * `PathExists`, not `PathChanged` (see the `.path` unit's own comment) —
 * the file's content is never read by anything, so a plain existence
 * check via `touch()` is all `enqueue()` needs; there is nothing to make
 * atomic here the way a real content write would need to be.
 */
final class BuildRequestQueue
{
    public function __construct(private readonly string $requestFilePath)
    {
    }

    /**
     * Idempotent: a request already pending stays pending. Systemd's
     * `PathExists` only cares that the file exists, and
     * `cuniform-build.service`'s own `ExecStartPre` removes it before each
     * run — re-creation by a later enqueue() call is what re-arms the
     * trigger for a build after that.
     */
    public function enqueue(): void
    {
        $dir = dirname($this->requestFilePath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw AdminException::buildEnqueueFailed($dir);
        }

        // is_writable() checked explicitly, ahead of touch() itself, so a
        // permission failure is a clean AdminException rather than a raw
        // E_WARNING from touch() — same precondition-first shape
        // FilesystemGateway::resolve() already uses for is_readable().
        if (!is_writable($dir)) {
            throw AdminException::buildEnqueueFailed($dir);
        }

        if (!touch($this->requestFilePath)) {
            throw AdminException::buildEnqueueFailed($this->requestFilePath);
        }
    }

    public function isPending(): bool
    {
        return is_file($this->requestFilePath);
    }
}
