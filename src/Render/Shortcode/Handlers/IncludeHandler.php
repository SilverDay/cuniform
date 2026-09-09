<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode\Handlers;

use Cuniform\Render\RenderException;
use Cuniform\Render\Shortcode\ShortcodeBodyType;
use Cuniform\Render\Shortcode\ShortcodeHandler;

/**
 * `[include page="slug"]` inlines another page's rendered body from the
 * *same language* (SPEC §4.5, §6.5). Depth capped at 2, cycles are a build
 * error — both cross a whole rendering chain, not just one document, so
 * this handler is constructed per-position in that chain: whoever resolves
 * an include (eventually T19) constructs a fresh IncludeHandler for the
 * page it resolves into, with depth incremented and the slug appended to
 * the chain, so nesting is tracked correctly across multiple includes.
 */
final class IncludeHandler implements ShortcodeHandler
{
    private const MAX_DEPTH = 2;

    /**
     * @param list<string> $inclusionChain Slugs already included along the
     *                                     current chain, starting with the
     *                                     top-level document's own slug.
     */
    public function __construct(
        private readonly IncludedPageRepository $pages,
        private readonly string $currentLanguage,
        private readonly int $currentDepth = 0,
        private readonly array $inclusionChain = [],
    ) {
    }

    public function name(): string
    {
        return 'include';
    }

    public function bodyType(): ShortcodeBodyType
    {
        return ShortcodeBodyType::None;
    }

    public function isBlockLevel(): bool
    {
        return true;
    }

    public function render(array $attributes, ?string $body): string
    {
        $slug = $attributes['page'] ?? '';
        if ($slug === '') {
            throw RenderException::missingIncludeSlug();
        }

        if ($this->currentDepth + 1 > self::MAX_DEPTH) {
            throw RenderException::includeDepthExceeded($slug, self::MAX_DEPTH);
        }

        if (in_array($slug, $this->inclusionChain, true)) {
            throw RenderException::includeCycle([...$this->inclusionChain, $slug]);
        }

        $page = $this->pages->find($slug, $this->currentLanguage);
        if ($page === null) {
            throw RenderException::includeNotFound($slug, $this->currentLanguage);
        }

        if ($page->language !== $this->currentLanguage) {
            throw RenderException::includeCrossLanguage($slug, $this->currentLanguage, $page->language);
        }

        return $page->bodyHtml;
    }
}
