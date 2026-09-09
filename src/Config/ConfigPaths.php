<?php

declare(strict_types=1);

namespace Cuniform\Config;

final class ConfigPaths
{
    public function __construct(
        public readonly string $content,
        public readonly string $templates,
        public readonly string $releases,
        public readonly string $public,
        public readonly string $var,
    ) {
    }
}
