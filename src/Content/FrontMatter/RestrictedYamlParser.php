<?php

declare(strict_types=1);

namespace Cuniform\Content\FrontMatter;

/**
 * Hand-written parser for the restricted YAML subset front matter is allowed
 * to use (SPEC §5.2): scalars, quoted strings, flat sequences, ISO-8601 dates
 * (left as plain strings here — a specific key's date semantics are the
 * schema layer's job, not this one's). No anchors, aliases, custom tags, or
 * merge keys, no nested mappings — only a flat key → scalar-or-list shape.
 *
 * Errors are collected across the whole block rather than raised on the
 * first bad line, matching the project's general "report everything at once"
 * convention.
 */
final class RestrictedYamlParser
{
    public function parse(string $yaml): YamlParseResult
    {
        $lines   = explode("\n", $yaml);
        $data    = [];
        $errors  = [];
        $count   = count($lines);
        $i       = 0;

        while ($i < $count) {
            $line = $lines[$i];

            if (trim($line) === '') {
                $i++;

                continue;
            }

            if ($line !== ltrim($line) && preg_match('/^\s*[A-Za-z_][A-Za-z0-9_]*\s*:/', $line)) {
                $errors[] = sprintf(
                    'line %d: unexpected indentation — nested mappings are not supported in front matter',
                    $i + 1
                );
                $i++;

                continue;
            }

            if (str_starts_with(trim($line), '<<')) {
                $errors[] = sprintf('line %d: YAML merge keys are not supported', $i + 1);
                $i++;

                continue;
            }

            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*):\s*(.*)$/', $line, $m)) {
                $errors[] = sprintf('line %d: cannot parse line: %s', $i + 1, $line);
                $i++;

                continue;
            }

            $key  = $m[1];
            $rest = trim($m[2]);

            if ($rest === '') {
                [$items, $itemErrors, $i] = $this->consumeBlockSequence($lines, $i + 1);
                $errors                   = [...$errors, ...$itemErrors];
                $data[$key]               = $items;

                continue;
            }

            if (str_starts_with($rest, '[') && str_ends_with($rest, ']')) {
                $inner = substr($rest, 1, -1);
                $items = [];
                foreach ($this->splitFlowSequence($inner) as $raw) {
                    [$value, $error] = $this->decodeScalar($raw);
                    if ($error !== null) {
                        $errors[] = sprintf('line %d: %s', $i + 1, $error);

                        continue;
                    }

                    $items[] = $value;
                }
                $data[$key] = $items;
                $i++;

                continue;
            }

            [$value, $error] = $this->decodeScalar($rest);
            if ($error !== null) {
                $errors[] = sprintf('line %d: %s', $i + 1, $error);
                $i++;

                continue;
            }

            $data[$key] = $value;
            $i++;
        }

        return new YamlParseResult($data, $errors);
    }

    /**
     * @param  list<string> $lines
     * @return array{0: list<mixed>, 1: list<string>, 2: int}
     */
    private function consumeBlockSequence(array $lines, int $start): array
    {
        $items  = [];
        $errors = [];
        $count  = count($lines);
        $j      = $start;

        while ($j < $count) {
            $line = $lines[$j];

            if (trim($line) === '') {
                $j++;

                continue;
            }

            if (!preg_match('/^\s*-\s?(.*)$/', $line, $m)) {
                break;
            }

            [$value, $error] = $this->decodeScalar($m[1]);
            if ($error !== null) {
                $errors[] = sprintf('line %d: %s', $j + 1, $error);
            } else {
                $items[] = $value;
            }

            $j++;
        }

        return [$items, $errors, $j];
    }

    /**
     * @return list<string>
     */
    private function splitFlowSequence(string $inner): array
    {
        $items      = [];
        $current    = '';
        $quoteChar  = null;

        for ($i = 0, $len = strlen($inner); $i < $len; $i++) {
            $char = $inner[$i];

            if ($quoteChar !== null) {
                $current .= $char;
                if ($char === $quoteChar) {
                    $quoteChar = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quoteChar = $char;
                $current .= $char;

                continue;
            }

            if ($char === ',') {
                $items[]  = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $items[] = trim($current);
        }

        return $items;
    }

    /**
     * @return array{0: mixed, 1: ?string}
     */
    private function decodeScalar(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return ['', null];
        }

        if (str_starts_with($raw, '&')) {
            return [null, 'YAML anchors are not supported'];
        }

        if (str_starts_with($raw, '*')) {
            return [null, 'YAML alias references are not supported'];
        }

        if (str_starts_with($raw, '!')) {
            return [null, 'YAML custom tags are not supported'];
        }

        if (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"')) {
            $inner = substr($raw, 1, -1);

            return [str_replace(['\\"', '\\\\'], ['"', '\\'], $inner), null];
        }

        if (strlen($raw) >= 2 && $raw[0] === "'" && str_ends_with($raw, "'")) {
            $inner = substr($raw, 1, -1);

            return [str_replace("''", "'", $inner), null];
        }

        if ($raw === 'true') {
            return [true, null];
        }

        if ($raw === 'false') {
            return [false, null];
        }

        if (preg_match('/^-?\d+$/', $raw)) {
            return [(int) $raw, null];
        }

        if (preg_match('/^-?\d+\.\d+$/', $raw)) {
            return [(float) $raw, null];
        }

        return [$raw, null];
    }
}
