<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

use Cuniform\Admin\AdminException;

/**
 * `git` invoked via `proc_open` with an argument array (SPEC §13.2,
 * CLAUDE.md security rules: "Never build a shell string") — every command
 * here is a `list<string>` passed straight to `proc_open()`, which execs it
 * directly with no shell in between, so there is nothing for a value
 * containing a shell metacharacter to break out into.
 *
 * Author/committer identity is set per-invocation with `-c user.name=` /
 * `-c user.email=` rather than relying on a global `~/.gitconfig` existing
 * for whichever system user runs the admin FPM pool (SPEC §15.1's
 * `cuniform-web`) — SPEC §12's "git commit with the session identity" is
 * the operator who is actually signed in, not a fixed service identity.
 */
final class GitRepository
{
    public function __construct(private readonly string $repositoryRoot)
    {
    }

    /**
     * `git add -A -- <paths>` followed by `git commit`. Passing more than
     * one path (EditorDocumentStore::move()'s own case: an old path and a
     * new one) stages a deletion and an addition in the same commit, which
     * git's own history view then recognises as a rename — the `git mv`
     * effect, reached without shelling out to a second command.
     *
     * @param  list<string> $relativePaths
     * @return string       The new commit's SHA (`git rev-parse HEAD`).
     *
     * @throws AdminException When git exits non-zero at either step.
     */
    public function addAndCommit(array $relativePaths, string $message, string $authorName, string $authorEmail): string
    {
        $this->run(['git', 'add', '-A', '--', ...$relativePaths]);

        $this->run([
            'git',
            '-c', "user.name={$authorName}",
            '-c', "user.email={$authorEmail}",
            'commit',
            '-m', $message,
            '--author', "{$authorName} <{$authorEmail}>",
        ]);

        return trim($this->run(['git', 'rev-parse', 'HEAD']));
    }

    /**
     * SPEC §12: "optionally push." Not called anywhere in EditorDocumentStore
     * yet — see BUILD-ORDER.md's T29 note for why wiring this to fire
     * automatically on every save is deliberately deferred rather than
     * built. Kept as a real, tested capability so a future opt-in only has
     * to call it, not build it.
     */
    public function push(string $remote = 'origin', string $branch = 'HEAD'): bool
    {
        try {
            $this->run(['git', 'push', $remote, $branch]);

            return true;
        } catch (AdminException) {
            return false;
        }
    }

    /**
     * @param list<string> $command
     */
    private function run(array $command): string
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process     = proc_open($command, $descriptors, $pipes, $this->repositoryRoot);

        if (!is_resource($process)) {
            throw AdminException::gitCommandFailed($command, 'could not start process');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw AdminException::gitCommandFailed($command, ($stderr !== false && trim($stderr) !== '') ? $stderr : (string) $stdout);
        }

        return $stdout === false ? '' : $stdout;
    }
}
