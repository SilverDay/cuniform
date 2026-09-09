<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode;

/**
 * Contract for a shortcode handler (SPEC §4.5). Implemented by the concrete
 * handlers T9 adds (figure, video, embed, details, note, toc, include).
 *
 * The handler is a trust boundary: it must escape every attribute value
 * itself (htmlspecialchars for text, a scheme allow-list for URLs) — the
 * processor passes attributes through unescaped, exactly as written.
 */
interface ShortcodeHandler
{
    /**
     * The shortcode name this handler answers to, e.g. 'figure'. Matched
     * against `[name ...]`.
     */
    public function name(): string;

    /**
     * How a paired shortcode's body is prepared before render() sees it.
     * Meaningless (and never consulted) for a self-closing shortcode.
     */
    public function bodyType(): ShortcodeBodyType;

    /**
     * Whether this shortcode is a block-level element (SPEC §4.5): the
     * processor isolates its placeholder token onto its own line so the
     * renderer emits a standalone <p> the post-pass can unwrap. An
     * inline-level shortcode's token is substituted in place instead.
     */
    public function isBlockLevel(): bool;

    /**
     * @param array<string, string> $attributes Raw, unescaped — see class docblock.
     * @param string|null           $body       Prepared per bodyType(); null for
     *                                           a self-closing shortcode or one
     *                                           whose bodyType() is None.
     */
    public function render(array $attributes, ?string $body): string;
}
