<?php

declare(strict_types=1);

/**
 * German template chrome strings (SPEC §7.8), loaded via UiStringCatalogue.
 * Every key here must also exist in every other configured language's file
 * — a missing key is a build error, never a silent fallback.
 */
return [
    'updated_on' => 'Aktualisiert am',
    'read_more'  => 'Weiterlesen',

    'posts_heading'   => 'Neueste Beiträge',
    'tag_heading'     => 'Schlagwort: %s',
    'series_heading'  => 'Serie: %s',
    'archive_heading' => 'Archiv: %d',

    'pagination_nav_label'  => 'Seitennummerierung',
    'pagination_previous'   => 'Zurück',
    'pagination_next'       => 'Weiter',
    'pagination_page_label' => 'Seite %1$d von %2$d',

    'search_heading'     => 'Suche',
    'search_summary'     => 'Diese Website durchsuchen.',
    'search_input_label' => 'Suchbegriff',
    'search_no_results'  => 'Keine Ergebnisse.',

    'error_404_heading'   => 'Seite nicht gefunden',
    'error_404_body'      => 'Die gesuchte Seite existiert nicht.',
    'error_404_home_link' => 'Zur Startseite',

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
