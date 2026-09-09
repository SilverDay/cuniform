<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\IndexViewModel $context
 */
$doc        = $context;
$pagination = $doc->pagination;
?>
<section class="post-index">
    <h1><?= e($doc->t('posts_heading')) ?></h1>
    <?php foreach ($doc->posts as $item) : ?>
    <?php include __DIR__ . '/partials/post-card.php'; ?>
    <?php endforeach; ?>
    <?php include __DIR__ . '/partials/pagination.php'; ?>
</section>
