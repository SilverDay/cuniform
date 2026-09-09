<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * One rendered route, held in memory until every route has rendered
 * successfully (SPEC §9 rule 6: "a template error aborts the build; a
 * partial page is never emitted" — nothing here is written to disk until
 * the whole batch succeeds, see BuildPipeline).
 */
final class GeneratedFile
{
    public function __construct(
        public readonly string $routePath,
        public readonly string $html,
    ) {
    }

    /**
     * Where this route lands under a release directory: trailing-slash
     * routes get `index.html` appended (SPEC §8.1: "Trailing slash
     * canonical, DirectoryIndex index.html").
     */
    public function relativeFilePath(): string
    {
        return RoutePathResolver::toReleaseFilePath($this->routePath);
    }
}
