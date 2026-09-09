<?php

declare(strict_types=1);

namespace Cuniform\Tests\Render\Shortcode\Handlers;

use Cuniform\Render\Shortcode\Handlers\NoteHandler;
use PHPUnit\Framework\TestCase;

final class NoteHandlerTest extends TestCase
{
    public function testRendersEachDocumentedType(): void
    {
        foreach (['info', 'warn', 'danger'] as $type) {
            $html = (new NoteHandler())->render(['type' => $type], 'Body.');
            self::assertSame("<div class=\"note note-{$type}\">Body.</div>", $html);
        }
    }

    public function testMissingTypeDefaultsToInfo(): void
    {
        $html = (new NoteHandler())->render([], 'Body.');

        self::assertStringContainsString('note-info', $html);
    }

    public function testInvalidTypeDefaultsToInfo(): void
    {
        $html = (new NoteHandler())->render(['type' => 'sparkly'], 'Body.');

        self::assertStringContainsString('note-info', $html);
    }

    public function testIsBlockLevelWithMarkdownBody(): void
    {
        $handler = new NoteHandler();

        self::assertTrue($handler->isBlockLevel());
        self::assertSame('note', $handler->name());
    }
}
