<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Audit;

use Cuniform\Admin\AdminException;
use Cuniform\Admin\Audit\AuditLogEntry;
use Cuniform\Admin\Audit\AuditLogWriter;
use PHPUnit\Framework\TestCase;

final class AuditLogWriterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cuniform_auditwriter_' . uniqid();
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testRecordCreatesTheParentDirectoryAndAppendsOneJsonLine(): void
    {
        $path = $this->root . '/log/audit.jsonl';

        (new AuditLogWriter($path))->record(new AuditLogEntry(new \DateTimeImmutable('2026-03-14T10:00:00+01:00'), 'Jane', 'create', 'posts/en/2026/a.md', 'deadbeef'));

        self::assertFileExists($path);
        $decoded = json_decode(trim((string) file_get_contents($path)), true);
        self::assertIsArray($decoded);
        self::assertSame('Jane', $decoded['actor']);
        self::assertSame('deadbeef', $decoded['commit_sha']);
    }

    public function testRecordAppendsRatherThanOverwriting(): void
    {
        $path   = $this->root . '/log/audit.jsonl';
        $writer = new AuditLogWriter($path);

        $writer->record(new AuditLogEntry(new \DateTimeImmutable(), 'Jane', 'create', 'a.md', 'sha1'));
        $writer->record(new AuditLogEntry(new \DateTimeImmutable(), 'Jane', 'update', 'a.md', 'sha2'));

        $lines = array_filter(explode("\n", (string) file_get_contents($path)));
        self::assertCount(2, $lines);
    }

    public function testRecordThrowsWhenTheDirectoryCannotBeWritten(): void
    {
        $dir = $this->root . '/readonly';
        mkdir($dir, 0o555, true);

        try {
            $this->expectException(AdminException::class);
            (new AuditLogWriter($dir . '/audit.jsonl'))->record(new AuditLogEntry(new \DateTimeImmutable(), 'Jane', 'create', 'a.md', 'sha1'));
        } finally {
            chmod($dir, 0o755);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        chmod($path, 0o755);
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) && !is_link($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}
