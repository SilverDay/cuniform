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
 * wraps it in layout.php. Only post.php and page.php are wired here — the
 * generated-listing templates (index/tag/series/archive/search/feed.xml)
 * don't exist yet (T17 is still partial); this stage renders exactly the
 * routes stage 4 (Resolve) produced, which are posts and pages only.
 */
final class SiteTemplateStage
{
    private readonly TemplateRenderer $templateRenderer;

    public function __construct(
        private readonly Config $config,
        private readonly TemplateResolver $templateResolver,
        private readonly UiStringCatalogue $strings,
        private readonly DateFormatter $dateFormatter,
    ) {
        $this->templateRenderer = new TemplateRenderer();
    }

    /**
     * @param  array<string, RenderedDocument> $renderedByIdentifier
     * @return list<GeneratedFile>
     */
    public function build(ResolvedSite $site, array $renderedByIdentifier): array
    {
        $files = [];

        foreach ($site->documents as $document) {
            $identifier = $document->parsed->identifier();
            $rendered   = $renderedByIdentifier[$identifier];

            $viewModel = $this->buildViewModel($document, $rendered);
            $templateName = $document->parsed->frontMatter instanceof PageFrontMatter
                ? $document->parsed->frontMatter->template
                : 'post.php';

            $inner = $this->templateRenderer->render($this->templateResolver->resolve($templateName), $viewModel);

            $language = $document->parsed->discovered->language;
            $nav      = $site->navByLanguage[$language] ?? ['primary' => [], 'footer' => []];

            $layout = new LayoutContext($viewModel, $inner, $this->config->title, $nav['primary'], $nav['footer']);
            $html   = $this->templateRenderer->render($this->templateResolver->resolve('layout.php'), $layout);

            $files[] = new GeneratedFile($document->url, $html);
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
