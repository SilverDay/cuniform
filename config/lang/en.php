<?php

declare(strict_types=1);

/**
 * English template chrome strings (SPEC §7.8), loaded via UiStringCatalogue.
 * Every key here must also exist in every other configured language's file
 * — a missing key is a build error, never a silent fallback.
 */
return [
    'updated_on' => 'Updated on',

    // Fallback month names (SPEC §7.9) — used only when ext-intl is
    // unavailable, so DateFormatter doesn't fall back to a deprecated,
    // locale-dependent strftime().
    'month_01' => 'January',
    'month_02' => 'February',
    'month_03' => 'March',
    'month_04' => 'April',
    'month_05' => 'May',
    'month_06' => 'June',
    'month_07' => 'July',
    'month_08' => 'August',
    'month_09' => 'September',
    'month_10' => 'October',
    'month_11' => 'November',
    'month_12' => 'December',
];
