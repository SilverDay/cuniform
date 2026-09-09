<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode\Handlers;

use Cuniform\Render\Shortcode\ShortcodeBodyType;
use Cuniform\Render\Shortcode\ShortcodeHandler;

/**
 * `[note type=info|warn|danger]…[/note]` → callout (SPEC §4.5). An invalid or
 * missing `type` falls back to `info` rather than erroring — it is a
 * presentational attribute, not something SPEC lists as build-blocking.
 * Styling is the stylesheet's job (T17): this only emits the semantic class.
 */
final class NoteHandler implements ShortcodeHandler
{
    private const TYPES = ['info', 'warn', 'danger'];

    public function name(): string
    {
        return 'note';
    }

    public function bodyType(): ShortcodeBodyType
    {
        return ShortcodeBodyType::Markdown;
    }

    public function isBlockLevel(): bool
    {
        return true;
    }

    public function render(array $attributes, ?string $body): string
    {
        $type = $attributes['type'] ?? '';
        if (!in_array($type, self::TYPES, true)) {
            $type = 'info';
        }

        return "<div class=\"note note-{$type}\">{$body}</div>";
    }
}
