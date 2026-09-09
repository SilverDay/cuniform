<?php

declare(strict_types=1);

/**
 * English template chrome strings (SPEC §7.8), loaded via UiStringCatalogue.
 * Every key here must also exist in every other configured language's file
 * — a missing key is a build error, never a silent fallback.
 */
return [
    'updated_on' => 'Updated on',
    'read_more'  => 'Read more',

    'posts_heading'   => 'Latest posts',
    'tag_heading'     => 'Tagged: %s',
    'series_heading'  => 'Series: %s',
    'archive_heading' => 'Archive: %d',

    'pagination_nav_label'  => 'Pagination',
    'pagination_previous'   => 'Previous',
    'pagination_next'       => 'Next',
    'pagination_page_label' => 'Page %1$d of %2$d',

    'search_heading'     => 'Search',
    'search_summary'     => 'Search this site.',
    'search_input_label' => 'Search query',
    'search_no_results'  => 'No results.',

    'error_404_heading'   => 'Page not found',
    'error_404_body'      => "The page you were looking for doesn't exist.",
    'error_404_home_link' => 'Go to homepage',

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
