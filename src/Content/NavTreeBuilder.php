<?php

declare(strict_types=1);

namespace Cuniform\Content;

use Cuniform\Content\FrontMatter\NavGroup;
use Cuniform\Template\NavItem;

/**
 * Builds per-language primary/footer nav trees from page front matter
 * (SPEC §6.2, §6.3), and validates the three-level nesting cap.
 *
 * Two rules SPEC leaves implicit, resolved here as documented judgment
 * calls rather than silently:
 *  - A page needs `nav_order` set to appear in nav at all — "absent =
 *    reachable but not in nav" (§6.2) is read literally, regardless of
 *    whether `nav_group` is also set.
 *  - When `nav_order` is set but `nav_group` isn't, it defaults to
 *    `primary` — the least surprising placement, and `none` remains
 *    available as an explicit opt-out.
 */
final class NavTreeBuilder
{
    private const MAX_DEPTH = 3;

    /**
     * @param list<NavCandidate> $candidates All pages for one language (SPEC
     *                                        §6.3: hierarchy is per language).
     * @return array{primary: list<NavItem>, footer: list<NavItem>, warnings: list<string>}
     *
     * @throws ContentException When a page nests more than three levels
     *                          below the language segment.
     */
    public function build(array $candidates): array
    {
        $byPath = [];
        foreach ($candidates as $candidate) {
            $this->assertNotTooDeep($candidate);
            $byPath[$candidate->path] = $candidate;
        }

        $warnings = $this->findDirectoriesWithoutAnIndexPage($byPath);

        $navigable = [];
        foreach ($candidates as $candidate) {
            if ($candidate->navOrder === null) {
                continue;
            }

            $group = $candidate->navGroup ?? NavGroup::Primary;
            if ($group === NavGroup::None) {
                continue;
            }

            $navigable[] = $candidate;
        }

        return [
            'primary'  => $this->buildGroup($navigable, $byPath, NavGroup::Primary),
            'footer'   => $this->buildGroup($navigable, $byPath, NavGroup::Footer),
            'warnings' => $warnings,
        ];
    }

    private function assertNotTooDeep(NavCandidate $candidate): void
    {
        $depth = $candidate->path === '' ? 0 : substr_count($candidate->path, '/') + 1;
        if ($depth > self::MAX_DEPTH) {
            throw ContentException::pageNestingTooDeep($candidate->path, self::MAX_DEPTH);
        }
    }

    /**
     * @param array<string, NavCandidate> $byPath
     * @return list<string>
     */
    private function findDirectoriesWithoutAnIndexPage(array $byPath): array
    {
        $warnings = [];
        $checked  = [];

        foreach ($byPath as $path => $candidate) {
            // '' marks the top level (the language root) — a generated post
            // listing, never a content page, so it's never "missing an index".
            $parent = $this->directoryPosition($path);
            while ($parent !== null && $parent !== '') {
                if (!isset($checked[$parent])) {
                    $checked[$parent] = true;
                    if (!isset($byPath[$parent])) {
                        $warnings[] = "directory '{$parent}' has no index.md (SPEC §6.3)";
                    }
                }
                $parent = $this->directoryPosition($parent);
            }
        }

        return $warnings;
    }

    /**
     * @param list<NavCandidate>          $navigable
     * @param array<string, NavCandidate> $byPath
     * @return list<NavItem>
     */
    private function buildGroup(array $navigable, array $byPath, NavGroup $group): array
    {
        $inGroup = array_values(array_filter(
            $navigable,
            fn (NavCandidate $c): bool => ($c->navGroup ?? NavGroup::Primary) === $group
        ));

        $childrenByParent = [];
        foreach ($inGroup as $candidate) {
            $parent                        = $candidate->navParent ?? $this->directoryPosition($candidate->path);
            $childrenByParent[$parent ?? ''][] = $candidate;
        }

        foreach ($childrenByParent as $parent => $items) {
            usort($items, static fn (NavCandidate $a, NavCandidate $b): int => ($a->navOrder ?? 0) <=> ($b->navOrder ?? 0));
            $childrenByParent[$parent] = $items;
        }

        return $this->buildLevel('', $childrenByParent);
    }

    /**
     * @param array<string, list<NavCandidate>> $childrenByParent
     * @return list<NavItem>
     */
    private function buildLevel(string $parentPath, array $childrenByParent): array
    {
        $items = [];
        foreach ($childrenByParent[$parentPath] ?? [] as $candidate) {
            $items[] = new NavItem(
                $candidate->label,
                $candidate->url,
                $this->buildLevel($candidate->path, $childrenByParent)
            );
        }

        return $items;
    }

    private function directoryPosition(string $path): ?string
    {
        if (!str_contains($path, '/')) {
            return $path === '' ? null : '';
        }

        $parent = substr($path, 0, (int) strrpos($path, '/'));

        return $parent;
    }
}
