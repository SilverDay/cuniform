<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\SearchViewModel $context
 *
 * The page ships with no results of its own — search.js fetches
 * `/search-index.json` at runtime and filters it client-side to
 * $doc->language (SPEC §11.3, §7.10). Deliberately no inline script
 * (SPEC §14.1): the CSP carries no per-request nonce on a static page, so
 * this ships as an external file instead of a hashed inline one.
 */
$doc = $context;
?>
<section class="search" data-cuniform-search data-lang="<?= eAttr($doc->language) ?>" data-index-url="/search-index.json">
    <h1><?= e($doc->t('search_heading')) ?></h1>
    <form role="search" onsubmit="return false">
        <label for="search-query"><?= e($doc->t('search_input_label')) ?></label>
        <input type="search" id="search-query" name="q" autocomplete="off">
    </form>
    <p class="search-no-results" hidden><?= e($doc->t('search_no_results')) ?></p>
    <ul class="search-results"></ul>
</section>
<script src="<?= eUrl($doc->searchScriptUrl) ?>" defer></script>
