<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * Maps WordPress's own bracket shortcodes to Cuniform's (SPEC §A.3:
 * "map WP shortcodes ([caption]→[figure], [gallery]→ repeated [figure],
 * [embed]→[embed]); unknown shortcodes preserved verbatim and reported,
 * never silently dropped"). Runs as a text-level pass over the raw HTML,
 * before DOM parsing (HtmlToMarkdownConverter) — `[caption]`'s body mixes
 * WP's own bracket syntax with a real `<img>` tag as plain text
 * (`[caption]<img src="...">A caption[/caption]`), which is far simpler
 * to pull apart with one targeted regex than to reconstruct from DOM
 * sibling nodes after the fact.
 *
 * A shortcode this class doesn't recognize at all is left completely
 * untouched: Cuniform's own `ShortcodeProcessor` already treats an
 * unregistered `[name ...]` as inert literal text (`extractShortcodes()`
 * — matched but no handler, so it's copied through unchanged), so
 * "preserved verbatim" here means exactly that — no placeholder, no
 * wrapper, the original bracket text survives all the way to the built
 * page, visible rather than silently gone.
 */
final class WpShortcodeConverter
{
    /**
     * WordPress's own shortcode names this pass maps (`caption`,
     * `gallery`) or maps onto a same-named Cuniform shortcode (`embed`)
     * — excluded from the generic "unknown shortcode" report because a
     * resolution failure for any of them already produces its own, more
     * specific warning.
     */
    private const HANDLED_WP_NAMES = ['caption', 'gallery', 'embed'];

    /**
     * Cuniform's own shortcode vocabulary (SPEC §4.5) — also excluded
     * from the generic report, for a different reason: by the time
     * `reportUnknownShortcodes()` runs, `[figure ...]`/`[embed ...]` in
     * the text is just as likely to be *this class's own output*
     * (`convertCaptions()`/`convertGalleries()` both emit `[figure]`) as
     * it is WordPress content that happened to use one of these names —
     * either way it's a real, recognized shortcode, not an unknown one.
     */
    private const CUNIFORM_SHORTCODE_NAMES = ['figure', 'video', 'embed', 'details', 'note', 'toc', 'include'];

    /**
     * @param array<int, WxrItem> $attachmentsById wp:post_id => attachment
     *                                              item, for resolving
     *                                              [gallery ids="..."].
     */
    public function __construct(private readonly array $attachmentsById = [])
    {
    }

    /**
     * @return array{html: string, warnings: list<string>}
     */
    public function convert(string $html): array
    {
        $warnings = [];
        $html     = $this->convertCaptions($html, $warnings);
        $html     = $this->convertGalleries($html, $warnings);
        $html     = $this->convertEmbeds($html, $warnings);
        $html     = $this->reportUnknownShortcodes($html, $warnings);

        return ['html' => $html, 'warnings' => $warnings];
    }

    /**
     * @param list<string> $warnings
     */
    private function convertCaptions(string $html, array &$warnings): string
    {
        $result = preg_replace_callback(
            '/\[caption\b([^\]]*)\](.*?)\[\/caption\]/is',
            function (array $matches) use (&$warnings): string {
                $shortcodeAttrs = $this->parseHtmlAttributes($matches[1]);
                $body           = $matches[2];

                if (preg_match('/<img\b([^>]*)>/i', $body, $imgMatch) !== 1) {
                    $warnings[] = 'unresolvable [caption] (no <img> in its body), preserved verbatim: '
                        . $this->snippet($matches[0]);

                    return $matches[0];
                }

                $imgAttrs = $this->parseHtmlAttributes($imgMatch[1]);
                $src      = $imgAttrs['src'] ?? '';
                if ($src === '') {
                    $warnings[] = 'unresolvable [caption] (<img> has no src), preserved verbatim: '
                        . $this->snippet($matches[0]);

                    return $matches[0];
                }

                $caption = trim(strip_tags(str_replace($imgMatch[0], '', $body)));

                return $this->buildFigureShortcode(
                    $src,
                    $imgAttrs['alt'] ?? '',
                    $caption,
                    $imgAttrs['width'] ?? ($shortcodeAttrs['width'] ?? ''),
                    $imgAttrs['height'] ?? '',
                );
            },
            $html
        );

        return $result ?? $html;
    }

    /**
     * @param list<string> $warnings
     */
    private function convertGalleries(string $html, array &$warnings): string
    {
        $result = preg_replace_callback(
            '/\[gallery\b([^\]]*)\]/i',
            function (array $matches) use (&$warnings): string {
                $attrs = $this->parseHtmlAttributes($matches[1]);
                $ids   = array_values(array_filter(
                    array_map('trim', explode(',', $attrs['ids'] ?? '')),
                    static fn (string $id): bool => $id !== ''
                ));

                if ($ids === []) {
                    $warnings[] = 'unresolvable [gallery] (no ids attribute), preserved verbatim: '
                        . $this->snippet($matches[0]);

                    return $matches[0];
                }

                $figures = [];
                foreach ($ids as $id) {
                    $attachment = $this->attachmentsById[(int) $id] ?? null;
                    if ($attachment === null) {
                        $warnings[] = "[gallery] image id={$id} not found among the export's attachments";

                        continue;
                    }

                    $figures[] = $this->figureForAttachment($attachment);
                }

                if ($figures === []) {
                    $warnings[] = 'unresolvable [gallery] (none of its ids matched an attachment), preserved verbatim: '
                        . $this->snippet($matches[0]);

                    return $matches[0];
                }

                return implode("\n\n", $figures);
            },
            $html
        );

        return $result ?? $html;
    }

    /**
     * @param list<string> $warnings
     */
    private function convertEmbeds(string $html, array &$warnings): string
    {
        $result = preg_replace_callback(
            '/\[embed\b[^\]]*\](.*?)\[\/embed\]/is',
            function (array $matches) use (&$warnings): string {
                $url    = trim(strip_tags($matches[1]));
                $mapped = $this->mapEmbedUrl($url);

                if ($mapped === null) {
                    $warnings[] = "unresolvable [embed] (provider not recognized — only youtube/vimeo map to "
                        . "Cuniform's [embed], SPEC §4.5), preserved verbatim: {$url}";

                    return $matches[0];
                }

                [$provider, $id] = $mapped;

                return "[embed provider=\"{$provider}\" id=\"{$id}\"]";
            },
            $html
        );

        return $result ?? $html;
    }

    /**
     * @param list<string> $warnings
     */
    private function reportUnknownShortcodes(string $html, array &$warnings): string
    {
        if (preg_match_all('/\[(\/?)(\w+)(?:\s[^\]]*)?\]/', $html, $matches, \PREG_SET_ORDER) === 0) {
            return $html;
        }

        $alreadyReported = [];
        foreach ($matches as $match) {
            $isClosingTag = $match[1] === '/';
            $name         = $match[2];

            if (
                $isClosingTag
                || in_array($name, self::HANDLED_WP_NAMES, true)
                || in_array($name, self::CUNIFORM_SHORTCODE_NAMES, true)
                || isset($alreadyReported[$name])
            ) {
                continue;
            }

            $warnings[]             = "unknown shortcode [{$name}] preserved verbatim — needs manual review (SPEC §A.3)";
            $alreadyReported[$name] = true;
        }

        return $html;
    }

    private function figureForAttachment(WxrItem $attachment): string
    {
        $alt = $attachment->postmeta['_wp_attachment_image_alt'][0] ?? $attachment->title;

        return $this->buildFigureShortcode($attachment->attachmentUrl ?? '', $alt, '', '', '');
    }

    /**
     * @return array{0: string, 1: string}|null [provider, id], or null when
     *                                           the URL doesn't match a
     *                                           provider Cuniform's own
     *                                           [embed] handler recognizes
     *                                           (SPEC §4.5 — youtube, vimeo).
     */
    private function mapEmbedUrl(string $url): ?array
    {
        if (preg_match('#youtube\.com/watch\?v=([\w-]+)#i', $url, $m) === 1) {
            return ['youtube', $m[1]];
        }

        if (preg_match('#youtu\.be/([\w-]+)#i', $url, $m) === 1) {
            return ['youtube', $m[1]];
        }

        if (preg_match('#vimeo\.com/(\d+)#i', $url, $m) === 1) {
            return ['vimeo', $m[1]];
        }

        return null;
    }

    private function buildFigureShortcode(string $src, string $alt, string $caption, string $width, string $height): string
    {
        $attrs   = [];
        $attrs[] = 'src="' . $this->sanitizeForAttribute($src) . '"';
        $attrs[] = 'alt="' . $this->sanitizeForAttribute($alt) . '"';

        if ($caption !== '') {
            $attrs[] = 'caption="' . $this->sanitizeForAttribute($caption) . '"';
        }

        if (preg_match('/^\d+$/', $width) === 1) {
            $attrs[] = "width=\"{$width}\"";
        }

        if (preg_match('/^\d+$/', $height) === 1) {
            $attrs[] = "height=\"{$height}\"";
        }

        return '[figure ' . implode(' ', $attrs) . ']';
    }

    /**
     * Cuniform's own shortcode attribute parser
     * (`ShortcodeProcessor::parseAttributes()`) has no escape mechanism —
     * `"[^"]*"` stops at the first `"` it finds, full stop. A caption or
     * alt text containing a literal double quote would otherwise
     * prematurely close the attribute and corrupt the shortcode; replacing
     * it with a single quote is a small, readable loss rather than a
     * broken shortcode.
     */
    private function sanitizeForAttribute(string $value): string
    {
        return str_replace('"', "'", $value);
    }

    /**
     * @return array<string, string>
     */
    private function parseHtmlAttributes(string $text): array
    {
        $attributes = [];
        preg_match_all('/([\w-]+)\s*=\s*"([^"]*)"|([\w-]+)\s*=\s*\'([^\']*)\'/', $text, $matches, \PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (isset($match[1])) {
                $attributes[strtolower($match[1])] = $match[2] ?? '';
            } elseif (isset($match[3])) {
                $attributes[strtolower($match[3])] = $match[4] ?? '';
            }
        }

        return $attributes;
    }

    private function snippet(string $text): string
    {
        $flattened = trim((string) preg_replace('/\s+/', ' ', $text));

        return strlen($flattened) > 120 ? substr($flattened, 0, 117) . '...' : $flattened;
    }
}
