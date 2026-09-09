<?php

declare(strict_types=1);

namespace Cuniform\Config;

final class BuildSettings
{
    public function __construct(
        public readonly int $retainReleases,
        public readonly int $maxDocumentBytes,
        public readonly float $pageCountDropThreshold,
        public readonly int $searchIndexWarnBytes,
    ) {
    }
}
