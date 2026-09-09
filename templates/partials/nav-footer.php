<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\LayoutContext $layout
 */
if ($layout->footerNav === []) {
    return;
}
?>
<nav class="nav-footer" aria-label="Footer">
    <ul>
        <?php foreach ($layout->footerNav as $item) : ?>
        <li><a href="<?= eUrl($item->url) ?>"><?= e($item->label) ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>
