<?php

declare(strict_types=1);

namespace Cuniform\Content\FrontMatter;

/**
 * Writes a front matter block `RestrictedYamlParser` (T4) can read back
 * correctly — the inverse of that class. Originally built for the WXR
 * importer (T37) and moved here from `Cuniform\Import` for T29: the admin
 * editor needs to write front matter too, and that's a general "how does
 * this codebase serialize front matter" concern, not an import-specific
 * one — `Cuniform\Admin\Editor` depending on `Cuniform\Import` for it would
 * have been the wrong coupling. Every scalar is double-quoted,
 * unconditionally, even one that looks numeric (a slug or source_id that
 * happens to be all digits): an unquoted numeric-looking scalar would
 * round-trip as an int, and every field this emits is schema-typed as a
 * string.
 *
 * The escaping (`\` before `\\` and `"`) was verified against the actual
 * parser, not derived from documentation alone — see FrontMatterEmitterTest,
 * which round-trips values containing both quotes and backslashes back
 * through `RestrictedYamlParser` and asserts they come back unchanged.
 *
 * int/float support was added for T29 (the admin editor emits `nav_order`
 * and `sitemap_priority`, SPEC §6.2): `RestrictedYamlParser::decodeScalar()`
 * only produces a PHP int/float for an *unquoted* numeric scalar — quoting
 * "5" would round-trip as the string "5", and `FrontMatterParser::optionalInt()`
 * requires `is_int()`, so it would then reject it with "must be an integer".
 * Nothing before T29 emitted either field (WxrImporter, T37, is posts-only).
 */
final class FrontMatterEmitter
{
    /**
     * @param array<string, string|int|float|bool|list<string>|null> $fields
     *        Insertion order is output order; a null/empty value is omitted
     *        entirely rather than emitted as blank (every field this is used
     *        for is optional except title/slug/status/summary/date, which
     *        callers are expected to always provide).
     */
    public function emit(array $fields, string $body): string
    {
        $lines = ['---'];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (is_array($value)) {
                $lines[] = "{$key}:";
                foreach ($value as $item) {
                    $lines[] = '  - ' . $this->quote($item);
                }

                continue;
            }

            if (is_bool($value)) {
                $lines[] = "{$key}: " . ($value ? 'true' : 'false');

                continue;
            }

            if (is_int($value) || is_float($value)) {
                $lines[] = "{$key}: " . $value;

                continue;
            }

            $lines[] = "{$key}: " . $this->quote($value);
        }

        $lines[] = '---';

        return implode("\n", $lines) . "\n" . $body;
    }

    private function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
