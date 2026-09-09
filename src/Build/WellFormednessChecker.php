<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * "Any output fails a well-formedness parse" (SPEC §10.3).
 *
 * HTML pages are *not* checked with DOMDocument's HTML parser directly —
 * that parser is HTML4-era and silently auto-repairs almost anything short
 * of a null byte, including genuinely broken tag nesting (a mismatched
 * `</figcaption>` from a shortcode bug, say), so it would essentially never
 * fire and this check would be decorative. Instead, void elements (`<img>`,
 * `<meta>`, `<link>`, …) are self-closed and the result is parsed as XML —
 * strict about tag balance, which is exactly what a "well-formedness"
 * check needs — since this codebase's own templates already produce
 * self-contained markup with no other reason to be invalid XML once void
 * elements are self-closed and bare boolean attributes (`<video controls>`
 * — valid HTML5, invalid XML, and something this codebase's own
 * VideoHandler genuinely emits) are expanded to `controls="controls"`.
 * XML artifacts (feeds, sitemap) are already real XML and need no such
 * preprocessing.
 */
final class WellFormednessChecker
{
    private const VOID_ELEMENTS = 'area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr';

    /**
     * @param  list<GeneratedFile> $pages
     * @param  list<ArtifactFile>  $xmlArtifacts Only files worth XML-parsing,
     *                                            e.g. feeds and the sitemap —
     *                                            the caller decides which.
     * @return list<string> Errors — empty means everything parsed.
     */
    public function check(array $pages, array $xmlArtifacts): array
    {
        $errors = [];

        foreach ($pages as $page) {
            $normalized = $this->normalizeBareAttributes($this->selfCloseVoidElements($page->html));
            $error      = $this->firstXmlError($normalized);
            if ($error !== null) {
                $errors[] = "{$page->routePath}: {$error}";
            }
        }

        foreach ($xmlArtifacts as $artifact) {
            $error = $this->firstXmlError($artifact->contents);
            if ($error !== null) {
                $errors[] = "{$artifact->relativePath}: {$error}";
            }
        }

        return $errors;
    }

    private function selfCloseVoidElements(string $html): string
    {
        return preg_replace('/<(' . self::VOID_ELEMENTS . ')([^>]*?)\s*\/?>/i', '<$1$2/>', $html) ?? $html;
    }

    /**
     * `<video controls>` -> `<video controls="controls">` — a bare boolean
     * attribute is valid HTML5 and invalid XML; only ever appears inside an
     * opening tag's attribute list, never touching tag names themselves.
     */
    private function normalizeBareAttributes(string $html): string
    {
        return preg_replace_callback(
            '/<([a-zA-Z][a-zA-Z0-9]*)((?:\s+[^<>]*)?)(\s*\/?)>/',
            function (array $tagMatch): string {
                $attributes = preg_replace_callback(
                    '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(\s*=\s*(?:"[^"]*"|\'[^\']*\'))?/',
                    static fn (array $attrMatch): string => ($attrMatch[2] ?? '') !== ''
                        ? $attrMatch[0]
                        : "{$attrMatch[1]}=\"{$attrMatch[1]}\"",
                    $tagMatch[2]
                ) ?? $tagMatch[2];

                return "<{$tagMatch[1]}{$attributes}{$tagMatch[3]}>";
            },
            $html
        ) ?? $html;
    }

    private function firstXmlError(string $xml): ?string
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $ok  = $dom->loadXML($xml);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($ok && $errors === []) {
            return null;
        }

        $first = $errors[0] ?? null;

        return $first === null
            ? 'failed to parse as XML'
            : trim(sprintf('line %d: %s', $first->line, trim($first->message)));
    }
}
