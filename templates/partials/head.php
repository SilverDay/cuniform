<?php

declare(strict_types=1);

/**
 * @var Cuniform\Template\LayoutContext $layout
 */
$doc = $layout->page;
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($doc->title) ?> — <?= e($layout->siteTitle) ?></title>
<meta name="description" content="<?= eAttr($doc->summary) ?>">
<?php if ($doc->hreflang !== null) : ?>
<?php foreach ($doc->hreflang->alternates as $alternate) : ?>
<link rel="alternate" hreflang="<?= eAttr($alternate->hreflang) ?>" href="<?= eUrl($alternate->url) ?>">
<?php endforeach; ?>
<meta property="og:locale" content="<?= eAttr($doc->language) ?>">
<?php foreach ($doc->hreflang->alternates as $alternate) : ?>
<?php if ($alternate->hreflang !== 'x-default' && $alternate->hreflang !== $doc->language) : ?>
<meta property="og:locale:alternate" content="<?= eAttr($alternate->hreflang) ?>">
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
<link rel="canonical" href="<?= eUrl($doc->canonicalUrl) ?>">
<link rel="stylesheet" href="/style.css">
