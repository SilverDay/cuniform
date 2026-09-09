<?php

declare(strict_types=1);

/**
 * @var Cuniform\Build\PostSummary $item Set by the including template
 *                                        (index.php/tag.php/series.php/
 *                                        archive.php) before each include —
 *                                        same convention as toc.php reading
 *                                        `$doc` from its caller's scope.
 * @var Cuniform\Template\ViewModel $doc  The including template's own
 *                                        ViewModel, for t() and language.
 */
?>
<article class="post-card">
    <h2><a href="<?= eUrl($item->url) ?>"><?= e($item->title) ?></a></h2>
    <p class="post-meta"><time><?= e($item->formattedDate) ?></time></p>
    <p class="post-summary"><?= e($item->summary) ?></p>
    <?php if ($item->tags !== []) : ?>
    <ul class="post-tags">
        <?php foreach ($item->tags as $tag) : ?>
        <li><a href="<?= eUrl($tag['url']) ?>"><?= e($tag['label']) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p class="post-read-more"><a href="<?= eUrl($item->url) ?>"><?= e($doc->t('read_more')) ?></a></p>
</article>
