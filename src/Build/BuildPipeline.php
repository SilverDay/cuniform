<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\Content\FilesystemGateway;
use Cuniform\Content\FrontMatter\FrontMatterParser;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\I18n\DateFormatter;
use Cuniform\I18n\UiStringCatalogue;
use Cuniform\Template\TemplateResolver;

/**
 * Orchestrates stages 1-7 (SPEC §10.1: Lock, Discover, Parse, Resolve,
 * Render, Template, Emit). Stages 8-9 — Verify and the atomic deploy — are
 * T22-T23 and do not happen here: a real (non-dry-run) build writes a
 * complete release tree under `paths.releases/<timestamp>/`, but never
 * touches `public/`.
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

        $adapterFactory    = new RenderAdapterFactory($gateway, $frontMatterParser);
        $documentRenderer  = new DocumentRenderer($adapterFactory);

        $renderedByIdentifier = [];
        foreach ($site->documents as $document) {
            $renderedByIdentifier[$document->parsed->identifier()] = $documentRenderer->render($document, $includedPages);
        }

        $artifacts = (new ArtifactStage($this->config))->build($site, $renderedByIdentifier, $now);

        $strings          = UiStringCatalogue::load($this->langDir, $this->config->languages);
        $dateFormatter    = new DateFormatter($strings);
        $templateResolver = new TemplateResolver($this->config->paths->templates);
        $templateStage    = new SiteTemplateStage(
            $this->config,
            $templateResolver,
            $strings,
            $dateFormatter,
            $artifacts['stylesheetUrl'],
        );

        // Every template renders to a string here, entirely in memory, before
        // anything is written to disk — a template error propagates from
        // build() with zero filesystem side effects (SPEC §9 rule 6).
        $pages = $templateStage->build($site, $renderedByIdentifier);

        $warnings = [...$site->warnings, ...$artifacts['warnings']];

        $releaseDir = $options->dryRun ? null : $this->writeRelease($pages, $artifacts['files']);

        return new BuildResult(count($site->documents), count($pages), $warnings, $releaseDir);
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
