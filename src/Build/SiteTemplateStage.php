<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\I18n\DateFormatter;
use Cuniform\I18n\UiStringCatalogue;
use Cuniform\Render\RenderedDocument;
use Cuniform\Template\LayoutContext;
use Cuniform\Template\PageViewModel;
use Cuniform\Template\PostViewModel;
use Cuniform\Template\TemplateRenderer;
use Cuniform\Template\TemplateResolver;
use Cuniform\Template\ViewModel;

/**
 * Stage 6 — Template (SPEC §10.1, §9): builds each document's ViewModel and
 * wraps it in layout.php. Only post.php and page.php are wired here —
 * ListingTemplateStage (T17) handles the generated-listing routes
 * (index/tag/series/archive/search/404) from aggregated data instead of
 * one ResolvedDocument at a time.
 *
 * `$dirty` (T24, SPEC §10.2) restricts this to the documents an
 * incremental build actually needs to re-template — BuildPipeline reuses
 * a cached page's bytes directly for everything not in it, so this
 * returns a map keyed by identifier rather than a plain list, letting the
 * caller merge fresh and cached pages by identifier without a second pass
 * over `$site->documents` to recover which route belongs to which.
 */
final class SiteTemplateStage
{
    private readonly TemplateRenderer $templateRenderer;

    public function __construct(
        private readonly Config $config,
        private readonly TemplateResolver $templateResolver,
        private readonly UiStringCatalogue $strings,
        private readonly DateFormatter $dateFormatter,
        private readonly string $stylesheetUrl,
    ) {
        $this->templateRenderer = new TemplateRenderer();
    }

    /**
     * @param  array<string, RenderedDocument> $renderedByIdentifier
     * @param  array<string, true>             $dirty Identifiers to render;
     *                                                  every other document
     *                                                  in $site is skipped.
     * @return array<string, GeneratedFile> Keyed by identifier.
     */
    public function build(ResolvedSite $site, array $renderedByIdentifier, array $dirty): array
    {
        $files = [];

        foreach ($site->documents as $document) {
            $identifier = $document->parsed->identifier();
            if (!isset($dirty[$identifier])) {
                continue;
            }

            $rendered = $renderedByIdentifier[$identifier];

            $viewModel = $this->buildViewModel($document, $rendered);
            $templateName = $document->parsed->frontMatter instanceof PageFrontMatter
                ? $document->parsed->frontMatter->template
                : 'post.php';

            $inner = $this->templateRenderer->render($this->templateResolver->resolve($templateName), $viewModel);

            $language = $document->parsed->discovered->language;
            $nav      = $site->navByLanguage[$language] ?? ['primary' => [], 'footer' => []];

            $layout = new LayoutContext($viewModel, $inner, $this->config->title, $nav['primary'], $nav['footer'], $this->stylesheetUrl);
            $html   = $this->templateRenderer->render($this->templateResolver->resolve('layout.php'), $layout);

            $files[$identifier] = new GeneratedFile($document->url, $html);
        }

        return $files;
    }

    private function buildViewModel(ResolvedDocument $document, RenderedDocument $rendered): ViewModel
    {
        $frontMatter  = $document->parsed->frontMatter;
        $shared       = $frontMatter->shared;
        $language     = $document->parsed->discovered->language;
        $canonicalUrl = rtrim($this->config->baseUrl, '/') . $document->url;

        if ($frontMatter instanceof PostFrontMatter) {
            $formattedUpdated = $shared->updated !== null
                ? $this->dateFormatter->format($shared->updated, $language)
                : null;

            return new PostViewModel(
                $language,
                $shared->title,
                $shared->summary,
                $canonicalUrl,
                $document->hreflang,
                $this->strings,
                $rendered->bodyHtml,
                $this->dateFormatter->format($frontMatter->date, $language),
                $formattedUpdated,
                $frontMatter->tags,
                $frontMatter->series,
                $shared->noindex,
                $shared->toc,
                $rendered->headings,
            );
        }

        return new PageViewModel(
            $language,
            $shared->title,
            $shared->summary,
            $canonicalUrl,
            $document->hreflang,
            $this->strings,
            $rendered->bodyHtml,
            $shared->noindex,
            $shared->toc,
            $rendered->headings,
        );
    }
}
