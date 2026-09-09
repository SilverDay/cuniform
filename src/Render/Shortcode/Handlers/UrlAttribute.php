<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode\Handlers;

/**
 * Shared URL-attribute sanitizer for handlers whose attributes accept a URL
 * (figure/video's `src`, video's `poster`). Same scheme allow-list as the
 * renderer's own sanitiseUrl() (SPEC §4.5: "the handler is a trust boundary").
 */
final class UrlAttribute
{
    public static function sanitize(string $url): string
    {
        $url = trim($url);

        if (preg_match('#^(https?|ftps?)://#i', $url) || preg_match('~^[/?#]~', $url)) {
            return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return '#';
    }
}
