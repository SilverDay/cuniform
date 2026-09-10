<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\Content\FilesystemGateway;
use Cuniform\Content\FrontMatter\FrontMatterParser;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\I18n\DateFormatter;
use Cuniform\I18n\UiStringCatalogue;
use Cuniform\Render\RenderedDocument;
use Cuniform\Template\TemplateResolver;

/**
 * Orchestrates stages 1-9 (SPEC §10.1: Lock, Discover, Parse, Resolve,
 * Render, Template — SiteTemplateStage renders one page per ResolvedDocument,
 * ListingTemplateStage renders the generated-listing routes (index/tag/
 * series/archive/search/404) from the aggregated ListingSet ListingResolver
 * builds, T17; Emit — feeds/sitemap/search-index/robots/security.txt/assets
 * via ArtifactStage, T20; the compiled redirects.conf via
 * RedirectMapCompiler/RedirectMapGenerator, T21; media copying and Verify
 * via MediaCopier/BuildVerifier, T22; the atomic deploy and release
 * pruning via ReleaseDeployer, T23 — Emit and redirect compilation are
 * both stage 7's job per §10.1's own listing, split into separate classes
 * only along BUILD-ORDER's task boundary). Verify runs whether or not
 * `--dry-run` was given — a dry run's whole point is telling you whether a
 * real build *would* succeed. A `--dry-run` build never reaches Deploy: a
 * real (non-dry-run) build writes a complete release tree under
 * `paths.releases/<timestamp>/`, verifies it, then atomically swaps
 * `public/` onto it.
 *
 * Incremental builds (T24, SPEC §10.2): Discover/Parse/Resolve/Listing
 * always run in full for every document — they're cheap, and Resolve's
 * routes/nav/hreflang/listing aggregation are only meaningful computed
 * for the whole corpus at once anyway. What IncrementalPlanner (via
 * BuildCache/BuildCacheKey) actually gates is the expensive per-document
 * work: Render (Markdown + shortcode conversion) and that document's own
 * Template step. A document outside the dirty set reuses its previous
 * build's rendered body (for feeds/search-index, which still read every
 * document's body every build — see ArtifactStage) and its previously
 * assembled page bytes directly, skipping both steps entirely. Verify
 * still runs against the *complete* page set, cached pages included —
 * deliberately not cached itself, since a cached page's own bytes being
 * unchanged says nothing about whether something it links to still
 * exists in *this* release.
 */
final class BuildPipeline
{
    public function __construct(
        private readonly Config $config,
        private readonly string $langDir,
    ) {
    }

    /**
     * @throws BuildException When stage 1's lock is already held, or stage 4
     *                        collects any route/hreflang/alias/image error.
     */
    public function run(BuildOptions $options): BuildResult
    {
        $lock = new BuildLock(rtrim($this->config->paths->var, '/') . '/build.lock');
        $lock->acquire();

        try {
            return $this->runLocked($options);
        } finally {
            $lock->release();
        }
    }

    private function runLocked(BuildOptions $options): BuildResult
    {
        $gateway           = new FilesystemGateway($this->config->paths->content, $this->config->build->maxDocumentBytes);
        $frontMatterParser = new FrontMatterParser($this->config->timezone);

        $discoverer = new ContentDiscoverer($this->config->paths->content, $this->config->languages);
        $discovered = $discoverer->discover();

        $parser = new DocumentParser($gateway, $frontMatterParser);
        $parsed = $parser->parse($discovered);

        $now = new \DateTimeImmutable();

        $resolver = new SiteResolver($this->config);
        $site     = $resolver->resolve($parsed, $now);

        $includedPages = array_values(array_filter(
            array_map(static fn (ResolvedDocument $document): ParsedDocument => $document->parsed, $site->documents),
            static fn (ParsedDocument $document): bool => $document->frontMatter instanceof PageFrontMatter
        ));

        $strings       = UiStringCatalogue::load($this->langDir, $this->config->languages);
        $dateFormatter = new DateFormatter($strings);
        $listing       = (new ListingResolver($this->config, $dateFormatter))->resolve($site);

        $buildCache       = new BuildCache(rtrim($this->config->paths->var, '/') . '/build-cache.json');
        $previousManifest = $buildCache->load();
        $cacheKey          = new BuildCacheKey($this->config, $this->config->paths->templates, $this->langDir);
        $navHash           = $cacheKey->navHash($site->navByLanguage);
        $plan              = (new IncrementalPlanner())->plan($site->documents, $previousManifest, $navHash, $cacheKey, $options->full);

        $adapterFactory   = new RenderAdapterFactory($gateway, $frontMatterParser);
        $documentRenderer = new DocumentRenderer($adapterFactory);

        $renderedByIdentifier = [];
        $dirty                = [];
        foreach ($site->documents as $document) {
            $identifier = $document->parsed->identifier();

            if (!$plan->isDirty($identifier)) {
                $cached = $previousManifest->documents[$identifier];
                $renderedByIdentifier[$identifier] = new RenderedDocument($document->parsed->frontMatter, $cached->bodyHtml, $cached->headings);

                continue;
            }

            $dirty[$identifier]                = true;
            $renderedByIdentifier[$identifier]  = $documentRenderer->render($document, $includedPages);
        }

        $artifacts = (new ArtifactStage($this->config))->build($site, $listing, $renderedByIdentifier, $now);

        $manualRedirects = (new RedirectMapParser())->parse(rtrim($this->config->paths->content, '/') . '/redirects.map');
        $redirects       = (new RedirectMapCompiler())->compile($site, $manualRedirects);
        $redirectsFile   = (new RedirectMapGenerator($this->config))->generate($redirects['entries']);

        $templateResolver = new TemplateResolver($this->config->paths->templates, $this->config->templateSet);
        $templateStage    = new SiteTemplateStage(
            $this->config,
            $templateResolver,
            $strings,
            $dateFormatter,
            $artifacts['stylesheetUrl'],
        );
        $listingStage = new ListingTemplateStage(
            $this->config,
            $templateResolver,
            $strings,
            $artifacts['stylesheetUrl'],
            $artifacts['searchScriptUrl'],
        );

        // Every template renders to a string here, entirely in memory, before
        // anything is written to disk — a template error propagates from
        // build() with zero filesystem side effects (SPEC §9 rule 6).
        $freshPages = $templateStage->build($site, $renderedByIdentifier, $dirty);

        $pagesByIdentifier = [];
        foreach ($site->documents as $document) {
            $identifier = $document->parsed->identifier();
            $pagesByIdentifier[$identifier] = $freshPages[$identifier]
                ?? new GeneratedFile($document->url, $previousManifest->documents[$identifier]->pageHtml);
        }

        $pages = [
            ...array_values($pagesByIdentifier),
            ...$listingStage->build($listing, $site->navByLanguage),
        ];

        $allFiles    = [...$artifacts['files'], $redirectsFile];
        $mediaCopier = new MediaCopier($this->config->paths->content);
        $mediaPaths  = $mediaCopier->list();

        $urlSchemeMetaPath = rtrim($this->config->paths->var, '/') . '/last-build-meta.json';
        $verifier          = new BuildVerifier($this->config, $this->config->paths->releases, $urlSchemeMetaPath);

        // Verify runs against everything already built in memory, before a
        // single byte is written — a failing condition here leaves the
        // filesystem exactly as it was before this build started.
        $verifyWarnings = $verifier->verify($pages, $allFiles, $mediaPaths, $redirects['entries'], $options->allowUrlSchemeChange);

        $warnings = [...$site->warnings, ...$artifacts['warnings'], ...$redirects['warnings'], ...$verifyWarnings];

        $releaseDir = null;
        if (!$options->dryRun) {
            $releaseDir = $this->writeRelease($pages, $allFiles);
            $mediaCopier->copyInto($releaseDir);

            $deployer = new ReleaseDeployer(
                $this->config->paths->public,
                $this->config->paths->releases,
                $this->config->build->retainReleases,
            );
            $deployer->deploy($releaseDir);

            // Only a build that actually deployed becomes the new baseline
            // the next build's UrlSchemeGuard compares against — a build
            // whose deploy step fails must leave the stored baseline
            // matching whatever is still actually live.
            (new UrlSchemeGuard($urlSchemeMetaPath))->persist($this->config);

            // Same reasoning for the incremental cache: only a build that
            // actually deployed becomes the baseline a later build's
            // IncrementalPlanner compares against.
            $newDocuments = [];
            foreach ($site->documents as $document) {
                $identifier = $document->parsed->identifier();
                $newDocuments[$identifier] = new CachedDocument(
                    $plan->baseKeyByIdentifier[$identifier],
                    $document->parsed->frontMatter->shared->translationKey,
                    $renderedByIdentifier[$identifier]->bodyHtml,
                    $renderedByIdentifier[$identifier]->headings,
                    $pagesByIdentifier[$identifier]->html,
                    $document->url,
                );
            }
            $buildCache->save(new CacheManifest($navHash, $newDocuments));
        }

        $reusedDocumentCount = count($site->documents) - count($dirty);

        $documentCountByLanguage = [];
        foreach ($site->documents as $document) {
            $language = $document->parsed->discovered->language;
            $documentCountByLanguage[$language] = ($documentCountByLanguage[$language] ?? 0) + 1;
        }

        return new BuildResult(count($site->documents), count($pages), $warnings, $releaseDir, $reusedDocumentCount, $documentCountByLanguage);
    }

    /**
     * @param list<GeneratedFile> $pages
     * @param list<ArtifactFile>  $artifacts
     */
    private function writeRelease(array $pages, array $artifacts): string
    {
        $releaseDir = rtrim($this->config->paths->releases, '/') . '/' . (new \DateTimeImmutable())->format('YmdHis');

        $this->makeDirectory($releaseDir);

        foreach ($pages as $page) {
            $this->writeFile($releaseDir, $page->relativeFilePath(), $page->html);
        }

        foreach ($artifacts as $artifact) {
            $this->writeFile($releaseDir, $artifact->relativePath, $artifact->contents);
        }

        return $releaseDir;
    }

    private function writeFile(string $releaseDir, string $relativePath, string $contents): void
    {
        $target = $releaseDir . '/' . $relativePath;
        $this->makeDirectory(dirname($target));

        if (file_put_contents($target, $contents) === false) {
            throw BuildException::fromErrors(["could not write file: {$target}"]);
        }
    }

    private function makeDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0o755, true) && !is_dir($path)) {
            throw BuildException::fromErrors(["could not create directory: {$path}"]);
        }
    }
}
