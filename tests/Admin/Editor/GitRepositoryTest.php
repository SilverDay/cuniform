<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Editor;

use Cuniform\Admin\AdminException;
use Cuniform\Admin\Editor\GitRepository;
use PHPUnit\Framework\TestCase;

final class GitRepositoryTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        $this->repoRoot = sys_get_temp_dir() . '/cuniform_git_' . uniqid();
        mkdir($this->repoRoot, 0o755, true);
        $this->git(['git', 'init', '-q', '-b', 'main']);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->repoRoot);
    }

    public function testAddAndCommitCreatesACommitWithTheGivenAuthorAndReturnsItsSha(): void
    {
        file_put_contents($this->repoRoot . '/a.md', 'hello');

        $sha = (new GitRepository($this->repoRoot))->addAndCommit(['a.md'], 'Create a.md', 'Jane Operator', 'jane@example.test');

        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $sha);

        $log = $this->git(['git', 'log', '-1', '--format=%an <%ae>%n%s']);
        self::assertSame("Jane Operator <jane@example.test>\nCreate a.md\n", $log);
    }

    public function testAddAndCommitWithTwoPathsStagesARenameInOneCommit(): void
    {
        file_put_contents($this->repoRoot . '/old.md', 'content');
        (new GitRepository($this->repoRoot))->addAndCommit(['old.md'], 'Create old.md', 'Jane', 'jane@example.test');

        rename($this->repoRoot . '/old.md', $this->repoRoot . '/new.md');

        $sha = (new GitRepository($this->repoRoot))->addAndCommit(['old.md', 'new.md'], 'Move old.md to new.md', 'Jane', 'jane@example.test');

        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $sha);
        self::assertFileDoesNotExist($this->repoRoot . '/old.md');
        self::assertFileExists($this->repoRoot . '/new.md');

        $status = $this->git(['git', 'status', '--porcelain']);
        self::assertSame('', trim($status));
    }

    public function testCommittingWithNothingStagedThrows(): void
    {
        $this->expectException(AdminException::class);

        (new GitRepository($this->repoRoot))->addAndCommit(['does-not-exist.md'], 'Nothing to commit', 'Jane', 'jane@example.test');
    }

    public function testPushWithNoRemoteConfiguredFailsGracefullyRatherThanThrowing(): void
    {
        file_put_contents($this->repoRoot . '/a.md', 'content');
        (new GitRepository($this->repoRoot))->addAndCommit(['a.md'], 'Create a.md', 'Jane', 'jane@example.test');

        self::assertFalse((new GitRepository($this->repoRoot))->push());
    }

    /**
     * @param list<string> $command
     */
    private function git(array $command): string
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process     = proc_open($command, $descriptors, $pipes, $this->repoRoot);
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $stdout === false ? '' : $stdout;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) && !is_link($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}
