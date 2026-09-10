<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

final class LineDiffEntry
{
    public function __construct(
        public readonly LineDiffOp $op,
        public readonly string $line,
    ) {
    }
}
