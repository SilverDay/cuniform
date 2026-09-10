<?php

declare(strict_types=1);

namespace Cuniform\Admin\Reporting;

use Cuniform\Build\ContentDiscoverer;
use Cuniform\Build\DocumentParser;
use Cuniform\Build\ListingResolver;
use Cuniform\Build\RedirectMapCompiler;
use Cuniform\Build\RedirectMapParser;
use Cuniform\Build\SiteResolver;
use Cuniform\Config\Config;
use Cuniform\Content\FilesystemGateway;
use Cuniform\Content\FrontMatter\FrontMatterParser;
use Cuniform\I18n\DateFormatter;
use Cuniform\I18n\UiStringCatalogue;

/**
 * The page-list nav tree, the tags/series-per-language listing, and the
 * redirects listing (SPEC §13.3) all need the same thing a real build's
 * Resolve stage already computes — routes, hreflang-relevant status, nav
 * placement — so this runs the identical stages BuildPipeline itself calls
 * (ContentDiscoverer/DocumentParser, SiteResolver, ListingResolver,
 * RedirectMapCompiler) rather than a parallel reimplementation, the same
 * reuse principle PreviewRenderer (T30) already established for its own
 * chain. Nothing here renders a template, writes a release, or touches
 * `releases/`/`public` — Render/Template/Emit/Verify/Deploy never run;
 * this stops right after stage 4 (Resolve) plus the two stage-7 pieces
 * (Listing, Redirects) these three screens specifically need.
 *
 * Reflects exactly what a real build would currently produce: a draft or
 * not-yet-due scheduled document is excluded from the nav tree and every
 * archive here, same as SiteResolver already excludes it from a release
 * (SPEC §5.6) — this is "what's live," not "everything in content/". The
 * dashboard's draft/untranslated counts, which *do* need every document
 * regardless of status, read DocumentIndex instead (see admin/index.php).
 */
final class AdminSiteReport
{
    public function __construct(
        private readonly Config $config,
        private readonly string $langDir,
    ) {
    }

    /**
     * @throws \Cuniform\CuniformException When the corpus itself has a
     *                                      build-blocking error (SPEC
     *                                      §5.5/§10.3) — the same
     *                                      conditions that would fail a
     *                                      real build.
     */
    public function build(): AdminSiteReportResult
    {
        $gateway           = new FilesystemGateway($this->config->paths->content, $this->config->build->maxDocumentBytes);
        $frontMatterParser = new FrontMatterParser($this->config->timezone);

        $discoverer = new ContentDiscoverer($this->config->paths->content, $this->config->languages);
        $parser     = new DocumentParser($gateway, $frontMatterParser);
        $parsed     = $parser->parse($discoverer->discover());

        $site = (new SiteResolver($this->config))->resolve($parsed, new \DateTimeImmutable());

        $strings       = UiStringCatalogue::load($this->langDir, $this->config->languages);
        $dateFormatter = new DateFormatter($strings);
        $listing       = (new ListingResolver($this->config, $dateFormatter))->resolve($site);

        $manualRedirects = (new RedirectMapParser())->parse(rtrim($this->config->paths->content, '/') . '/redirects.map');
        $redirects       = (new RedirectMapCompiler())->compile($site, $manualRedirects);

        return new AdminSiteReportResult(
            $site->navByLanguage,
            $listing,
            $redirects['entries'],
            [...$site->warnings, ...$redirects['warnings']],
        );
    }
}
