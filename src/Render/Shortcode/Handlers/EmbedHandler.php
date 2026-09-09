<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode\Handlers;

use Cuniform\Render\Shortcode\ShortcodeBodyType;
use Cuniform\Render\Shortcode\ShortcodeHandler;

/**
 * `[embed provider id]` → click-to-load facade; no third-party request until
 * clicked (SPEC §4.5, §14.3). `provider` is a closed allow-list — youtube and
 * vimeo, the two providers common enough to be worth a dedicated facade; an
 * unrecognized provider is not rendered at all (empty output) rather than
 * guessed at, since we cannot validate an arbitrary provider's `id` format or
 * construct a safe URL for it.
 *
 * Deliberately JS-free: the facade is a plain link to the provider, so
 * "click-to-load" here means the browser only ever talks to the third party
 * if the visitor actually clicks through — not even a thumbnail image is
 * fetched from the provider on page load, which a typical lite-embed facade
 * would do. A template-level enhancement (T17) could later swap this for a
 * richer inline facade; this handler doesn't assume one exists.
 */
final class EmbedHandler implements ShortcodeHandler
{
    private const PROVIDERS = [
        'youtube' => ['label' => 'YouTube', 'url' => 'https://www.youtube.com/watch?v=%s'],
        'vimeo'   => ['label' => 'Vimeo', 'url' => 'https://vimeo.com/%s'],
    ];

    public function name(): string
    {
        return 'embed';
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
        $provider = $attributes['provider'] ?? '';
        $id       = $attributes['id'] ?? '';

        if (!isset(self::PROVIDERS[$provider]) || !preg_match('/^[\w-]+$/', $id)) {
            return '';
        }

        $config = self::PROVIDERS[$provider];
        $url    = htmlspecialchars(sprintf($config['url'], $id), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $label  = htmlspecialchars($config['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<p class="embed-facade" data-embed-provider="' . htmlspecialchars($provider, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\">▶ Watch on {$label}</a>"
            . '</p>';
    }
}
