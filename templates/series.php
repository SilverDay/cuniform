<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\SeriesViewModel $context
 */
$doc = $context;
?>
<section class="series-index">
    <h1><?= e(sprintf($doc->t('series_heading'), $doc->seriesLabel)) ?></h1>
    <?php foreach ($doc->posts as $item) : ?>
    <?php include __DIR__ . '/partials/post-card.php'; ?>
    <?php endforeach; ?>
</section>
