<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\Pagination                             $pagination Set by the including template.
 * @var Cuniform\Template\IndexViewModel|Cuniform\Template\TagViewModel $doc  For t().
 */
if ($pagination->totalPages <= 1) {
    return;
}
?>
<nav class="pagination" aria-label="<?= eAttr($doc->t('pagination_nav_label')) ?>">
    <?php if ($pagination->previousUrl !== null) : ?>
    <a class="pagination-previous" href="<?= eUrl($pagination->previousUrl) ?>"><?= e($doc->t('pagination_previous')) ?></a>
    <?php endif; ?>
    <span class="pagination-status"><?= e(sprintf($doc->t('pagination_page_label'), $pagination->currentPage, $pagination->totalPages)) ?></span>
    <?php if ($pagination->nextUrl !== null) : ?>
    <a class="pagination-next" href="<?= eUrl($pagination->nextUrl) ?>"><?= e($doc->t('pagination_next')) ?></a>
    <?php endif; ?>
</nav>
