<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\NeutralErrorContext $context
 *
 * The neutral root `/404.html` (SPEC §7.12) — no `layout.php`, no single
 * `<html lang>` for the whole document: every configured language gets
 * its own block, each wrapped in its own `lang` attribute (NFR-4).
 */
$page = $context;
?>
<!DOCTYPE html>
<html lang="<?= eAttr($page->defaultLanguage) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= eUrl($page->stylesheetUrl) ?>">
</head>
<body>
<main class="error-404-neutral">
<?php foreach ($page->languages as $entry) : ?>
    <section lang="<?= eAttr($entry['language']) ?>">
        <h1><?= e($entry['heading']) ?></h1>
        <p><?= e($entry['body']) ?></p>
        <p>
            <a href="<?= eUrl($entry['homeUrl']) ?>"><?= e($entry['homeLinkLabel']) ?></a>
            &#183;
            <a href="<?= eUrl($entry['searchUrl']) ?>"><?= e($entry['searchLinkLabel']) ?></a>
        </p>
    </section>
<?php endforeach; ?>
</main>
</body>
</html>
