<?php

declare(strict_types=1);

namespace Cuniform\Routing;

use Cuniform\Build\BuildException;

/**
 * Route-namespace validation (SPEC §8.2): every registered path must be
 * unique, and no document's first path segment may be a reserved slug —
 * a language code or one of the fixed reserved words. A legal-marked page's
 * slug needs no separate reservation: a different document claiming the
 * same slug produces the same path, which the ordinary uniqueness check
 * below already catches.
 */
final class RouteTable
{
    private const RESERVED_WORDS = [
        'tag', 'series', 'archive', 'page', 'search', 'feed.xml', 'atom.xml',
        'sitemap.xml', 'robots.txt', '.well-known', 'admin', 'media',
    ];

    /** @var array<string, string> path => identifier */
    private array $routesByPath = [];

    /**
     * @param list<string> $languages
     */
    public function __construct(private readonly array $languages)
    {
    }

    /**
     * @throws BuildException When the first segment is reserved, or the path
     *                        is already claimed by a different identifier.
     */
    public function register(string $path, string $firstSegment, string $identifier): void
    {
        if (in_array($firstSegment, $this->languages, true) || in_array($firstSegment, self::RESERVED_WORDS, true)) {
            throw BuildException::reservedSlugClaimed($firstSegment, $identifier);
        }

        if (isset($this->routesByPath[$path]) && $this->routesByPath[$path] !== $identifier) {
            throw BuildException::routeCollision($path, $this->routesByPath[$path], $identifier);
        }

        $this->routesByPath[$path] = $identifier;
    }

    public function has(string $path): bool
    {
        return isset($this->routesByPath[$path]);
    }
}
