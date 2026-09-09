<?php

declare(strict_types=1);

namespace Cuniform\Content\FrontMatter;

final class YamlParseResult
{
    /**
     * @param array<string, mixed> $data
     * @param list<string>         $errors
     */
    public function __construct(
        public readonly array $data,
        public readonly array $errors,
    ) {
    }
}
