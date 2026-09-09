<?php

declare(strict_types=1);

namespace Cuniform\I18n;

use Cuniform\Build\BuildException;

/**
 * The build-wide check SPEC §10.3 requires: an hreflang set is asymmetric
 * when page A advertises page B as an alternate but B does not advertise A
 * back. A well-behaved build never produces this on its own — every page's
 * set is built from the same translation group — but an incremental build
 * (§10.2) rebuilding only one side of a pair, or any other inconsistency
 * between two pages' cached sets, would produce exactly this, silently,
 * without a check like this one run across the whole site.
 */
final class HreflangSymmetryChecker
{
    /**
     * @param array<string, HreflangSet> $setsByUrl Every rendered page's own
     *                                                hreflang set, keyed by
     *                                                its own canonical URL.
     *
     * @throws BuildException
     */
    public function assertSymmetric(array $setsByUrl): void
    {
        $errors = [];

        foreach ($setsByUrl as $url => $set) {
            foreach ($set->alternates as $entry) {
                if ($entry->hreflang === 'x-default' || $entry->url === $url) {
                    continue;
                }

                $target = $setsByUrl[$entry->url] ?? null;
                if ($target === null) {
                    $errors[] = "'{$url}' advertises '{$entry->url}' as an alternate, but that URL has no hreflang set";

                    continue;
                }

                if (!$this->advertises($target, $url)) {
                    $errors[] = "asymmetric hreflang: '{$url}' advertises '{$entry->url}', "
                        . "but '{$entry->url}' does not advertise '{$url}' back (SPEC §10.3)";
                }
            }
        }

        if ($errors !== []) {
            throw BuildException::fromErrors($errors);
        }
    }

    private function advertises(HreflangSet $set, string $url): bool
    {
        foreach ($set->alternates as $entry) {
            if ($entry->url === $url) {
                return true;
            }
        }

        return false;
    }
}
