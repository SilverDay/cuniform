<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\PostViewModel|Cuniform\Template\PageViewModel $doc
 *
 * Only rendered when the document's own `toc` front matter flag is set
 * (SPEC §5.2) — the [toc] shortcode (T9) is the other way a table of
 * contents can appear; this partial is for a template-level "always show
 * a ToC for this document" rendering, not a replacement for the shortcode.
 */
if (!$doc->toc || $doc->headings === []) {
    return;
}
?>
<nav class="toc" aria-label="Table of contents">
    <ul>
        <?php foreach ($doc->headings as $heading) : ?>
        <li class="toc-level-<?= e((string) $heading['level']) ?>">
            <a href="#<?= eAttr($heading['id']) ?>"><?= $heading['text'] ?></a>
        </li>
        <?php endforeach; ?>
    </ul>
</nav>
