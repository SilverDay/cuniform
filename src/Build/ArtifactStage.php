<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\Render\RenderedDocument;

/**
 * Stage 7 — Emit (SPEC §10.1, §11): feeds, the sitemap, the search index,
 * robots.txt, security.txt, and the fingerprinted static assets
 * (stylesheet, client-side search script). Orchestrates the individual
 * generators rather than doing the work itself — each one is
 * independently testable against a ResolvedSite/rendered-document map.
 */
final class ArtifactStage
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param  array<string, RenderedDocument> $renderedByIdentifier
     * @return array{files: list<ArtifactFile>, warnings: list<string>, stylesheetUrl: string, searchScriptUrl: string}
     */
    public function build(ResolvedSite $site, ListingSet $listing, array $renderedByIdentifier, \DateTimeImmutable $now): array
    {
        $files    = [];
        $warnings = [];

        foreach ((new FeedGenerator($this->config))->generate($site, $renderedByIdentifier) as $feedFile) {
            $files[] = $feedFile;
        }

        $files[] = (new SitemapGenerator($this->config))->generate($site, $listing);

        [$searchIndexFile, $searchIndexWarnings] = (new SearchIndexGenerator($this->config))->generate($site, $renderedByIdentifier);
        $files[]  = $searchIndexFile;
        $warnings = [...$warnings, ...$searchIndexWarnings];

        $files[] = (new RobotsTxtGenerator($this->config))->generate();
        $files[] = (new SecurityTxtGenerator($this->config))->generate($now);

        $fingerprinter = new AssetFingerprinter();
        $templateSet   = $this->config->templateSet;

        [$cssFile, $stylesheetUrl] = $fingerprinter->fingerprint($this->templateAssetPath('style.css'));
        $files[] = $cssFile;

        [$jsFile, $searchScriptUrl] = $fingerprinter->fingerprint($this->templateAssetPath('search.js'));
        $files[] = $jsFile;

        return [
            'files'           => $files,
            'warnings'        => $warnings,
            'stylesheetUrl'   => $stylesheetUrl,
            'searchScriptUrl' => $searchScriptUrl,
        ];
    }

    private function templateAssetPath(string $assetName): string
    {
        $base = rtrim($this->config->paths->templates, '/');
        $path = $base . '/' . $assetName;
        $setPath = $base . '/' . $this->config->templateSet . '/' . $assetName;

        if ($this->config->templateSet !== '' && $this->config->templateSet !== 'default' && is_file($setPath)) {
            return $setPath;
        }

        return $path;
    }
}
