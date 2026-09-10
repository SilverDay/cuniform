<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\PageViewModel $context
 */
$doc = $context;
?>
<article class="page">
    <h1><?= e($doc->title) ?></h1>
    <?php include partial('toc.php'); ?>
    <div class="page-body">
        <?= $doc->bodyHtml ?>
    </div>
</article>
