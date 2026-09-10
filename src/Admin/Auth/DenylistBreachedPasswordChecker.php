<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

/**
 * Default BreachedPasswordChecker: an offline, bundled denylist of common/
 * breached passwords (`data/common-passwords.txt`, one entry per line).
 *
 * Judgment call, documented rather than silently decided (SPEC §13.1 says
 * "breached-password check on set" but names no corpus): the alternative is
 * a live k-anonymity lookup against a third-party breach-database API
 * (e.g. HIBP). That would be the more thorough check, but it's a new kind
 * of runtime network dependency this project has nowhere else — every
 * other outbound request the spec allows is outbound mail (§15.2), and
 * §14.3's "zero third-party requests" principle, while stated for public
 * pages specifically, argues for the same restraint here: an offline check
 * is untestable-by-network-mock-only in this project's own test rules
 * ("No network ... in tests", php-style.md) and keeps working if the host
 * ever loses outbound connectivity. This is a real, accepted limitation —
 * a password absent from this list but present in a real breach corpus
 * will pass — not a claim of HIBP-equivalent coverage. Swappable later:
 * anything implementing BreachedPasswordChecker works with PasswordPolicy
 * unchanged.
 */
final class DenylistBreachedPasswordChecker implements BreachedPasswordChecker
{
    /** @var list<string>|null */
    private ?array $denylist = null;

    public function __construct(private readonly string $listPath = __DIR__ . '/data/common-passwords.txt')
    {
    }

    public function isBreached(string $password): bool
    {
        return in_array(strtolower($password), $this->load(), true);
    }

    /**
     * @return list<string>
     */
    private function load(): array
    {
        if ($this->denylist !== null) {
            return $this->denylist;
        }

        $contents = is_file($this->listPath) ? file_get_contents($this->listPath) : false;
        if ($contents === false) {
            $this->denylist = [];

            return $this->denylist;
        }

        $lines = array_filter(explode("\n", $contents), static fn (string $line): bool => trim($line) !== '');

        $this->denylist = array_values(array_map(
            static fn (string $line): string => strtolower(trim($line)),
            $lines,
        ));

        return $this->denylist;
    }
}
