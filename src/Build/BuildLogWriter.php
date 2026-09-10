<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * Appends one line to `var/log/build.jsonl` (SPEC §15.4). Called from
 * `Cli\Application::build()`, the one place every real trigger (git push's
 * `post-receive`, the admin-enqueue `.path` unit, the scheduled-post
 * `.timer`, and a manual `bin/cuniform build`) converges on — see that
 * class's own docblock — rather than from `BuildPipeline` itself, which
 * stays a pure build-the-release-tree component with no logging concern of
 * its own. Only a real (non-`--dry-run`) attempt is ever logged: a dry run
 * writes nothing and deploys nothing, so it has no outcome a "last build
 * status" dashboard reading should reflect.
 */
final class BuildLogWriter
{
    public function __construct(private readonly string $logPath)
    {
    }

    public function record(BuildLogEntry $entry): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw BuildException::fromErrors(["could not create directory: {$dir}"]);
        }

        if (!is_writable($dir)) {
            throw BuildException::fromErrors(["build log directory is not writable: {$dir}"]);
        }

        $line = json_encode($entry->toArray(), JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX) === false) {
            throw BuildException::fromErrors(["could not write build log: {$this->logPath}"]);
        }
    }
}
