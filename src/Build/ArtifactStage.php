<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\Render\RenderedDocument;

/**
 * Stage 7 — Emit (SPEC §10.1, §11): feeds, the sitemap, the search index,
 * robots.txt, security.txt, and the fingerprinted stylesheet. Orchestrates
 * the individual generators rather than doing the work itself — each one is
 * independently testable against a ResolvedSite/rendered-document map.
 */
final class ArtifactStage
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param  array<string, RenderedDocument> $renderedByIdentifier
     * @return array{files: list<ArtifactFile>, warnings: list<string>, stylesheetUrl: string}
     */
    public function build(ResolvedSite $site, array $renderedByIdentifier, \DateTimeImmutable $now): array
    {
        $files    = [];
        $warnings = [];

        foreach ((new FeedGenerator($this->config))->generate($site, $renderedByIdentifier) as $feedFile) {
            $files[] = $feedFile;
        }

        $files[] = (new SitemapGenerator($this->config))->generate($site);

        [$searchIndexFile, $searchIndexWarnings] = (new SearchIndexGenerator($this->config))->generate($site, $renderedByIdentifier);
        $files[]  = $searchIndexFile;
        $warnings = [...$warnings, ...$searchIndexWarnings];

        $files[] = (new RobotsTxtGenerator($this->config))->generate();
        $files[] = (new SecurityTxtGenerator($this->config))->generate($now);

        $stylesheetSource = rtrim($this->config->paths->templates, '/') . '/style.css';
        [$cssFile, $stylesheetUrl] = (new AssetFingerprinter())->fingerprint($stylesheetSource);
        $files[] = $cssFile;

        return ['files' => $files, 'warnings' => $warnings, 'stylesheetUrl' => $stylesheetUrl];
    }
}
