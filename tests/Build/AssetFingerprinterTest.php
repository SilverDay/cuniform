<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\AssetFingerprinter;
use Cuniform\Build\BuildException;
use PHPUnit\Framework\TestCase;

final class AssetFingerprinterTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'cuniform_css_') . '.css';
        file_put_contents($this->path, 'body { color: red; }');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testFilenameIsStableForTheSameContent(): void
    {
        [$fileA, $urlA] = (new AssetFingerprinter())->fingerprint($this->path);
        [$fileB, $urlB] = (new AssetFingerprinter())->fingerprint($this->path);

        self::assertSame($urlA, $urlB);
        self::assertSame($fileA->relativePath, $fileB->relativePath);
        self::assertMatchesRegularExpression('/^\/style\.[0-9a-f]{8}\.css$/', $urlA);
    }

    public function testFilenameChangesWhenContentChanges(): void
    {
        [, $urlBefore] = (new AssetFingerprinter())->fingerprint($this->path);

        file_put_contents($this->path, 'body { color: blue; }');
        [, $urlAfter] = (new AssetFingerprinter())->fingerprint($this->path);

        self::assertNotSame($urlBefore, $urlAfter);
    }

    public function testMissingSourceIsABuildError(): void
    {
        $this->expectException(BuildException::class);
        (new AssetFingerprinter())->fingerprint($this->path . '.does-not-exist');
    }
}
