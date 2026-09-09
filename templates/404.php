<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\ErrorViewModel $context
 *
 * The per-language 404 (SPEC §7.12) — same layout.php chain, that
 * language's own strings and navigation. $doc->canonicalUrl points at
 * this page's own error route; there is nothing more canonical to say
 * about a 404, but head.php still needs a value to print.
 */
$doc = $context;
?>
<section class="error-404">
    <h1><?= e($doc->t('error_404_heading')) ?></h1>
    <p><?= e($doc->t('error_404_body')) ?></p>
    <p><a href="<?= eUrl($doc->homeUrl) ?>"><?= e($doc->t('error_404_home_link')) ?></a></p>
</section>
