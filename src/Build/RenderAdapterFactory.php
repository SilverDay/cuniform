<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Content\FilesystemGateway;
use Cuniform\Content\FrontMatter\FrontMatterParser;
use Cuniform\Render\Md2Html;
use Cuniform\Render\RenderAdapter;
use Cuniform\Render\Shortcode\Handlers\DetailsHandler;
use Cuniform\Render\Shortcode\Handlers\EmbedHandler;
use Cuniform\Render\Shortcode\Handlers\FigureHandler;
use Cuniform\Render\Shortcode\Handlers\IncludedPageRepository;
use Cuniform\Render\Shortcode\Handlers\IncludeHandler;
use Cuniform\Render\Shortcode\Handlers\NoteHandler;
use Cuniform\Render\Shortcode\Handlers\TocHandler;
use Cuniform\Render\Shortcode\Handlers\VideoHandler;
use Cuniform\Render\Shortcode\ShortcodeHandlerRegistry;

/**
 * Builds one RenderAdapter per rendering position (SPEC §4.5, §6.5). A fresh
 * Md2Html + handler registry every call, never reused: `[toc]` needs the
 * *current* document's own headings (T9's shared-instance requirement), and
 * `[include]`'s depth/cycle tracking is specific to one position in one
 * inclusion chain (IncludeHandler's own docblock) — nothing here is safe to
 * share across two different documents or two different chain positions.
 */
final class RenderAdapterFactory
{
    public function __construct(
        private readonly FilesystemGateway $gateway,
        private readonly FrontMatterParser $frontMatterParser,
    ) {
    }

    /**
     * @param list<string> $inclusionChain
     */
    public function create(
        string $language,
        int $depth,
        array $inclusionChain,
        IncludedPageRepository $pages,
    ): RenderAdapter {
        $renderer = new Md2Html(['headless' => true]);

        $handlers = new ShortcodeHandlerRegistry([
            new FigureHandler(),
            new VideoHandler(),
            new EmbedHandler(),
            new DetailsHandler(),
            new NoteHandler(),
            new TocHandler($renderer),
            new IncludeHandler($pages, $language, $depth, $inclusionChain),
        ]);

        return new RenderAdapter($this->gateway, $this->frontMatterParser, $renderer, $handlers);
    }
}
