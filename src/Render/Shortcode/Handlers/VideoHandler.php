<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode\Handlers;

use Cuniform\Render\Shortcode\ShortcodeBodyType;
use Cuniform\Render\Shortcode\ShortcodeHandler;

/**
 * `[video src poster]` → self-hosted <video> (SPEC §4.5). Self-hosted only —
 * §14.3 rules out third-party embeds firing on page load; [embed] is the
 * click-to-load facade for actual third-party providers.
 */
final class VideoHandler implements ShortcodeHandler
{
    public function name(): string
    {
        return 'video';
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
        $src = UrlAttribute::sanitize($attributes['src'] ?? '');

        $posterAttr = '';
        if (($attributes['poster'] ?? '') !== '') {
            $poster     = UrlAttribute::sanitize($attributes['poster']);
            $posterAttr = " poster=\"{$poster}\"";
        }

        return "<video controls preload=\"metadata\" src=\"{$src}\"{$posterAttr}></video>";
    }
}
