<?php

declare(strict_types=1);

/**
 * German template chrome strings (SPEC §7.8), loaded via UiStringCatalogue.
 * Every key here must also exist in every other configured language's file
 * — a missing key is a build error, never a silent fallback.
 */
return [
    'updated_on' => 'Aktualisiert am',

    // Fallback month names (SPEC §7.9) — used only when ext-intl is
    // unavailable, so DateFormatter doesn't fall back to a deprecated,
    // locale-dependent strftime().
    'month_01' => 'Januar',
    'month_02' => 'Februar',
    'month_03' => 'März',
    'month_04' => 'April',
    'month_05' => 'Mai',
    'month_06' => 'Juni',
    'month_07' => 'Juli',
    'month_08' => 'August',
    'month_09' => 'September',
    'month_10' => 'Oktober',
    'month_11' => 'November',
    'month_12' => 'Dezember',
];
