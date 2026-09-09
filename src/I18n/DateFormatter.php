<?php

declare(strict_types=1);

namespace Cuniform\I18n;

/**
 * Locale-aware date formatting (SPEC §7.9): German posts read
 * "14. März 2026", English ones "14 March 2026" — note the day carries a
 * trailing period in German but not English, a day-arrangement detail that
 * a bare month-name substitution wouldn't capture on its own.
 *
 * Uses IntlDateFormatter with an explicit ICU pattern per language rather
 * than a locale's default date style: PHP's IntlDateFormatter::LONG for a
 * bare 'en' locale actually produces US ordering ("March 14, 2026"), not
 * the day-first form SPEC's own example shows, so relying on locale-default
 * styles would silently produce the wrong output for the one language this
 * matters most for.
 *
 * ext-intl is a *soft* requirement (§15.1, §7.9) — when it's unavailable,
 * falls back to an explicit per-language month-name table read from the UI
 * string catalogue (T13), keyed `month_01`..`month_12`. Deliberately not
 * strftime(): deprecated since PHP 8.1, and locale-dependent in a way that
 * fails silently on a server with no locales generated (§7.9) — the whole
 * point of the fallback is to not depend on system locale data at all.
 */
final class DateFormatter
{
    private const PATTERNS = [
        'de' => 'd. MMMM y',
        'en' => 'd MMMM y',
    ];

    private const DEFAULT_PATTERN = 'd MMMM y';

    /** Trailing mark after the day number, only when the fallback path is used. */
    private const FALLBACK_DAY_SUFFIX = [
        'de' => '.',
    ];

    /**
     * @param bool $forceFallback Skip ext-intl even when it's available —
     *                            for testing the fallback path deterministically,
     *                            regardless of what's installed on a given machine.
     */
    public function __construct(
        private readonly UiStringCatalogue $strings,
        private readonly bool $forceFallback = false,
    ) {
    }

    public function format(\DateTimeImmutable $date, string $language): string
    {
        if (!$this->forceFallback && class_exists(\IntlDateFormatter::class)) {
            $formatted = $this->formatWithIntl($date, $language);
            if ($formatted !== null) {
                return $formatted;
            }
        }

        return $this->formatWithFallback($date, $language);
    }

    private function formatWithIntl(\DateTimeImmutable $date, string $language): ?string
    {
        $pattern   = self::PATTERNS[$language] ?? self::DEFAULT_PATTERN;
        $formatter = new \IntlDateFormatter(
            $language,
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            $date->getTimezone(),
            \IntlDateFormatter::GREGORIAN,
            $pattern
        );

        $result = $formatter->format($date);

        return $result === false ? null : $result;
    }

    private function formatWithFallback(\DateTimeImmutable $date, string $language): string
    {
        $month  = $this->strings->get($language, sprintf('month_%02d', (int) $date->format('n')));
        $suffix = self::FALLBACK_DAY_SUFFIX[$language] ?? '';

        return sprintf('%d%s %s %s', (int) $date->format('j'), $suffix, $month, $date->format('Y'));
    }
}
