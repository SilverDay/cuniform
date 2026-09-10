<?php

declare(strict_types=1);

namespace Cuniform\Admin\Preview;

use Cuniform\Admin\Editor\EditorDocumentStore;
use Cuniform\Admin\Editor\EditorSaveRequest;
use Cuniform\Build\AssetFingerprinter;
use Cuniform\Build\ContentDiscoverer;
use Cuniform\Build\DiscoveredDocument;
use Cuniform\Build\DocumentParser;
use Cuniform\Build\DocumentRenderer;
use Cuniform\Build\ParsedDocument;
use Cuniform\Build\RenderAdapterFactory;
use Cuniform\Build\ResolvedDocument;
use Cuniform\Build\SiteResolver;
use Cuniform\Build\SiteTemplateStage;
use Cuniform\Config\Config;
use Cuniform\Content\FilesystemGateway;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\FrontMatterParser;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Content\FrontMatter\SharedFrontMatter;
use Cuniform\CuniformException;
use Cuniform\I18n\DateFormatter;
use Cuniform\I18n\UiStringCatalogue;
use Cuniform\Template\TemplateResolver;

/**
 * Preview (SPEC §12 Path C): renders one document through the identical
 * C3/C4/C5 chain a real build uses — ContentDiscoverer/DocumentParser
 * (stages 2-3), SiteResolver (stage 4), DocumentRenderer (stage 5,
 * shortcodes + Md2Html), SiteTemplateStage (stage 6) — the same classes
 * BuildPipeline itself calls, not a parallel reimplementation. That's what
 * makes "byte-identical to build output" (the T30 acceptance criterion) a
 * property of reuse rather than something that has to be independently
 * maintained in sync.
 *
 * The one substitution: $request's raw bytes (an unsaved editor buffer,
 * possibly for a document that isn't on disk at all yet) stand in for
 * whatever DocumentParser would otherwise have read from the file at the
 * path $request would occupy once saved (EditorDocumentStore::
 * previewPath()) — spliced into the real, on-disk corpus in that
 * document's place before SiteResolver ever runs, so its route, hreflang
 * set, and nav placement come out exactly as they would in a real build of
 * this exact content. Every other document in the corpus renders from disk,
 * completely unaffected.
 *
 * A draft or not-yet-due scheduled document (SPEC §5.6) would normally
 * never reach SiteResolver's included set at all — but SPEC §12 requires
 * preview to work for exactly those. The previewed document's own status is
 * therefore coerced to Published for this render only, before splicing:
 * this is what "renders as it will look once actually published" has to
 * mean, and no template reads the `status` field itself (confirmed: it
 * never appears in templates/), so this coercion is invisible in the output
 * bytes for an already-published document — the case the byte-identity
 * test exercises.
 */
final class PreviewRenderer
{
    public function __construct(
        private readonly Config $config,
        private readonly string $langDir,
        private readonly EditorDocumentStore $editorStore,
    ) {
    }

    public function render(EditorSaveRequest $request): PreviewResult
    {
        if (!in_array($request->language, $this->config->languages, true)) {
            return PreviewResult::invalid(["'{$request->language}' is not a configured language"]);
        }

        $pathInfo = $this->editorStore->previewPath($request);
        if ($pathInfo['path'] === null) {
            return PreviewResult::invalid($pathInfo['errors']);
        }
        $relativePath = $pathInfo['path'];

        $raw = $this->editorStore->render($request);

        try {
            return $this->renderRaw($request, $relativePath, $raw);
        } catch (CuniformException $e) {
            return PreviewResult::invalid([$e->getMessage()]);
        }
    }

    private function renderRaw(EditorSaveRequest $request, string $relativePath, string $raw): PreviewResult
    {
        $gateway           = new FilesystemGateway($this->config->paths->content, $this->config->build->maxDocumentBytes);
        $frontMatterParser = new FrontMatterParser($this->config->timezone);

        $frontMatter = $frontMatterParser->parse($raw, $request->kind, $relativePath);
        $frontMatter = $this->forcePublished($frontMatter);

        $absolutePath = rtrim($this->config->paths->content, '/') . '/' . $relativePath;
        $discovered   = new DiscoveredDocument($absolutePath, $relativePath, $request->kind, $request->language, 0, hash('sha256', $raw));
        $previewed    = new ParsedDocument($discovered, $frontMatter);

        $discoverer     = new ContentDiscoverer($this->config->paths->content, $this->config->languages);
        $documentParser = new DocumentParser($gateway, $frontMatterParser);
        $corpus         = $this->spliceIn($documentParser->parse($discoverer->discover()), $previewed);

        $site = (new SiteResolver($this->config))->resolve($corpus, new \DateTimeImmutable());

        $resolvedDoc = null;
        foreach ($site->documents as $document) {
            if ($document->parsed->identifier() === $previewed->identifier()) {
                $resolvedDoc = $document;

                break;
            }
        }

        if ($resolvedDoc === null) {
            return PreviewResult::invalid(['this document could not be routed for preview — check its slug and language against the rest of the site']);
        }

        $includedPages = array_values(array_filter(
            array_map(static fn (ResolvedDocument $document): ParsedDocument => $document->parsed, $site->documents),
            static fn (ParsedDocument $document): bool => $document->frontMatter instanceof PageFrontMatter
        ));

        $adapterFactory   = new RenderAdapterFactory($gateway, $frontMatterParser);
        $documentRenderer = new DocumentRenderer($adapterFactory);
        $rendered         = $documentRenderer->renderContent($resolvedDoc, $raw, $includedPages);

        $strings          = UiStringCatalogue::load($this->langDir, $this->config->languages);
        $dateFormatter    = new DateFormatter($strings);
        $templateResolver = new TemplateResolver($this->config->paths->templates, $this->config->templateSet);

        $fingerprinter = new AssetFingerprinter();
        [, $stylesheetUrl] = $fingerprinter->fingerprint(
            $this->templateAssetPath('style.css')
        );

        $templateStage = new SiteTemplateStage($this->config, $templateResolver, $strings, $dateFormatter, $stylesheetUrl);

        $identifier = $resolvedDoc->parsed->identifier();
        $files      = $templateStage->build($site, [$identifier => $rendered], [$identifier => true]);

        return PreviewResult::rendered(PreviewBanner::inject($files[$identifier]->html));
    }

    private function templateAssetPath(string $assetName): string
    {
        $base = rtrim($this->config->paths->templates, '/');
        $setPath = $base . '/' . $this->config->templateSet . '/' . $assetName;

        if ($this->config->templateSet !== '' && $this->config->templateSet !== 'default' && is_file($setPath)) {
            return $setPath;
        }

        return $base . '/' . $assetName;
    }

    /**
     * @param  list<ParsedDocument> $corpus
     * @return list<ParsedDocument>
     */
    private function spliceIn(array $corpus, ParsedDocument $previewed): array
    {
        $spliced  = [];
        $replaced = false;

        foreach ($corpus as $document) {
            if ($document->discovered->relativePath === $previewed->discovered->relativePath) {
                $spliced[] = $previewed;
                $replaced  = true;

                continue;
            }

            $spliced[] = $document;
        }

        if (!$replaced) {
            $spliced[] = $previewed;
        }

        return $spliced;
    }

    private function forcePublished(PostFrontMatter|PageFrontMatter $frontMatter): PostFrontMatter|PageFrontMatter
    {
        $shared     = $frontMatter->shared;
        $published  = new SharedFrontMatter(
            $shared->title,
            $shared->slug,
            DocumentStatus::Published,
            $shared->summary,
            $shared->translationKey,
            $shared->updated,
            $shared->image,
            $shared->imageAlt,
            $shared->canonical,
            $shared->noindex,
            $shared->aliases,
            $shared->toc,
            $shared->sourceId,
        );

        if ($frontMatter instanceof PostFrontMatter) {
            return new PostFrontMatter($published, $frontMatter->date, $frontMatter->tags, $frontMatter->series, $frontMatter->body);
        }

        return new PageFrontMatter(
            $published,
            $frontMatter->template,
            $frontMatter->navLabel,
            $frontMatter->navOrder,
            $frontMatter->navParent,
            $frontMatter->navGroup,
            $frontMatter->sitemapPriority,
            $frontMatter->legal,
            $frontMatter->body,
        );
    }
}
