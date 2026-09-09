<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * Writes a front matter block `Cuniform\Content\FrontMatter\RestrictedYamlParser`
 * (T4) can read back correctly — the inverse of that class, and the first
 * thing in this codebase that needs to *write* front matter rather than
 * only parse it. Every scalar is double-quoted, unconditionally, even one
 * that looks numeric (a slug or source_id that happens to be all digits):
 * an unquoted numeric-looking scalar would round-trip as an int, and
 * every field this emits is schema-typed as a string.
 *
 * The escaping (`\` before `\\` and `"`) was verified against the actual
 * parser, not derived from documentation alone — see WxrImporterTest,
 * which round-trips values containing both quotes and backslashes back
 * through `RestrictedYamlParser` and asserts they come back unchanged.
 */
final class FrontMatterEmitter
{
    /**
     * @param array<string, string|bool|list<string>|null> $fields Insertion
     *        order is output order; a null/empty value is omitted entirely
     *        rather than emitted as blank (every field this is used for is
     *        optional except title/slug/status/summary/date, which callers
     *        are expected to always provide).
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
