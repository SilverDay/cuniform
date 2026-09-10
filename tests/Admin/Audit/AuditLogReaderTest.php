<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Audit;

use Cuniform\Admin\Audit\AuditLogEntry;
use Cuniform\Admin\Audit\AuditLogReader;
use Cuniform\Admin\Audit\AuditLogWriter;
use PHPUnit\Framework\TestCase;

final class AuditLogReaderTest extends TestCase
{
    private string $root;
    private string $logPath;

    protected function setUp(): void
    {
        $this->root    = sys_get_temp_dir() . '/cuniform_auditreader_' . uniqid();
        $this->logPath = $this->root . '/log/audit.jsonl';
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $items = is_dir($this->root . '/log') ? scandir($this->root . '/log') : [];
        foreach ($items ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                unlink($this->root . '/log/' . $item);
            }
        }
        if (is_dir($this->root . '/log')) {
            rmdir($this->root . '/log');
        }
        rmdir($this->root);
    }

    public function testRecentReturnsAnEmptyListWhenTheLogDoesNotExist(): void
    {
        self::assertSame([], (new AuditLogReader($this->logPath))->recent());
    }

    public function testRecentReturnsEntriesNewestFirst(): void
    {
        $writer = new AuditLogWriter($this->logPath);
        $writer->record(new AuditLogEntry(new \DateTimeImmutable('2026-01-01T10:00:00+00:00'), 'Jane', 'create', 'posts/en/2026/a.md', 'a1a1a1'));
        $writer->record(new AuditLogEntry(new \DateTimeImmutable('2026-01-01T11:00:00+00:00'), 'Jane', 'update', 'posts/en/2026/a.md', 'b2b2b2'));

        $entries = (new AuditLogReader($this->logPath))->recent();

        self::assertCount(2, $entries);
        self::assertSame('update', $entries[0]->action);
        self::assertSame('create', $entries[1]->action);
    }

    public function testRecentRespectsTheLimit(): void
    {
        $writer = new AuditLogWriter($this->logPath);
        for ($i = 0; $i < 5; $i++) {
            $writer->record(new AuditLogEntry(new \DateTimeImmutable(), 'Jane', 'update', "posts/en/2026/{$i}.md", "sha{$i}"));
        }

        self::assertCount(3, (new AuditLogReader($this->logPath))->recent(3));
    }

    public function testAMalformedLineIsSkippedRatherThanBreakingTheWholeRead(): void
    {
        $dir = dirname($this->logPath);
        mkdir($dir, 0o755, true);
        file_put_contents($this->logPath, "not json at all\n" . json_encode(['timestamp' => 'also not valid']) . "\n");

        (new AuditLogWriter($this->logPath))->record(new AuditLogEntry(new \DateTimeImmutable(), 'Jane', 'create', 'posts/en/2026/a.md', 'sha1'));

        $entries = (new AuditLogReader($this->logPath))->recent();

        self::assertCount(1, $entries);
        self::assertSame('create', $entries[0]->action);
    }
}
