<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode\Handlers;

use Cuniform\Render\Shortcode\ShortcodeBodyType;
use Cuniform\Render\Shortcode\ShortcodeHandler;

/**
 * `[details summary]…[/details]` → collapsible block (SPEC §4.5), using the
 * native <details>/<summary> elements — no JavaScript needed.
 */
final class DetailsHandler implements ShortcodeHandler
{
    public function name(): string
    {
        return 'details';
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
        $summary = htmlspecialchars($attributes['summary'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return "<details><summary>{$summary}</summary>{$body}</details>";
    }
}
