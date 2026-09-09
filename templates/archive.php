<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\ArchiveViewModel $context
 */
$doc = $context;
?>
<section class="year-archive">
    <h1><?= e(sprintf($doc->t('archive_heading'), $doc->year)) ?></h1>
    <?php foreach ($doc->posts as $item) : ?>
    <?php include __DIR__ . '/partials/post-card.php'; ?>
    <?php endforeach; ?>
</section>
