<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode;

use Cuniform\Render\Md2Html;
use Cuniform\Render\RenderException;

/**
 * The shortcode pre/post-pass around the renderer (SPEC §4.5, C3):
 *
 *  1. Mask code — fenced blocks and inline spans get their `[`/`]` replaced
 *     with opaque markers so shortcode syntax inside an example is never
 *     interpreted (the fence/backtick delimiters themselves are untouched,
 *     so the renderer still sees ordinary code).
 *  2. Match `[name attr="value"]` / paired `[name]...[/name]`, resolve each
 *     against the handler registry, and prepare its body (§4.5's raw/text/
 *     markdown declaration) — a block-level match gets an isolated
 *     placeholder token so the renderer emits a standalone <p>; an inline
 *     one is substituted in place. The handler itself is not called yet.
 *  3. Convert the token-substituted, code-still-masked markdown through the
 *     renderer.
 *  4. Only now call each handler's render() — after the renderer has run,
 *     so a handler that needs the current document's own state (the [toc]
 *     handler needs its heading list, only known once conversion has
 *     happened) can actually see it. Substitute each result, unwrapping the
 *     <p> around a block-level one, then restore the masked code markers.
 *
 * A `markdown`-body shortcode recurses through this same convert() while
 * *preparing* its body (step 2) — that is what "recurse through C4" means
 * in practice, and it also means a markdown-type body can itself contain
 * further shortcodes. That recursion is unrelated to, and unaffected by,
 * step 4's deferred rendering: each recursive convert() call is a
 * self-contained pipeline of its own.
 */
final class ShortcodeProcessor
{
    private const MASK_OPEN  = '%%CFMASKLBRACKET%%';
    private const MASK_CLOSE = '%%CFMASKRBRACKET%%';

    private const SHORTCODE_PATTERN = '/\[(\w+)((?:\s+[\w-]+=(?:"[^"]*"|\S+))*)\s*\]/';

    private int $tokenCounter = 0;

    public function __construct(
        private readonly Md2Html $renderer,
        private readonly ShortcodeHandlerRegistry $handlers,
    ) {
    }

    public function convert(string $markdown): string
    {
        $masked = $this->maskCode($markdown);

        [$withTokens, $pending] = $this->extractShortcodes($masked);

        $html = $this->renderer->convert($withTokens);

        foreach ($pending as $token => [$handler, $attributes, $body, $isBlockLevel]) {
            $generatedHtml = $handler->render($attributes, $body);
            $html          = $isBlockLevel
                ? $this->unwrapBlockToken($html, $token, $generatedHtml)
                : str_replace($token, $generatedHtml, $html);
        }

        return $this->unmaskCode($html);
    }

    // -----------------------------------------------------------------------
    // Code masking
    // -----------------------------------------------------------------------

    private function maskCode(string $markdown): string
    {
        $lines     = explode("\n", $markdown);
        $inFence   = false;
        $fenceChar = '';
        $fenceLen  = 0;

        foreach ($lines as $i => $line) {
            if (!$inFence && preg_match('/^(`{3,}|~{3,})\s*(\S*)\s*$/', $line, $m)) {
                $inFence   = true;
                $fenceChar = $m[1][0];
                $fenceLen  = strlen($m[1]);

                continue;
            }

            if ($inFence) {
                if (preg_match('/^' . preg_quote($fenceChar, '/') . '{' . $fenceLen . ',}\s*$/', $line)) {
                    $inFence = false;

                    continue;
                }

                $lines[$i] = $this->maskBrackets($line);

                continue;
            }

            $lines[$i] = $this->maskInlineCodeSpans($line);
        }

        return implode("\n", $lines);
    }

    private function maskInlineCodeSpans(string $line): string
    {
        return preg_replace_callback(
            '/(`+)(.+?)\1/',
            fn (array $m): string => $m[1] . $this->maskBrackets($m[2]) . $m[1],
            $line
        ) ?? $line;
    }

    private function maskBrackets(string $text): string
    {
        return str_replace(['[', ']'], [self::MASK_OPEN, self::MASK_CLOSE], $text);
    }

    private function unmaskCode(string $html): string
    {
        return str_replace([self::MASK_OPEN, self::MASK_CLOSE], ['[', ']'], $html);
    }

    // -----------------------------------------------------------------------
    // Shortcode extraction
    // -----------------------------------------------------------------------

    /**
     * @return array{0: string, 1: array<string, array{0: ShortcodeHandler, 1: array<string, string>, 2: ?string, 3: bool}>}
     */
    private function extractShortcodes(string $markdown): array
    {
        $pending = [];
        $result  = '';
        $pos     = 0;

        while (preg_match(self::SHORTCODE_PATTERN, $markdown, $m, PREG_OFFSET_CAPTURE, $pos)) {
            $fullMatch = $m[0][0];
            $matchPos  = $m[0][1];
            $name      = $m[1][0];
            $attrText  = $m[2][0];

            $result .= substr($markdown, $pos, $matchPos - $pos);

            if (!$this->handlers->has($name)) {
                $result .= $fullMatch;
                $pos     = $matchPos + strlen($fullMatch);

                continue;
            }

            $handler     = $this->handlers->get($name);
            $attributes  = $this->parseAttributes($attrText);
            $afterOpenAt = $matchPos + strlen($fullMatch);
            $consumedTo  = $afterOpenAt;
            $body        = null;

            if ($handler->bodyType() !== ShortcodeBodyType::None) {
                $closeTag = "[/{$name}]";
                $closeAt  = strpos($markdown, $closeTag, $afterOpenAt);
                if ($closeAt === false) {
                    throw RenderException::unclosedShortcode($name);
                }

                $rawBody    = substr($markdown, $afterOpenAt, $closeAt - $afterOpenAt);
                $body       = $this->prepareBody($rawBody, $handler->bodyType());
                $consumedTo = $closeAt + strlen($closeTag);
            }

            $token           = $this->nextToken();
            $pending[$token] = [$handler, $attributes, $body, $handler->isBlockLevel()];

            $result .= $handler->isBlockLevel() ? "\n\n{$token}\n\n" : $token;
            $pos     = $consumedTo;
        }

        $result .= substr($markdown, $pos);

        return [$result, $pending];
    }

    private function prepareBody(string $rawBody, ShortcodeBodyType $bodyType): string
    {
        return match ($bodyType) {
            ShortcodeBodyType::Raw => $rawBody,
            ShortcodeBodyType::Text => htmlspecialchars($rawBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ShortcodeBodyType::Markdown => $this->convert($rawBody),
            ShortcodeBodyType::None => '',
        };
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $attrText): array
    {
        $attributes = [];
        preg_match_all('/([\w-]+)=("[^"]*"|\S+)/', $attrText, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $raw = $m[2];
            $attributes[$m[1]] = (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"'))
                ? substr($raw, 1, -1)
                : $raw;
        }

        return $attributes;
    }

    private function nextToken(): string
    {
        $this->tokenCounter++;

        return sprintf('%%%%CFSC%04d%%%%', $this->tokenCounter);
    }

    // -----------------------------------------------------------------------
    // Post-pass substitution
    // -----------------------------------------------------------------------

    private function unwrapBlockToken(string $html, string $token, string $generatedHtml): string
    {
        $pattern = '/<p>\s*' . preg_quote($token, '/') . '\s*<\/p>\n?/';
        $replacement = $generatedHtml . "\n";

        $unwrapped = preg_replace($pattern, $replacement, $html);
        if ($unwrapped !== null && $unwrapped !== $html) {
            return $unwrapped;
        }

        // The token wasn't isolated in its own paragraph after all — substitute
        // it in place rather than leaving it visible in the output.
        return str_replace($token, $generatedHtml, $html);
    }
}
