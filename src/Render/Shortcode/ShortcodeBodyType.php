<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode;

/**
 * How a paired shortcode's body ([name]...[/name]) is prepared before being
 * handed to the handler (SPEC §4.5). Self-closing shortcodes ([name attr])
 * have no body at all — None.
 */
enum ShortcodeBodyType
{
    case None;
    case Raw;
    case Text;
    case Markdown;
}
