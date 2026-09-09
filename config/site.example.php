<?php

declare(strict_types=1);

/**
 * Copy to config/site.php and adjust. site.php is gitignored.
 * Every key here is validated at build time (SPEC §7.1, §8.1).
 */
return [
    'base_url'          => 'https://blog.silverday.de',
    'title'             => 'SilverDay',
    'timezone'          => 'Europe/Berlin',

    // Languages. One entry = single-language site (SPEC §7.1).
    'languages'         => ['de', 'en'],
    'default_language'  => 'en',

    // 'always' | 'auto' | 'never'. 'always' keeps URLs stable if a language is added later.
    'url_prefix'        => 'always',

    // Tokens: {slug} {year} {month} {day}
    'permalink'         => '/{slug}/',

    'posts_per_page'    => 10,
    'feed_items'        => 20,

    'paths' => [
        'content'   => __DIR__ . '/../content',
        'templates' => __DIR__ . '/../templates',
        'releases'  => __DIR__ . '/../releases',
        'public'    => __DIR__ . '/../public',
        'var'       => __DIR__ . '/../var',
    ],

    'build' => [
        'retain_releases'          => 5,
        'max_document_bytes'       => 2 * 1024 * 1024,
        'page_count_drop_threshold'=> 0.10,
        'search_index_warn_bytes'  => 750 * 1024,
    ],

    'mail' => [
        // Outbound only; no inbound, no bounces (SPEC §15.2).
        'enabled'        => true,
        'from'           => 'cuniform@silverday.de',
        'notify'         => 'REPLACE-ME',
        'envelope_sender'=> 'cuniform@silverday.de',
    ],
];
