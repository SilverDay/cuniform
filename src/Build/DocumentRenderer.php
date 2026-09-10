<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Render\RenderedDocument;

/**
 * Stage 5 — Render (SPEC §10.1): the shortcode pre-pass, conversion, and
 * post-pass, per document, via the render adapter (C4). Each top-level
 * document renders at inclusion depth 0 with a fresh IncludeHandler/
 * IncludeResolvingPageRepository pair scoped to its own slug — the starting
 * point for the chain any [include] shortcode inside it walks.
 */
final class DocumentRenderer
{
    public function __construct(private readonly RenderAdapterFactory $adapterFactory)
    {
    }

    /**
     * @param list<ParsedDocument> $includedPages Every page in this build
     *                                             (SPEC §5.6), for [include]
     *                                             resolution (§6.5) — not
     *                                             just the document set this
     *                                             particular render() call
     *                                             belongs to.
     */
    public function render(ResolvedDocument $document, array $includedPages): RenderedDocument
    {
        [$adapter, $kind] = $this->adapterFor($document, $includedPages);

        return $adapter->render($document->parsed->discovered->absolutePath, $kind);
    }

    /**
     * Renders $raw in place of the document's on-disk bytes — used by
     * preview (SPEC §12 Path C, T30) for an unsaved editor buffer that may
     * not match, or exist, on disk. Same include chain, same depth, same
     * shortcode handler set as render() — the only difference is where the
     * Markdown source comes from.
     *
     * @param list<ParsedDocument> $includedPages
     */
    public function renderContent(ResolvedDocument $document, string $raw, array $includedPages): RenderedDocument
    {
        [$adapter, $kind] = $this->adapterFor($document, $includedPages);

        return $adapter->renderContent($raw, $kind, $document->parsed->discovered->absolutePath);
    }

    /**
     * @param  list<ParsedDocument>            $includedPages
     * @return array{0: \Cuniform\Render\RenderAdapter, 1: DocumentKind}
     */
    private function adapterFor(ResolvedDocument $document, array $includedPages): array
    {
        $slug     = $document->parsed->frontMatter->shared->slug;
        $language = $document->parsed->discovered->language;
        $kind     = $document->parsed->frontMatter instanceof PageFrontMatter
            ? DocumentKind::Page
            : DocumentKind::Post;

        $chain   = [$slug];
        $repo    = new IncludeResolvingPageRepository($includedPages, $this->adapterFactory, 1, $chain);
        $adapter = $this->adapterFactory->create($language, 0, $chain, $repo);

        return [$adapter, $kind];
    }
}
