<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\TagViewModel $context
 */
$doc        = $context;
$pagination = $doc->pagination;
?>
<section class="tag-archive">
    <h1><?= e(sprintf($doc->t('tag_heading'), $doc->tagLabel)) ?></h1>
    <?php foreach ($doc->posts as $item) : ?>
        <?php include partial('post-card.php'); ?>
    <?php endforeach; ?>
    <?php include partial('pagination.php'); ?>
</section>
