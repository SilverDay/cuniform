<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;

/**
 * Stage 8 — Verify (SPEC §10.3). Runs after everything else has been
 * built (in memory — see BuildPipeline) and before anything is written:
 * "No deploy if" any of §10.3's conditions fire. Some of §10.3's bullets
 * are already enforced earlier in the pipeline and aren't repeated here —
 * an alias shadowing a real route and a reserved slug being claimed both
 * abort during stage 4 (SiteResolver, T19), hreflang symmetry is checked
 * there too (HreflangSymmetryChecker), and a missing UI string key aborts
 * when the catalogue loads (UiStringCatalogue, T13) — this class only
 * covers the conditions nothing upstream already guarantees: well-
 * formedness, internal links / referenced media resolving, the page-count
 * drop, the URL-scheme-change guard, and redirect targets resolving.
 */
final class BuildVerifier
{
    public function __construct(
        private readonly Config $config,
        private readonly string $releasesRoot,
        private readonly string $urlSchemeMetaPath,
    ) {
    }

    /**
     * @param  list<GeneratedFile>  $pages
     * @param  list<ArtifactFile>   $artifacts
     * @param  list<string>         $mediaPaths Release-relative, from MediaCopier::list().
     * @param  list<RedirectEntry>  $redirects
     * @return list<string> Warnings — non-fatal conditions worth surfacing.
     *
     * @throws BuildException Collecting every blocking condition together.
     */
    public function verify(array $pages, array $artifacts, array $mediaPaths, array $redirects, bool $allowUrlSchemeChange): array
    {
        $errors   = [];
        $warnings = [];

        $knownPaths = $this->knownReleasePaths($pages, $artifacts, $mediaPaths);

        $xmlArtifacts = array_values(array_filter(
            $artifacts,
            static fn (ArtifactFile $a): bool => str_ends_with($a->relativePath, '.xml')
        ));
        $errors = [...$errors, ...(new WellFormednessChecker())->check($pages, $xmlArtifacts)];

        $errors = [...$errors, ...(new InternalLinkChecker($this->config->baseUrl))->check($pages, $knownPaths)];

        $errors = [...$errors, ...(new RedirectTargetChecker())->check($redirects, $knownPaths)];

        $pageCountViolation = (new PageCountGuard($this->releasesRoot))
            ->check(count($pages), $this->config->build->pageCountDropThreshold);
        if ($pageCountViolation !== null) {
            $errors[] = $pageCountViolation;
        }

        $urlSchemeViolation = (new UrlSchemeGuard($this->urlSchemeMetaPath))->check($this->config, $allowUrlSchemeChange);
        if ($urlSchemeViolation !== null) {
            $errors[] = $urlSchemeViolation;
        }

        if ($errors !== []) {
            throw BuildException::fromErrors($errors);
        }

        return $warnings;
    }

    /**
     * @param  list<GeneratedFile> $pages
     * @param  list<ArtifactFile>  $artifacts
     * @param  list<string>        $mediaPaths
     * @return array<string, true>
     */
    private function knownReleasePaths(array $pages, array $artifacts, array $mediaPaths): array
    {
        $known = [];

        foreach ($pages as $page) {
            $known[$page->relativeFilePath()] = true;
        }

        foreach ($artifacts as $artifact) {
            $known[$artifact->relativePath] = true;
        }

        foreach ($mediaPaths as $mediaPath) {
            $known[$mediaPath] = true;
        }

        return $known;
    }
}
