<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\LayoutContext $layout
 *
 * SPEC §7.7: renders nothing for a single-language site (hreflang is null in
 * that case, same signal §7.5 uses) and never links to a differently-
 * languaged page as though it were a translation. Simplification, to
 * revisit alongside T18: a language with no translation for this document
 * should render disabled or link to that language's home page instead of
 * being silently absent — that needs the full configured-language list
 * compared against what's available, which isn't wired up yet.
 */
$doc = $layout->page;
if ($doc->hreflang === null) {
    return;
}
?>
<nav class="lang-switcher" aria-label="Language">
    <ul>
        <?php foreach ($doc->hreflang->alternates as $alternate) : ?>
        <?php if ($alternate->hreflang === 'x-default') {
            continue;
        } ?>
        <li>
            <a
                href="<?= eUrl($alternate->url) ?>"
                hreflang="<?= eAttr($alternate->hreflang) ?>"
                lang="<?= eAttr($alternate->hreflang) ?>"
                <?php if ($alternate->hreflang === $doc->language) : ?>aria-current="true"<?php endif; ?>
            ><?= e($alternate->hreflang) ?></a>
        </li>
        <?php endforeach; ?>
    </ul>
</nav>
