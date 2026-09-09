<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode\Handlers;

use Cuniform\Render\Md2Html;
use Cuniform\Render\Shortcode\ShortcodeBodyType;
use Cuniform\Render\Shortcode\ShortcodeHandler;

/**
 * `[toc]` → table of contents from the renderer's heading tree (SPEC §4.5,
 * §4.3). Takes the *same* Md2Html instance the ShortcodeProcessor is using —
 * ShortcodeProcessor defers every handler's render() until after the
 * document has gone through the renderer, specifically so that by the time
 * this runs, getHeadings() reflects the current document rather than being
 * empty or stale from whatever was rendered previously.
 */
final class TocHandler implements ShortcodeHandler
{
    public function __construct(private readonly Md2Html $renderer)
    {
    }

    public function name(): string
    {
        return 'toc';
    }

    public function bodyType(): ShortcodeBodyType
    {
        return ShortcodeBodyType::None;
    }

    public function isBlockLevel(): bool
    {
        return true;
    }

    public function render(array $attributes, ?string $body): string
    {
        $headings = $this->renderer->getHeadings();
        if ($headings === []) {
            return '';
        }

        $items = '';
        foreach ($headings as $heading) {
            $id   = htmlspecialchars($heading['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $text = $heading['text'];
            $items .= "<li class=\"toc-level-{$heading['level']}\"><a href=\"#{$id}\">{$text}</a></li>";
        }

        return "<nav class=\"toc\"><ul>{$items}</ul></nav>";
    }
}
