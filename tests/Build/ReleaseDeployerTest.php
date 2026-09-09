<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\BuildException;
use Cuniform\Build\ReleaseDeployer;
use PHPUnit\Framework\TestCase;

final class ReleaseDeployerTest extends TestCase
{
    private string $root;
    private string $publicPath;
    private string $releasesRoot;

    protected function setUp(): void
    {
        $this->root         = sys_get_temp_dir() . '/cuniform_deploy_' . uniqid();
        $this->publicPath   = $this->root . '/public';
        $this->releasesRoot = $this->root . '/releases';
        mkdir($this->releasesRoot, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testDeploySwapsPublicOntoTheReleaseWhenNothingExistsYet(): void
    {
        $release = $this->makeRelease('20260101120000', 'one');

        $this->deployer()->deploy($release);

        self::assertTrue(is_link($this->publicPath));
        self::assertSame($release, readlink($this->publicPath));
        self::assertSame('one', (string) file_get_contents($this->publicPath . '/marker.txt'));
    }

    public function testDeployReplacesAnEmptyRealDirectoryLeftByProvisioning(): void
    {
        mkdir($this->publicPath, 0o755, true);
        $release = $this->makeRelease('20260101120000', 'one');

        $this->deployer()->deploy($release);

        self::assertTrue(is_link($this->publicPath));
        self::assertSame($release, readlink($this->publicPath));
    }

    public function testDeployRejectsANonEmptyRealDirectory(): void
    {
        mkdir($this->publicPath, 0o755, true);
        file_put_contents($this->publicPath . '/index.html', 'legacy content');
        $release = $this->makeRelease('20260101120000', 'one');

        try {
            $this->deployer()->deploy($release);
            self::fail('expected a BuildException');
        } catch (BuildException $e) {
            self::assertStringContainsString('non-empty real directory', $e->getMessage());
        }

        self::assertDirectoryExists($this->publicPath);
        self::assertFalse(is_link($this->publicPath));
        self::assertFileExists($this->publicPath . '/index.html');
    }

    public function testDeploySwapsOntoASecondReleaseAtomically(): void
    {
        $first  = $this->makeRelease('20260101120000', 'one');
        $second = $this->makeRelease('20260101130000', 'two');

        $deployer = $this->deployer();
        $deployer->deploy($first);
        $deployer->deploy($second);

        self::assertSame($second, readlink($this->publicPath));
        self::assertSame('two', (string) file_get_contents($this->publicPath . '/marker.txt'));
    }

    public function testDeployPrunesReleasesBeyondTheRetainCount(): void
    {
        $releases = [];
        for ($i = 1; $i <= 7; $i++) {
            $releases[] = $this->makeRelease(sprintf('2026010112%04d', $i), "r{$i}");
        }

        $deployer = new ReleaseDeployer($this->publicPath, $this->releasesRoot, retainReleases: 5);
        foreach ($releases as $release) {
            $deployer->deploy($release);
        }

        $remaining = glob($this->releasesRoot . '/*') ?: [];
        sort($remaining);

        self::assertCount(5, $remaining, 'only the 5 most recent releases should survive');
        self::assertSame(array_slice($releases, -5), $remaining);
    }

    public function testRollbackNeverPrunesAndCanReachAReleaseOutsideTheRetainWindow(): void
    {
        $releases = [];
        for ($i = 1; $i <= 3; $i++) {
            $releases[] = $this->makeRelease(sprintf('2026010112%04d', $i), "r{$i}");
        }

        $deployer = new ReleaseDeployer($this->publicPath, $this->releasesRoot, retainReleases: 2);
        $deployer->deploy($releases[0]);
        $deployer->deploy($releases[1]);
        $deployer->deploy($releases[2]);

        // deploy() of $releases[2] pruned $releases[0] (outside the top-2
        // window), but rollback() never prunes — it can still repoint
        // public at whatever remains, one step back from what's live.
        $rolledTo = $deployer->rollback();
        self::assertSame($releases[1], $rolledTo);
        self::assertSame($releases[1], readlink($this->publicPath));
        self::assertDirectoryDoesNotExist($releases[0], 'a deploy prunes past the retain window regardless of an earlier rollback');
    }

    public function testRollbackFailsWhenPublicIsNotASymlink(): void
    {
        try {
            $this->deployer()->rollback();
            self::fail('expected a BuildException');
        } catch (BuildException $e) {
            self::assertStringContainsString('not a deployed release', $e->getMessage());
        }
    }

    public function testRollbackFailsWhenThereIsNoPreviousRelease(): void
    {
        $release = $this->makeRelease('20260101120000', 'one');
        $deployer = $this->deployer();
        $deployer->deploy($release);

        try {
            $deployer->rollback();
            self::fail('expected a BuildException');
        } catch (BuildException $e) {
            self::assertStringContainsString('no release before', $e->getMessage());
        }
    }

    public function testRollbackRepointsAtThePreviousReleaseAndCanBeRepeated(): void
    {
        $first  = $this->makeRelease('20260101120000', 'one');
        $second = $this->makeRelease('20260101130000', 'two');
        $third  = $this->makeRelease('20260101140000', 'three');

        $deployer = $this->deployer();
        $deployer->deploy($first);
        $deployer->deploy($second);
        $deployer->deploy($third);

        $rolledTo = $deployer->rollback();
        self::assertSame($second, $rolledTo);
        self::assertSame($second, readlink($this->publicPath));

        $rolledTo = $deployer->rollback();
        self::assertSame($first, $rolledTo);
        self::assertSame($first, readlink($this->publicPath));
    }

    public function testConcurrentReadsDuringRepeatedDeploysNeverSeeAMissingOrPartialPage(): void
    {
        $script = $this->root . '/reader.php';
        file_put_contents($script, <<<'PHP'
            <?php
            [, $publicPath, $resultPath, $deadline] = $argv;
            $failures = [];
            while (microtime(true) < (float) $deadline) {
                $marker = @file_get_contents($publicPath . '/marker.txt');
                if ($marker === false || !in_array($marker, ['one', 'two'], true)) {
                    $failures[] = var_export($marker, true);
                }
            }
            file_put_contents($resultPath, implode("\n", $failures));
            PHP);

        $one = $this->makeRelease('20260101120000', 'one');
        $two = $this->makeRelease('20260101130000', 'two');

        $deployer = $this->deployer();
        $deployer->deploy($one);

        $resultPath = $this->root . '/result.txt';
        $deadline   = microtime(true) + 1.0;
        $process    = proc_open(
            [PHP_BINARY, $script, $this->publicPath, $resultPath, (string) $deadline],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);

        while (microtime(true) < $deadline) {
            $deployer->deploy($two);
            $deployer->deploy($one);
        }

        proc_close($process);

        self::assertFileExists($resultPath);
        self::assertSame('', (string) file_get_contents($resultPath), 'a concurrent reader must never see a missing or partial page during a deploy');
    }

    private function deployer(int $retain = 5): ReleaseDeployer
    {
        return new ReleaseDeployer($this->publicPath, $this->releasesRoot, $retain);
    }

    private function makeRelease(string $timestamp, string $marker): string
    {
        $dir = $this->releasesRoot . '/' . $timestamp;
        mkdir($dir, 0o755, true);
        file_put_contents($dir . '/marker.txt', $marker);

        return $dir;
    }

    private function removeDirectory(string $path): void
    {
        if (is_link($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_link($full) || !is_dir($full) ? unlink($full) : $this->removeDirectory($full);
        }

        rmdir($path);
    }
}
