<?php

declare(strict_types=1);

namespace Cuniform\Tests\Content;

use Cuniform\Content\ContentException;
use Cuniform\Content\FilesystemGateway;
use PHPUnit\Framework\TestCase;

final class FilesystemGatewayTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cuniform_fsgw_' . uniqid();
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        // A sibling directory used by the partial-prefix test, if it was created.
        $this->removeDirectory($this->root . '-other');
    }

    public function testResolveAcceptsAMarkdownFileInsideContentRoot(): void
    {
        $path = $this->write('post.md', '# Hello');

        $gateway = new FilesystemGateway($this->root);

        self::assertSame(realpath($path), $gateway->resolve($path));
    }

    public function testResolveAcceptsAMarkdownFileInANestedDirectory(): void
    {
        mkdir($this->root . '/de/2026', 0o755, true);
        $path = $this->write('de/2026/post.md', '# Nested');

        $gateway = new FilesystemGateway($this->root);

        self::assertSame(realpath($path), $gateway->resolve($path));
    }

    public function testExtensionCheckIsCaseInsensitive(): void
    {
        $path = $this->write('post.MD', '# Hello');

        $gateway = new FilesystemGateway($this->root);

        self::assertSame(realpath($path), $gateway->resolve($path));
    }

    public function testReadReturnsFileContents(): void
    {
        $path = $this->write('post.md', '# Hello, World!');

        $gateway = new FilesystemGateway($this->root);

        self::assertSame('# Hello, World!', $gateway->read($path));
    }

    public function testResolveRejectsNonExistentFile(): void
    {
        $gateway = new FilesystemGateway($this->root);

        $this->expectException(ContentException::class);
        $gateway->resolve($this->root . '/does-not-exist.md');
    }

    public function testResolveRejectsDisallowedExtension(): void
    {
        $path    = $this->write('post.txt', '# Hello');
        $gateway = new FilesystemGateway($this->root);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches('/Disallowed file extension/');
        $gateway->resolve($path);
    }

    public function testResolveRejectsPathTraversalOutsideContentRoot(): void
    {
        mkdir($this->root . '/inner', 0o755, true);
        file_put_contents(dirname($this->root) . '/outside-' . basename($this->root) . '.md', '# Outside');
        $outside = dirname($this->root) . '/outside-' . basename($this->root) . '.md';

        $gateway  = new FilesystemGateway($this->root . '/inner');
        $traverse = $this->root . '/inner/../../' . basename($outside);

        try {
            $this->expectException(ContentException::class);
            $this->expectExceptionMessageMatches('/outside the content root/');
            $gateway->resolve($traverse);
        } finally {
            @unlink($outside);
        }
    }

    public function testResolveRejectsSymlinkEscapingContentRoot(): void
    {
        $outside = sys_get_temp_dir() . '/cuniform_fsgw_outside_' . uniqid() . '.md';
        file_put_contents($outside, '# Outside');
        $link = $this->root . '/escape.md';
        symlink($outside, $link);

        $gateway = new FilesystemGateway($this->root);

        try {
            $this->expectException(ContentException::class);
            $this->expectExceptionMessageMatches('/outside the content root/');
            $gateway->resolve($link);
        } finally {
            @unlink($link);
            @unlink($outside);
        }
    }

    public function testResolveRejectsFileExceedingMaxBytes(): void
    {
        $path    = $this->write('post.md', str_repeat('x', 20));
        $gateway = new FilesystemGateway($this->root, maxBytes: 10);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches('/exceeds the maximum allowed size/');
        $gateway->resolve($path);
    }

    public function testResolveRejectsUnreadableFile(): void
    {
        if (posix_getuid() === 0) {
            self::markTestSkipped('Running as root; is_readable() ignores file permissions.');
        }

        $path = $this->write('post.md', '# Hello');
        chmod($path, 0o000);

        $gateway = new FilesystemGateway($this->root);

        try {
            $this->expectException(ContentException::class);
            $this->expectExceptionMessageMatches('/not readable/');
            $gateway->resolve($path);
        } finally {
            chmod($path, 0o644);
        }
    }

    public function testConstructorRejectsAMissingContentRoot(): void
    {
        $this->expectException(ContentException::class);
        new FilesystemGateway($this->root . '/does-not-exist');
    }

    public function testContainmentRejectsASiblingDirectoryWithASharedPrefix(): void
    {
        // Guards against a naive prefix check: "$this->root" is a string-prefix
        // of "$this->root-other", but the latter is not inside it.
        $siblingRoot = $this->root . '-other';
        mkdir($siblingRoot, 0o755, true);
        file_put_contents($siblingRoot . '/post.md', '# Sibling');

        $gateway = new FilesystemGateway($this->root);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches('/outside the content root/');
        $gateway->resolve($siblingRoot . '/post.md');
    }

    private function write(string $relativePath, string $contents): string
    {
        $path = $this->root . '/' . $relativePath;
        file_put_contents($path, $contents);

        return $path;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_link($path) || is_file($path)) {
                unlink($path);
            } else {
                $this->removeDirectory($path);
            }
        }

        rmdir($dir);
    }
}
