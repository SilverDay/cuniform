<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\LayoutContext $layout
 */
if ($layout->primaryNav === []) {
    return;
}
?>
<nav class="nav-primary" aria-label="Primary">
    <ul>
        <?php foreach ($layout->primaryNav as $item) : ?>
        <li>
            <a href="<?= eUrl($item->url) ?>"><?= e($item->label) ?></a>
            <?php if ($item->children !== []) : ?>
            <ul>
                <?php foreach ($item->children as $child) : ?>
                <li><a href="<?= eUrl($child->url) ?>"><?= e($child->label) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</nav>
