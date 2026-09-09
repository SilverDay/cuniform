<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * Converts a WXR item's `content:encoded` HTML into Markdown constrained
 * to what `Cuniform\Render\Md2Html` actually supports (SPEC §A.3: "HTML→
 * Markdown constrained to constructs the renderer supports, with anything
 * else becoming a shortcode or a review flag").
 *
 * Pipeline, matching §A.3's own ordering: strip Gutenberg block comments
 * → normalize wpautop-style content lacking explicit block markup → map
 * WordPress's own bracket shortcodes onto Cuniform's (`WpShortcodeConverter`,
 * a text-level pass — it has to run before DOM parsing, since `[caption]`'s
 * body mixes bracket text with a real `<img>` tag) → parse what's left as
 * HTML and walk it, emitting Markdown.
 *
 * A tag Md2Html doesn't support is never emitted as raw HTML (SPEC §4.4;
 * T35's own acceptance criterion) — its own markup is dropped, its text
 * content (and any inline formatting inside it) is kept, and the drop is
 * recorded as a warning for the migration report (T38). `<script>`/
 * `<style>` are the one exception: their content isn't readable text, so
 * both the tag and its content are dropped entirely, also with a warning.
 *
 * Deliberately unescaped: bracket text (`[`/`]`) in plain content is left
 * alone, specifically because `WpShortcodeConverter`'s own output — the
 * Cuniform shortcodes it just generated — has to survive this same DOM
 * walk unchanged; escaping brackets here would corrupt them. This is a
 * known, accepted gap for the rare case of a post's prose *itself*
 * containing literal `[bracket text]` that happens to match a registered
 * Cuniform shortcode name — not present anywhere in the operator's real
 * export (BUILD-ORDER's T34/T35 notes), and Md2Html has no escape syntax
 * to safely neutralize it even if this class tried to. Markdown-special
 * characters (`*`, `_`, `#`, backtick, ...) inside plain text are left
 * as-is for the same reason — no escape mechanism exists downstream.
 */
final class HtmlToMarkdownConverter
{
    private const BLOCK_TAGS = [
        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li',
        'blockquote', 'pre', 'hr', 'div', 'section', 'article', 'figure', 'table',
        // script/style must dispatch through convertBlockNode() ->
        // handleUnsupportedBlock()'s drop-entirely branch, never through
        // the inline-buffer path — their "text content" is code, not
        // prose, and must never end up looking like part of the post.
        'script', 'style',
    ];

    private readonly GutenbergBlockStripper $gutenberg;
    private readonly WpautopNormalizer $wpautop;
    private readonly WpShortcodeConverter $shortcodes;

    /**
     * @param array<int, WxrItem> $attachmentsById wp:post_id => attachment
     *                                              item, for resolving
     *                                              [gallery ids="..."]
     *                                              (SPEC §A.3).
     */
    public function __construct(array $attachmentsById = [])
    {
        $this->gutenberg  = new GutenbergBlockStripper();
        $this->wpautop    = new WpautopNormalizer();
        $this->shortcodes = new WpShortcodeConverter($attachmentsById);
    }

    public function convert(string $html): ConversionResult
    {
        $html = $this->gutenberg->strip($html);
        $html = $this->wpautop->normalize($html);

        $shortcodeResult = $this->shortcodes->convert($html);
        $html            = $shortcodeResult['html'];
        $warnings        = $shortcodeResult['warnings'];

        if (trim($html) === '') {
            return new ConversionResult('', $warnings);
        }

        $root     = $this->parseFragment($html);
        $markdown = trim($this->convertBlockChildren($root, $warnings));

        return new ConversionResult($markdown === '' ? '' : $markdown . "\n", $warnings);
    }

    private function parseFragment(string $html): \DOMElement
    {
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
            . '<body><div id="cuniform-import-root">' . $html . '</div></body></html>';

        $dom              = new \DOMDocument();
        $previousSetting  = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom->loadHTML($wrapped, \LIBXML_NOERROR | \LIBXML_NOWARNING);
        libxml_use_internal_errors($previousSetting);

        $root = $dom->getElementById('cuniform-import-root');
        if (!$root instanceof \DOMElement) {
            throw ImportException::malformedXml('could not parse item content as HTML');
        }

        return $root;
    }

    // -----------------------------------------------------------------------
    // Block level
    // -----------------------------------------------------------------------

    /**
     * A block-level container's children are not necessarily block-level
     * themselves — real WordPress markup has plenty of `<div>`s and
     * `<span>`s wrapping bare text or inline formatting with no `<p>` in
     * sight, the DOM equivalent of the bare wpautop-style text
     * `WpautopNormalizer` already handles at the string level. Text nodes
     * and inline elements sitting directly among block siblings are
     * buffered and flushed as their own implicit paragraph, exactly the
     * way a lone `<span>text</span>` next to a `<p>` should read.
     *
     * @param list<string> $warnings
     */
    private function convertBlockChildren(\DOMNode $parent, array &$warnings): string
    {
        $output       = '';
        $inlineBuffer = '';

        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && in_array(strtolower($child->tagName), self::BLOCK_TAGS, true)) {
                $output .= $this->flushInlineBuffer($inlineBuffer);
                $output .= $this->convertBlockNode($child, $warnings);

                continue;
            }

            $inlineBuffer .= $this->convertInlineNode($child, $warnings);
        }

        $output .= $this->flushInlineBuffer($inlineBuffer);

        return $output;
    }

    private function flushInlineBuffer(string &$buffer): string
    {
        $text   = trim($buffer);
        $buffer = '';

        return $text === '' ? '' : $text . "\n\n";
    }

    /**
     * @param list<string> $warnings
     */
    private function convertBlockNode(\DOMElement $node, array &$warnings): string
    {
        $tag = strtolower($node->tagName);

        if (preg_match('/^h([1-6])$/', $tag, $m) === 1) {
            return str_repeat('#', (int) $m[1]) . ' ' . trim($this->convertInlineChildren($node, $warnings)) . "\n\n";
        }

        return match ($tag) {
            'p' => trim($this->convertInlineChildren($node, $warnings)) . "\n\n",
            'ul' => $this->convertList($node, $warnings, 'ul', 0) . "\n",
            'ol' => $this->convertList($node, $warnings, 'ol', 0) . "\n",
            'blockquote' => $this->convertBlockquote($node, $warnings) . "\n",
            'pre' => $this->convertPre($node) . "\n",
            'hr' => "---\n\n",
            'figure', 'div', 'section', 'article' => $this->convertBlockChildren($node, $warnings),
            default => $this->handleUnsupportedBlock($node, $warnings),
        };
    }

    /**
     * @param list<string> $warnings
     */
    private function convertList(\DOMElement $node, array &$warnings, string $type, int $depth): string
    {
        $output = '';
        $index  = 1;
        $indent = str_repeat('    ', $depth);

        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement || strtolower($child->tagName) !== 'li') {
                continue;
            }

            $marker = $type === 'ol' ? ($index . '.') : '-';
            $index++;

            $inline = '';
            $nested = '';
            foreach ($child->childNodes as $liChild) {
                if ($liChild instanceof \DOMElement && in_array(strtolower($liChild->tagName), ['ul', 'ol'], true)) {
                    $nested .= $this->convertList($liChild, $warnings, strtolower($liChild->tagName), $depth + 1);

                    continue;
                }

                $inline .= $this->convertInlineNode($liChild, $warnings);
            }

            $output .= "{$indent}{$marker} " . trim($inline) . "\n";
            $output .= $nested;
        }

        return $output;
    }

    /**
     * @param list<string> $warnings
     */
    private function convertBlockquote(\DOMElement $node, array &$warnings): string
    {
        $inner = trim($this->convertBlockChildren($node, $warnings));
        $lines = explode("\n", $inner);
        $quoted = array_map(static fn (string $line): string => $line === '' ? '>' : "> {$line}", $lines);

        return implode("\n", $quoted) . "\n";
    }

    private function convertPre(\DOMElement $node): string
    {
        $codeNode = null;
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->tagName) === 'code') {
                $codeNode = $child;

                break;
            }
        }

        $code = ($codeNode ?? $node)->textContent;

        $language = '';
        if ($codeNode !== null && preg_match('/language-(\S+)/', $codeNode->getAttribute('class'), $m) === 1) {
            $language = $m[1];
        }

        return "```{$language}\n" . rtrim($code, "\n") . "\n```\n";
    }

    /**
     * @param list<string> $warnings
     */
    private function handleUnsupportedBlock(\DOMElement $node, array &$warnings): string
    {
        $tag = strtolower($node->tagName);

        if (in_array($tag, ['script', 'style'], true)) {
            $warnings[] = "dropped a <{$tag}> block entirely (not readable content) — needs manual review (SPEC §4.4)";

            return '';
        }

        $warnings[] = "unsupported <{$tag}> block — kept its text content, dropped the tag itself; "
            . 'needs manual review (SPEC §4.4)';

        // convertBlockChildren() already buffers any bare inline content
        // into its own implicit paragraph, so this needs no separate
        // "does it have a block child" check — it's correct either way.
        return $this->convertBlockChildren($node, $warnings);
    }

    // -----------------------------------------------------------------------
    // Inline level
    // -----------------------------------------------------------------------

    /**
     * @param list<string> $warnings
     */
    private function convertInlineChildren(\DOMNode $parent, array &$warnings): string
    {
        $output = '';
        foreach ($parent->childNodes as $child) {
            $output .= $this->convertInlineNode($child, $warnings);
        }

        return $output;
    }

    /**
     * @param list<string> $warnings
     */
    private function convertInlineNode(\DOMNode $node, array &$warnings): string
    {
        if ($node instanceof \DOMText) {
            return $node->textContent;
        }

        if (!$node instanceof \DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);

        return match ($tag) {
            'strong', 'b' => '**' . $this->convertInlineChildren($node, $warnings) . '**',
            'em', 'i' => '*' . $this->convertInlineChildren($node, $warnings) . '*',
            'del', 's', 'strike' => '~~' . $this->convertInlineChildren($node, $warnings) . '~~',
            'code' => '`' . $node->textContent . '`',
            'br' => "  \n",
            'a' => $this->convertLink($node, $warnings),
            'img' => $this->convertImage($node, $warnings),
            'span' => $this->convertInlineChildren($node, $warnings),
            default => $this->handleUnsupportedInline($node, $warnings),
        };
    }

    /**
     * @param list<string> $warnings
     */
    private function convertLink(\DOMElement $node, array &$warnings): string
    {
        $href = $node->getAttribute('href');
        $text = $this->convertInlineChildren($node, $warnings);

        return $href === '' ? $text : "[{$text}]({$href})";
    }

    /**
     * @param list<string> $warnings
     */
    private function convertImage(\DOMElement $node, array &$warnings): string
    {
        $src = $node->getAttribute('src');
        if ($src === '') {
            $warnings[] = 'dropped an <img> with no src attribute';

            return '';
        }

        return '![' . $node->getAttribute('alt') . '](' . $src . ')';
    }

    /**
     * @param list<string> $warnings
     */
    private function handleUnsupportedInline(\DOMElement $node, array &$warnings): string
    {
        $tag = strtolower($node->tagName);

        // Belt-and-suspenders alongside BLOCK_TAGS including script/style
        // (the normal way either ever reaches this class): a script/style
        // element nested somewhere DOMDocument still parsed as inline
        // content must never leak its "text" (code, not prose) either.
        if (in_array($tag, ['script', 'style'], true)) {
            $warnings[] = "dropped a <{$tag}> element entirely (not readable content) — needs manual review (SPEC §4.4)";

            return '';
        }

        $warnings[] = "unsupported inline <{$tag}> — kept its text content, dropped the tag itself; "
            . 'needs manual review (SPEC §4.4)';

        return $this->convertInlineChildren($node, $warnings);
    }
}
