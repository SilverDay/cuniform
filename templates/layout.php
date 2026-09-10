<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\LayoutContext $context
 *
 * Owns <html lang>, <head>, primary nav, language switcher, footer nav
 * (SPEC §9, rule 3). $context->content is post.php/page.php's own already-
 * rendered, already-escaped output — the one place besides $doc->bodyHtml
 * itself that this file trusts without an escaping helper (see
 * tools/escaping-lint.php).
 */
$layout = $context;
$doc    = $layout->page;
?>
<!DOCTYPE html>
<html lang="<?= eAttr($doc->language) ?>">

<head>
    <?php include partial('head.php'); ?>
</head>

<body>
    <?php include partial('nav-primary.php'); ?>
    <?php include partial('lang-switcher.php'); ?>
    <main>
        <?= $layout->content ?>
    </main>
    <?php include partial('nav-footer.php'); ?>
</body>

</html>
