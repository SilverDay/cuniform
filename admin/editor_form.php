<?php

declare(strict_types=1);

use Cuniform\Admin\Editor\EditorDocument;
use Cuniform\Admin\Editor\EditorSaveRequest;
use Cuniform\Content\FrontMatter\DocumentKind;

/**
 * Front-matter-form field mapping shared by editor.php (SPEC §12 Path B)
 * and preview.php (§12 Path C) — both read the identical set of POSTed
 * form fields into an EditorSaveRequest, so this lives in one place rather
 * than two copies that could drift. Nothing here is a page in its own
 * right; every admin/*.php entry point that needs it requires this file
 * explicitly, the same way each requires bootstrap.php.
 */

/**
 * @return array<string, string|bool>
 */
function editor_blank_form_values(string $language, string $kind): array
{
    return [
        'identifier' => '', 'expected_sha256' => '', 'language' => $language, 'kind' => $kind,
        'title' => '', 'slug' => '', 'status' => 'draft', 'summary' => '',
        'translation_key' => '', 'updated' => '', 'image' => '', 'image_alt' => '',
        'canonical' => '', 'noindex' => false, 'aliases' => '', 'toc' => false, 'source_id' => '',
        'date' => '', 'tags' => '', 'series' => '',
        'template' => 'page.php', 'nav_label' => '', 'nav_order' => '', 'nav_parent' => '',
        'nav_group' => '', 'sitemap_priority' => '', 'legal' => '',
        'body' => '',
    ];
}

/**
 * @return array<string, string|bool>
 */
function editor_form_values_from_document(EditorDocument $doc): array
{
    return [
        'identifier' => $doc->identifier,
        'expected_sha256' => $doc->sha256,
        'language' => $doc->language,
        'kind' => $doc->kind === DocumentKind::Post ? 'post' : 'page',
        'title' => $doc->title,
        'slug' => $doc->slug,
        'status' => $doc->status->value,
        'summary' => $doc->summary,
        'translation_key' => $doc->translationKey ?? '',
        'updated' => $doc->updated?->format('Y-m-d\TH:i:sP') ?? '',
        'image' => $doc->image ?? '',
        'image_alt' => $doc->imageAlt ?? '',
        'canonical' => $doc->canonical ?? '',
        'noindex' => $doc->noindex,
        'aliases' => implode("\n", $doc->aliases),
        'toc' => $doc->toc,
        'source_id' => $doc->sourceId ?? '',
        'date' => $doc->date?->format('Y-m-d\TH:i:sP') ?? '',
        'tags' => implode(', ', $doc->tags),
        'series' => $doc->series ?? '',
        'template' => $doc->template ?? 'page.php',
        'nav_label' => $doc->navLabel ?? '',
        'nav_order' => $doc->navOrder === null ? '' : (string) $doc->navOrder,
        'nav_parent' => $doc->navParent ?? '',
        'nav_group' => $doc->navGroup === null ? '' : $doc->navGroup->value,
        'sitemap_priority' => $doc->sitemapPriority === null ? '' : (string) $doc->sitemapPriority,
        'legal' => $doc->legal === null ? '' : $doc->legal->value,
        'body' => $doc->body,
    ];
}

/**
 * @return array<string, string|bool>
 */
function editor_form_values_from_post(): array
{
    return [
        'identifier' => admin_post_field('identifier'),
        'expected_sha256' => admin_post_field('expected_sha256'),
        'language' => admin_post_field('language'),
        'kind' => admin_post_field('kind'),
        'title' => admin_post_field('title'),
        'slug' => admin_post_field('slug'),
        'status' => admin_post_field('status'),
        'summary' => admin_post_field('summary'),
        'translation_key' => admin_post_field('translation_key'),
        'updated' => admin_post_field('updated'),
        'image' => admin_post_field('image'),
        'image_alt' => admin_post_field('image_alt'),
        'canonical' => admin_post_field('canonical'),
        'noindex' => admin_post_checked('noindex'),
        'aliases' => admin_post_field('aliases'),
        'toc' => admin_post_checked('toc'),
        'source_id' => admin_post_field('source_id'),
        'date' => admin_post_field('date'),
        'tags' => admin_post_field('tags'),
        'series' => admin_post_field('series'),
        'template' => admin_post_field('template'),
        'nav_label' => admin_post_field('nav_label'),
        'nav_order' => admin_post_field('nav_order'),
        'nav_parent' => admin_post_field('nav_parent'),
        'nav_group' => admin_post_field('nav_group'),
        'sitemap_priority' => admin_post_field('sitemap_priority'),
        'legal' => admin_post_field('legal'),
        'body' => admin_post_field('body'),
    ];
}

function editor_request_from_post(): EditorSaveRequest
{
    $kind = admin_post_field('kind') === 'page' ? DocumentKind::Page : DocumentKind::Post;

    return new EditorSaveRequest(
        identifier: admin_post_field_or_null('identifier'),
        expectedSha256: admin_post_field_or_null('expected_sha256'),
        language: admin_post_field('language'),
        kind: $kind,
        title: admin_post_field('title'),
        slug: admin_post_field('slug'),
        status: admin_post_field('status'),
        summary: admin_post_field('summary'),
        translationKey: admin_post_field_or_null('translation_key'),
        updated: admin_post_field_or_null('updated'),
        image: admin_post_field_or_null('image'),
        imageAlt: admin_post_field_or_null('image_alt'),
        canonical: admin_post_field_or_null('canonical'),
        noindex: admin_post_checked('noindex'),
        aliases: admin_post_lines('aliases'),
        toc: admin_post_checked('toc'),
        sourceId: admin_post_field_or_null('source_id'),
        date: admin_post_field_or_null('date'),
        tags: admin_post_csv('tags'),
        series: admin_post_field_or_null('series'),
        template: admin_post_field_or_null('template'),
        navLabel: admin_post_field_or_null('nav_label'),
        navOrder: admin_post_field_or_null('nav_order'),
        navParent: admin_post_field_or_null('nav_parent'),
        navGroup: admin_post_field_or_null('nav_group'),
        sitemapPriority: admin_post_field_or_null('sitemap_priority'),
        legal: admin_post_field_or_null('legal'),
        body: admin_post_field('body'),
    );
}
