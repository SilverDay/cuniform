<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\I18n\UiStringCatalogue;
use Cuniform\Routing\RouteBuilder;
use Cuniform\Template\ArchiveViewModel;
use Cuniform\Template\ErrorViewModel;
use Cuniform\Template\IndexViewModel;
use Cuniform\Template\LayoutContext;
use Cuniform\Template\NeutralErrorContext;
use Cuniform\Template\Pagination;
use Cuniform\Template\SearchViewModel;
use Cuniform\Template\SeriesViewModel;
use Cuniform\Template\TagViewModel;
use Cuniform\Template\TemplateRenderer;
use Cuniform\Template\TemplateResolver;
use Cuniform\Template\ViewModel;

/**
 * Stage 6's other half (SPEC §10.1, §9) — the generated-listing routes
 * (index/pagination, tag/series/archive, search, 404) that SiteTemplateStage
 * doesn't cover, since those render one ResolvedDocument each and these
 * render aggregated ListingSet data instead (T17, closing the gap
 * BUILD-ORDER's T17 progress note and InternalLinkChecker's carve-out
 * both point at).
 */
final class ListingTemplateStage
{
    private readonly TemplateRenderer $templateRenderer;
    private readonly RouteBuilder $routeBuilder;

    public function __construct(
        private readonly Config $config,
        private readonly TemplateResolver $templateResolver,
        private readonly UiStringCatalogue $strings,
        private readonly string $stylesheetUrl,
        private readonly string $searchScriptUrl,
    ) {
        $this->templateRenderer = new TemplateRenderer();
        $this->routeBuilder     = new RouteBuilder($config->urlPrefix, $config->languages, $config->permalink);
    }

    /**
     * @param  array<string, array{primary: list<\Cuniform\Template\NavItem>, footer: list<\Cuniform\Template\NavItem>}> $navByLanguage
     * @return list<GeneratedFile>
     */
    public function build(ListingSet $listing, array $navByLanguage): array
    {
        $files = [];

        foreach ($this->config->languages as $language) {
            $files = [...$files, ...$this->indexPages($listing, $language, $navByLanguage)];
            $files = [...$files, ...$this->tagPages($listing, $language, $navByLanguage)];
            $files = [...$files, ...$this->seriesPages($listing, $language, $navByLanguage)];
            $files = [...$files, ...$this->archivePages($listing, $language, $navByLanguage)];
            $files[] = $this->searchPage($language, $navByLanguage);

            if ($this->routeBuilder->prefixFor($language) !== '') {
                $files[] = $this->errorPage($language, $navByLanguage);
            }
        }

        $files[] = $this->neutralErrorPage();

        return $files;
    }

    /**
     * @param  array<string, array{primary: list<\Cuniform\Template\NavItem>, footer: list<\Cuniform\Template\NavItem>}> $navByLanguage
     * @return list<GeneratedFile>
     */
    private function indexPages(ListingSet $listing, string $language, array $navByLanguage): array
    {
        $posts = $listing->postsByLanguage[$language] ?? [];
        $pages = (new Paginator())->paginate($posts, $this->config->postsPerPage);

        $files = [];
        foreach ($pages as $index => $pagePosts) {
            $page = $index + 1;
            $url  = $this->routeBuilder->indexRoute($language, $page);

            $viewModel = new IndexViewModel(
                $language,
                $this->strings->get($language, 'posts_heading'),
                $this->config->title,
                $this->absoluteUrl($url),
                $this->strings,
                $pagePosts,
                $this->pagination($page, count($pages), fn (int $p): string => $this->routeBuilder->indexRoute($language, $p)),
            );

            $files[] = $this->render('index.php', $viewModel, $url, $language, $navByLanguage);
        }

        return $files;
    }

    /**
     * @param  array<string, array{primary: list<\Cuniform\Template\NavItem>, footer: list<\Cuniform\Template\NavItem>}> $navByLanguage
     * @return list<GeneratedFile>
     */
    private function tagPages(ListingSet $listing, string $language, array $navByLanguage): array
    {
        $files = [];

        foreach ($listing->tagsByLanguage[$language] ?? [] as $tag) {
            $pages = (new Paginator())->paginate($tag->posts, $this->config->postsPerPage);

            foreach ($pages as $index => $pagePosts) {
                $page = $index + 1;
                $url  = $this->routeBuilder->tagRoute($language, $tag->slug, $page);

                $viewModel = new TagViewModel(
                    $language,
                    sprintf($this->strings->get($language, 'tag_heading'), $tag->label),
                    $this->config->title,
                    $this->absoluteUrl($url),
                    $this->strings,
                    $tag->label,
                    $pagePosts,
                    $this->pagination($page, count($pages), fn (int $p): string => $this->routeBuilder->tagRoute($language, $tag->slug, $p)),
                );

                $files[] = $this->render('tag.php', $viewModel, $url, $language, $navByLanguage);
            }
        }

        return $files;
    }

    /**
     * @param  array<string, array{primary: list<\Cuniform\Template\NavItem>, footer: list<\Cuniform\Template\NavItem>}> $navByLanguage
     * @return list<GeneratedFile>
     */
    private function seriesPages(ListingSet $listing, string $language, array $navByLanguage): array
    {
        $files = [];

        foreach ($listing->seriesByLanguage[$language] ?? [] as $series) {
            $url = $this->routeBuilder->seriesRoute($language, $series->slug);

            $viewModel = new SeriesViewModel(
                $language,
                sprintf($this->strings->get($language, 'series_heading'), $series->label),
                $this->config->title,
                $this->absoluteUrl($url),
                $this->strings,
                $series->label,
                $series->posts,
            );

            $files[] = $this->render('series.php', $viewModel, $url, $language, $navByLanguage);
        }

        return $files;
    }

    /**
     * @param  array<string, array{primary: list<\Cuniform\Template\NavItem>, footer: list<\Cuniform\Template\NavItem>}> $navByLanguage
     * @return list<GeneratedFile>
     */
    private function archivePages(ListingSet $listing, string $language, array $navByLanguage): array
    {
        $files = [];

        foreach ($listing->yearsByLanguage[$language] ?? [] as $year) {
            $url = $this->routeBuilder->archiveRoute($language, $year->year);

            $viewModel = new ArchiveViewModel(
                $language,
                sprintf($this->strings->get($language, 'archive_heading'), $year->year),
                $this->config->title,
                $this->absoluteUrl($url),
                $this->strings,
                $year->year,
                $year->posts,
            );

            $files[] = $this->render('archive.php', $viewModel, $url, $language, $navByLanguage);
        }

        return $files;
    }

    /**
     * @param array<string, array{primary: list<\Cuniform\Template\NavItem>, footer: list<\Cuniform\Template\NavItem>}> $navByLanguage
     */
    private function searchPage(string $language, array $navByLanguage): GeneratedFile
    {
        $url = $this->routeBuilder->searchRoute($language);

        $viewModel = new SearchViewModel(
            $language,
            $this->strings->get($language, 'search_heading'),
            $this->strings->get($language, 'search_summary'),
            $this->absoluteUrl($url),
            $this->strings,
            $this->searchScriptUrl,
        );

        return $this->render('search.php', $viewModel, $url, $language, $navByLanguage);
    }

    /**
     * @param array<string, array{primary: list<\Cuniform\Template\NavItem>, footer: list<\Cuniform\Template\NavItem>}> $navByLanguage
     */
    private function errorPage(string $language, array $navByLanguage): GeneratedFile
    {
        $url = $this->routeBuilder->errorRoute($language);

        $viewModel = new ErrorViewModel(
            $language,
            $this->strings->get($language, 'error_404_heading'),
            $this->strings->get($language, 'error_404_body'),
            $this->absoluteUrl($url),
            $this->strings,
            $this->routeBuilder->indexRoute($language, 1),
        );

        return $this->render('404.php', $viewModel, $url, $language, $navByLanguage);
    }

    private function neutralErrorPage(): GeneratedFile
    {
        $languages = array_map(fn (string $language): array => [
            'language'        => $language,
            'homeUrl'         => $this->routeBuilder->indexRoute($language, 1),
            'searchUrl'       => $this->routeBuilder->searchRoute($language),
            'heading'         => $this->strings->get($language, 'error_404_heading'),
            'body'            => $this->strings->get($language, 'error_404_body'),
            'homeLinkLabel'   => $this->strings->get($language, 'error_404_home_link'),
            'searchLinkLabel' => $this->strings->get($language, 'search_heading'),
        ], $this->config->languages);

        $context = new NeutralErrorContext($languages, $this->config->defaultLanguage, $this->stylesheetUrl);
        $html    = $this->templateRenderer->render($this->templateResolver->resolve('404-root.php'), $context);

        return new GeneratedFile('/404.html', $html);
    }

    /**
     * @param array<string, array{primary: list<\Cuniform\Template\NavItem>, footer: list<\Cuniform\Template\NavItem>}> $navByLanguage
     */
    private function render(string $template, ViewModel $viewModel, string $url, string $language, array $navByLanguage): GeneratedFile
    {
        $inner = $this->templateRenderer->render($this->templateResolver->resolve($template), $viewModel);

        $nav    = $navByLanguage[$language] ?? ['primary' => [], 'footer' => []];
        $layout = new LayoutContext($viewModel, $inner, $this->config->title, $nav['primary'], $nav['footer'], $this->stylesheetUrl);
        $html   = $this->templateRenderer->render($this->templateResolver->resolve('layout.php'), $layout);

        return new GeneratedFile($url, $html);
    }

    private function pagination(int $currentPage, int $totalPages, callable $urlForPage): Pagination
    {
        return new Pagination(
            $currentPage,
            $totalPages,
            $currentPage > 1 ? $urlForPage($currentPage - 1) : null,
            $currentPage < $totalPages ? $urlForPage($currentPage + 1) : null,
        );
    }

    private function absoluteUrl(string $path): string
    {
        return rtrim($this->config->baseUrl, '/') . $path;
    }
}
