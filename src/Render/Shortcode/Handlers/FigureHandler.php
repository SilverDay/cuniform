<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode\Handlers;

use Cuniform\Render\Shortcode\ShortcodeBodyType;
use Cuniform\Render\Shortcode\ShortcodeHandler;

/**
 * `[figure src alt caption width height]` → <figure><img …><figcaption>…</figcaption></figure> (SPEC §4.5).
 */
final class FigureHandler implements ShortcodeHandler
{
    public function name(): string
    {
        return 'figure';
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
        // Empty alt is valid (a decorative image) — see class docblock; not
        // requiring it here mirrors how §5.5 only enforces image_alt at the
        // front-matter level, not on every image reference.
        $alt = htmlspecialchars($attributes['alt'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $imgAttrs = "src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\" decoding=\"async\"";

        foreach (['width', 'height'] as $dimension) {
            $value = $attributes[$dimension] ?? '';
            if (preg_match('/^\d+$/', $value)) {
                $imgAttrs .= " {$dimension}=\"{$value}\"";
            }
        }

        $figcaption = '';
        if (($attributes['caption'] ?? '') !== '') {
            $caption    = htmlspecialchars($attributes['caption'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $figcaption = "<figcaption>{$caption}</figcaption>";
        }

        return "<figure><img {$imgAttrs}>{$figcaption}</figure>";
    }
}
