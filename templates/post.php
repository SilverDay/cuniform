<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\PostViewModel $context
 */
$doc = $context;
?>
<article class="post">
    <h1><?= e($doc->title) ?></h1>
    <p class="post-meta">
        <time><?= e($doc->formattedDate) ?></time>
        <?php if ($doc->formattedUpdated !== null) : ?>
            <span class="post-updated"><?= e($doc->t('updated_on')) ?> <?= e($doc->formattedUpdated) ?></span>
        <?php endif; ?>
    </p>
    <?php if ($doc->tags !== []) : ?>
        <ul class="post-tags">
            <?php foreach ($doc->tags as $tag) : ?>
                <li><?= e($tag) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php include partial('toc.php'); ?>
    <div class="post-body">
        <?= $doc->bodyHtml ?>
    </div>
</article>
