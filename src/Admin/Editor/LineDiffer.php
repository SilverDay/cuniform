<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

/**
 * A minimal line-level diff for SPEC §12's "on mismatch refuse the write
 * and show a diff — never auto-merge." No runtime dependency exists for
 * this (CLAUDE.md: no Composer at runtime), so this is a small classic
 * LCS-based diff — a document-sized text (this codebase's content is
 * capped at 2 MiB by FilesystemGateway, but a 2 MiB file of short lines can
 * still be tens of thousands of lines) rather than something meant for
 * arbitrarily large input.
 */
final class LineDiffer
{
    /**
     * Above this many lines on either side, the full LCS table (O(n*m)
     * cells) would be too large to build cheaply for a synchronous admin
     * request — the fallback below is a coarse "everything before was
     * removed, everything after was added" diff, which is always correct,
     * just not line-precise.
     */
    private const MAX_DIFF_LINES = 2000;

    /**
     * @return list<LineDiffEntry>
     */
    public function diff(string $before, string $after): array
    {
        $a = explode("\n", $before);
        $b = explode("\n", $after);

        if (count($a) > self::MAX_DIFF_LINES || count($b) > self::MAX_DIFF_LINES) {
            return [
                ...array_map(static fn (string $line): LineDiffEntry => new LineDiffEntry(LineDiffOp::Removed, $line), $a),
                ...array_map(static fn (string $line): LineDiffEntry => new LineDiffEntry(LineDiffOp::Added, $line), $b),
            ];
        }

        $lcsLengths = $this->lcsLengths($a, $b);

        $entries = [];
        $i       = 0;
        $j       = 0;
        $n       = count($a);
        $m       = count($b);

        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $entries[] = new LineDiffEntry(LineDiffOp::Same, $a[$i]);
                $i++;
                $j++;
            } elseif ($lcsLengths[$i + 1][$j] >= $lcsLengths[$i][$j + 1]) {
                $entries[] = new LineDiffEntry(LineDiffOp::Removed, $a[$i]);
                $i++;
            } else {
                $entries[] = new LineDiffEntry(LineDiffOp::Added, $b[$j]);
                $j++;
            }
        }

        while ($i < $n) {
            $entries[] = new LineDiffEntry(LineDiffOp::Removed, $a[$i]);
            $i++;
        }

        while ($j < $m) {
            $entries[] = new LineDiffEntry(LineDiffOp::Added, $b[$j]);
            $j++;
        }

        return $entries;
    }

    /**
     * @param  list<string>            $a
     * @param  list<string>            $b
     * @return array<int, array<int, int>>
     */
    private function lcsLengths(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        $table = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j]
                    ? $table[$i + 1][$j + 1] + 1
                    : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        return $table;
    }
}
