<?php

declare(strict_types=1);

return [
    'base_url'          => 'https://blog.silverday.de',
    'title'             => 'SilverDay',
    'timezone'          => 'Europe/Berlin',
    'languages'         => ['de', 'en'],
    'default_language'  => 'de',
    'url_prefix'        => 'always',
    'permalink'         => '/{slug}/',
    'posts_per_page'    => 10,
    'feed_items'        => 20,
    'paths' => [
        'content'   => '/tmp/cuniform-fixture/content',
        'templates' => '/tmp/cuniform-fixture/templates',
        'releases'  => '/tmp/cuniform-fixture/releases',
        'public'    => '/tmp/cuniform-fixture/public',
        'var'       => '/tmp/cuniform-fixture/var',
    ],
    'build' => [
        'retain_releases'           => 5,
        'max_document_bytes'        => 2 * 1024 * 1024,
        'page_count_drop_threshold' => 0.10,
        'search_index_warn_bytes'   => 750 * 1024,
    ],
    'mail' => [
        'enabled'         => true,
        'from'            => 'cuniform@silverday.de',
        'notify'          => 'notify@example.com',
        'envelope_sender' => 'cuniform@silverday.de',
    ],
];
