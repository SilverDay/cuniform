<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\BuildLogEntry;
use Cuniform\Build\BuildLogReader;
use Cuniform\Build\BuildLogWriter;
use Cuniform\Build\BuildOutcome;
use PHPUnit\Framework\TestCase;

final class BuildLogReaderTest extends TestCase
{
    private string $root;
    private string $logPath;

    protected function setUp(): void
    {
        $this->root    = sys_get_temp_dir() . '/cuniform_buildlogreader_' . uniqid();
        $this->logPath = $this->root . '/log/build.jsonl';
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root . '/log')) {
            foreach (scandir($this->root . '/log') ?: [] as $item) {
                if ($item !== '.' && $item !== '..') {
                    unlink($this->root . '/log/' . $item);
                }
            }
            rmdir($this->root . '/log');
        }
        rmdir($this->root);
    }

    public function testRecentReturnsAnEmptyListWhenTheLogDoesNotExist(): void
    {
        self::assertSame([], (new BuildLogReader($this->logPath))->recent());
    }

    public function testRecentReturnsEntriesNewestFirst(): void
    {
        $writer = new BuildLogWriter($this->logPath);
        $writer->record(new BuildLogEntry(new \DateTimeImmutable('2026-01-01T10:00:00+00:00'), BuildOutcome::Success, 1.5, ['en' => 3], 5, '/releases/1', null));
        $writer->record(new BuildLogEntry(new \DateTimeImmutable('2026-01-01T11:00:00+00:00'), BuildOutcome::Failed, 0.2, [], 0, null, 'boom'));

        $entries = (new BuildLogReader($this->logPath))->recent();

        self::assertCount(2, $entries);
        self::assertSame(BuildOutcome::Failed, $entries[0]->outcome);
        self::assertSame('boom', $entries[0]->message);
        self::assertSame(BuildOutcome::Success, $entries[1]->outcome);
        self::assertSame(['en' => 3], $entries[1]->documentCountByLanguage);
        self::assertSame('/releases/1', $entries[1]->releaseDir);
    }

    public function testLatestReturnsTheSingleMostRecentEntry(): void
    {
        $writer = new BuildLogWriter($this->logPath);
        $writer->record(new BuildLogEntry(new \DateTimeImmutable('2026-01-01T10:00:00+00:00'), BuildOutcome::Success, 1.0, ['en' => 1], 1, '/releases/1', null));
        $writer->record(new BuildLogEntry(new \DateTimeImmutable('2026-01-01T11:00:00+00:00'), BuildOutcome::Success, 1.0, ['en' => 2], 2, '/releases/2', null));

        $latest = (new BuildLogReader($this->logPath))->latest();

        self::assertNotNull($latest);
        self::assertSame('/releases/2', $latest->releaseDir);
    }

    public function testLatestReturnsNullWhenTheLogIsEmpty(): void
    {
        self::assertNull((new BuildLogReader($this->logPath))->latest());
    }

    public function testAMalformedLineIsSkippedRatherThanBreakingTheWholeRead(): void
    {
        $dir = dirname($this->logPath);
        mkdir($dir, 0o755, true);
        file_put_contents($this->logPath, "not json\n" . json_encode(['timestamp' => 'x']) . "\n");

        (new BuildLogWriter($this->logPath))->record(new BuildLogEntry(new \DateTimeImmutable(), BuildOutcome::Success, 1.0, ['en' => 1], 1, '/releases/1', null));

        $entries = (new BuildLogReader($this->logPath))->recent();

        self::assertCount(1, $entries);
        self::assertSame(BuildOutcome::Success, $entries[0]->outcome);
    }
}
