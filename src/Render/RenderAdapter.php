<?php

declare(strict_types=1);

namespace Cuniform\Render;

use Cuniform\Content\FilesystemGateway;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\FrontMatterParser;
use Cuniform\Render\Shortcode\ShortcodeHandlerRegistry;
use Cuniform\Render\Shortcode\ShortcodeProcessor;

/**
 * Render adapter (C4, SPEC §4.6, §9, §4.5). Reads a Markdown file through the
 * filesystem gateway (realpath containment, extension/size guards — the
 * renderer's own convertFile() protections are not inherited since this
 * calls convert(string), never convertFile()), splits and validates its
 * front matter, then renders the remaining body — never the front matter
 * block — through the shortcode pre/post-pass around an injected,
 * caller-owned renderer instance.
 *
 * The renderer is injected rather than built here so it can be the *same*
 * instance passed to a `[toc]` shortcode handler (T9): that handler needs
 * getHeadings() to reflect the document currently being rendered, which
 * only works if adapter and handler share one Md2Html.
 */
final class RenderAdapter
{
    private readonly ShortcodeProcessor $shortcodes;

    /**
     * @param Md2Html $renderer Must be headless (SPEC §9) — asserted, not just assumed,
     *                          since this is now an injected, possibly-shared instance
     *                          rather than one this class builds itself. Sharing matters
     *                          for the [toc] shortcode handler (T9), which needs
     *                          getHeadings() from the very same instance after it renders.
     */
    public function __construct(
        private readonly FilesystemGateway $gateway,
        private readonly FrontMatterParser $frontMatterParser,
        private readonly Md2Html $renderer,
        ShortcodeHandlerRegistry $handlers = new ShortcodeHandlerRegistry(),
    ) {
        if (!$renderer->isHeadless()) {
            throw RenderException::rendererNotHeadless();
        }

        $this->shortcodes = new ShortcodeProcessor($renderer, $handlers);
    }

    public function render(string $path, DocumentKind $kind): RenderedDocument
    {
        $raw         = $this->gateway->read($path);
        $frontMatter = $this->frontMatterParser->parse($raw, $kind, $path);
        $bodyHtml    = $this->shortcodes->convert($frontMatter->body);

        return new RenderedDocument($frontMatter, $bodyHtml, $this->renderer->getHeadings());
    }
}
