<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Render\Shortcode\Handlers\IncludedPage;
use Cuniform\Render\Shortcode\Handlers\IncludedPageRepository;

/**
 * The concrete IncludedPageRepository T9 deferred (SPEC §6.5) — resolves
 * `[include page="slug"]` against the pages actually in this build.
 *
 * Cycle/depth tracking (IncludeHandler's own job) needs a *fresh*
 * IncludeHandler at each level of nesting, bound to that level's depth and
 * inclusion chain — see IncludeHandler's class docblock. This class carries
 * exactly that: $depth is the depth a page found *through this instance*
 * should be rendered at, and $chainPrefix is the chain before that page's
 * own slug is appended. A find() call that succeeds constructs the next
 * instance down (depth+1, chain+[slug]) and hands it to a fresh
 * IncludeHandler for the found page's own body — so a second level of
 * nesting is checked against the *correct* chain, not the top-level one.
 */
final class IncludeResolvingPageRepository implements IncludedPageRepository
{
    /**
     * @param list<ParsedDocument> $includedPages Pages actually in this build
     *                                             (SPEC §5.6 — a draft or a
     *                                             not-yet-due scheduled page
     *                                             is never a valid include
     *                                             target).
     * @param list<string>         $chainPrefix
     */
    public function __construct(
        private readonly array $includedPages,
        private readonly RenderAdapterFactory $adapterFactory,
        private readonly int $depth,
        private readonly array $chainPrefix,
    ) {
    }

    public function find(string $slug, string $language): ?IncludedPage
    {
        $document = $this->findDocument($slug, $language);
        if ($document === null) {
            return null;
        }

        $chain      = [...$this->chainPrefix, $slug];
        $nestedRepo = new self($this->includedPages, $this->adapterFactory, $this->depth + 1, $chain);
        $adapter    = $this->adapterFactory->create($language, $this->depth, $chain, $nestedRepo);
        $rendered   = $adapter->render($document->discovered->absolutePath, DocumentKind::Page);

        return new IncludedPage($language, $rendered->bodyHtml);
    }

    private function findDocument(string $slug, string $language): ?ParsedDocument
    {
        foreach ($this->includedPages as $document) {
            if (!$document->frontMatter instanceof PageFrontMatter) {
                continue;
            }

            if ($document->discovered->language === $language && $document->frontMatter->shared->slug === $slug) {
                return $document;
            }
        }

        return null;
    }
}
