<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin;

use PHPUnit\Framework\TestCase;

/**
 * T32 acceptance (SPEC §10.5, §13.2): "the admin process cannot write to
 * `releases/` or `public` — verified by file permissions, not by
 * convention." The real enforcement is the OS-level Unix-user split SPEC
 * §15.1 requires (`cuniform-web`, the FPM pool user, has no write access to
 * `releases/`/`public`; only `cuniform-build`, the systemd build-consumer
 * unit's user, does) — that boundary needs a real multi-user host to
 * exercise for real, which this environment cannot provide (same limit
 * BUILD-ORDER.md's own T27 note already documents for its ACME criterion).
 *
 * What this test verifies instead, durably rather than by a one-time
 * read-through: no file under `src/Admin/` or `admin/` — the entire admin
 * application's source — ever references `Config::$paths->releases` or
 * `Config::$paths->public`. Those two properties are the only way any code
 * in this codebase constructs a path into either directory (ReleaseDeployer
 * and BuildPipeline's own writers are the sole legitimate readers of them),
 * so their total absence from the admin application's source is a
 * structural guarantee — not "the code happens not to do this today," but
 * "there is no path expression anywhere in the admin application capable of
 * naming either directory" — and a future change that introduces one fails
 * this test immediately, the same day it's written, rather than being
 * caught by a later manual audit.
 */
final class AdminWriteBoundaryTest extends TestCase
{
    private const FORBIDDEN = ['paths->releases', 'paths->public'];

    public function testNoAdminSourceFileReferencesTheReleasesOrPublicPaths(): void
    {
        $offenders = [];

        foreach ($this->phpFilesUnder(__DIR__ . '/../../src/Admin') as $file) {
            $offenders = [...$offenders, ...$this->scan($file)];
        }

        foreach ($this->phpFilesUnder(__DIR__ . '/../../admin') as $file) {
            $offenders = [...$offenders, ...$this->scan($file)];
        }

        self::assertSame([], $offenders);
    }

    /**
     * @return list<string>
     */
    private function scan(string $file): array
    {
        $contents = (string) file_get_contents($file);
        $hits     = [];

        foreach (self::FORBIDDEN as $needle) {
            if (str_contains($contents, $needle)) {
                $hits[] = "{$file} references '{$needle}'";
            }
        }

        return $hits;
    }

    /**
     * @return list<string>
     */
    private function phpFilesUnder(string $root): array
    {
        $real = realpath($root);
        if ($real === false) {
            return [];
        }

        $files    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            \assert($fileInfo instanceof \SplFileInfo);
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo->getPathname();
            }
        }

        return $files;
    }
}
