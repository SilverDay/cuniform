<?php

declare(strict_types=1);

namespace Cuniform\Content;

/**
 * Turns free text into a slug (SPEC §5.4): UTF-8 aware, German transliteration,
 * lowercase, non-alphanumerics collapsed, trimmed, capped at 96 chars.
 *
 * Not used on a post/page slug supplied in front matter — that is used
 * verbatim and never re-slugified (§5.4), which is already how the front
 * matter parser (T4) reads it: it never calls this class. This is for
 * deriving slugs from free-form text, e.g. tag and series names (§5.3,
 * §7.10), where ensureUnique() also applies — a route-table collision
 * between posts/pages is a hard build failure (§8.2), never auto-suffixed,
 * but two different tag names coincidentally slugifying the same way is not
 * a route collision, just a naming clash worth resolving automatically.
 */
final class Slugifier
{
    private const MAX_LENGTH = 96;

    private const TRANSLITERATIONS = [
        'ä' => 'ae',
        'ö' => 'oe',
        'ü' => 'ue',
        'ß' => 'ss',
    ];

    public function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = strtr($text, self::TRANSLITERATIONS);
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? $text;
        $text = trim($text, '-');

        return $this->capLength($text);
    }

    /**
     * Resolve a collision by appending "-2", "-3", ... until the result isn't
     * already taken. Returns $slug unchanged when it isn't taken at all.
     *
     * @param iterable<string> $taken
     */
    public function ensureUnique(string $slug, iterable $taken): string
    {
        $takenSet = [];
        foreach ($taken as $existing) {
            $takenSet[$existing] = true;
        }

        if (!isset($takenSet[$slug])) {
            return $slug;
        }

        $suffix = 2;
        do {
            $candidate = $this->withSuffix($slug, $suffix);
            $suffix++;
        } while (isset($takenSet[$candidate]));

        return $candidate;
    }

    private function withSuffix(string $slug, int $suffix): string
    {
        $suffixText    = '-' . $suffix;
        $maxBaseLength = self::MAX_LENGTH - strlen($suffixText);
        $base          = strlen($slug) > $maxBaseLength
            ? rtrim(substr($slug, 0, $maxBaseLength), '-')
            : $slug;

        return $base . $suffixText;
    }

    private function capLength(string $text): string
    {
        if (strlen($text) <= self::MAX_LENGTH) {
            return $text;
        }

        return rtrim(substr($text, 0, self::MAX_LENGTH), '-');
    }
}
