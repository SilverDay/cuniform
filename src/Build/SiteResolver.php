<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\Content\ContentException;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Content\NavCandidate;
use Cuniform\Content\NavTreeBuilder;
use Cuniform\Content\PagePathResolver;
use Cuniform\Content\Translation\TranslationCandidate;
use Cuniform\Content\Translation\TranslationGroup;
use Cuniform\Content\Translation\TranslationGrouper;
use Cuniform\I18n\HreflangSet;
use Cuniform\I18n\HreflangSetBuilder;
use Cuniform\I18n\HreflangSymmetryChecker;
use Cuniform\I18n\TranslatedDocument;
use Cuniform\Routing\RouteBuilder;
use Cuniform\Routing\RouteTable;

/**
 * Stage 4 — Resolve (SPEC §10.1): routes, the namespace-collision check,
 * translation groups, per-language nav trees, and hreflang sets — everything
 * a document needs before it can be rendered and templated, computed for the
 * whole corpus at once because several of these (route uniqueness,
 * translation grouping, hreflang symmetry) are only meaningful site-wide.
 *
 * A draft is excluded outright (SPEC §5.6 — never rendered into a release).
 * A scheduled *post* is excluded until its `date` is at or before $now.
 * A scheduled *page* has no `date` field to be due against (§5.3's `date`
 * key is post-only) — SPEC doesn't say what "scheduled" means without one,
 * so this treats it the same as draft: excluded, since there is no signal
 * that it has become due. Worth raising if a real use case for it shows up.
 */
final class SiteResolver
{
    private readonly RouteBuilder $routeBuilder;
    private readonly PagePathResolver $pagePathResolver;
    private readonly TranslationGrouper $translationGrouper;
    private readonly NavTreeBuilder $navTreeBuilder;
    private readonly HreflangSetBuilder $hreflangSetBuilder;
    private readonly HreflangSymmetryChecker $hreflangSymmetryChecker;

    public function __construct(private readonly Config $config)
    {
        $this->routeBuilder      = new RouteBuilder($config->urlPrefix, $config->languages, $config->permalink);
        $this->pagePathResolver  = new PagePathResolver();
        $this->translationGrouper = new TranslationGrouper();
        $this->navTreeBuilder     = new NavTreeBuilder();

        $defaultHome = rtrim($config->baseUrl, '/') . '/' . $this->routeBuilder->prefixFor($config->defaultLanguage);
        $this->hreflangSetBuilder      = new HreflangSetBuilder($config->languages, $defaultHome);
        $this->hreflangSymmetryChecker = new HreflangSymmetryChecker();
    }

    /**
     * @param list<ParsedDocument> $parsed
     *
     * @throws BuildException When any route collides, an alias shadows a real
     *                        route, an image doesn't resolve, or hreflang
     *                        comes out asymmetric.
     * @throws ContentException When a translation_key is duplicated within
     *                          one language.
     */
    public function resolve(array $parsed, \DateTimeImmutable $now): ResolvedSite
    {
        $errors   = [];
        $warnings = [];

        $included = array_values(array_filter(
            $parsed,
            fn (ParsedDocument $document): bool => $this->isIncluded($document, $now)
        ));

        $routeTable = new RouteTable($this->config->languages);
        /** @var array<string, string> $urlByIdentifier */
        $urlByIdentifier = [];

        foreach ($included as $document) {
            [$path, $firstSegment] = $this->routeFor($document);

            try {
                $routeTable->register($path, $firstSegment, $document->identifier());
            } catch (BuildException $e) {
                $errors[] = $e->getMessage();

                continue;
            }

            $urlByIdentifier[$document->identifier()] = $path;
        }

        /** @var array<string, DocumentStatus> $statusByIdentifier */
        $statusByIdentifier = [];
        foreach ($parsed as $document) {
            $statusByIdentifier[$document->identifier()] = $document->frontMatter->shared->status;
        }

        $groups = $this->groupTranslations($parsed, $errors);

        foreach ($included as $document) {
            $image = $document->frontMatter->shared->image;
            if ($image !== null && !$this->imageResolves($image)) {
                $errors[] = BuildException::imageDoesNotResolve($document->identifier(), $image)->getMessage();
            }

            foreach ($document->frontMatter->shared->aliases as $alias) {
                if ($routeTable->has($alias)) {
                    $errors[] = BuildException::aliasCollidesWithRoute($document->identifier(), $alias)->getMessage();
                }
            }
        }

        /** @var array<string, HreflangSet> $hreflangByUrl */
        $hreflangByUrl = [];
        $resolved      = [];

        foreach ($included as $document) {
            $url      = $urlByIdentifier[$document->identifier()] ?? null;
            if ($url === null) {
                // Registration failed above (collision/reserved slug) — already reported.
                continue;
            }

            $hreflang = $this->buildHreflang($document, $url, $groups, $urlByIdentifier, $statusByIdentifier);
            if ($hreflang !== null) {
                $hreflangByUrl[$url] = $hreflang;
            }

            $resolved[] = new ResolvedDocument($document, $url, $hreflang);
        }

        try {
            $this->hreflangSymmetryChecker->assertSymmetric($hreflangByUrl);
        } catch (BuildException $e) {
            $errors[] = $e->getMessage();
        }

        $navByLanguage = [];
        foreach ($this->config->languages as $language) {
            $candidates = $this->navCandidatesFor($included, $language, $urlByIdentifier);

            try {
                $nav = $this->navTreeBuilder->build($candidates);
            } catch (ContentException $e) {
                $errors[] = $e->getMessage();

                continue;
            }

            $navByLanguage[$language] = ['primary' => $nav['primary'], 'footer' => $nav['footer']];
            $warnings                 = [...$warnings, ...$nav['warnings']];
        }

        if ($errors !== []) {
            throw BuildException::fromErrors($errors);
        }

        return new ResolvedSite($resolved, $navByLanguage, $warnings);
    }

    private function isIncluded(ParsedDocument $document, \DateTimeImmutable $now): bool
    {
        $status = $document->frontMatter->shared->status;

        return match ($status) {
            DocumentStatus::Published => true,
            DocumentStatus::Draft => false,
            DocumentStatus::Scheduled => $document->frontMatter instanceof PostFrontMatter
                && $document->frontMatter->date <= $now,
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function routeFor(ParsedDocument $document): array
    {
        $language = $document->discovered->language;
        $slug     = $document->frontMatter->shared->slug;

        if ($document->frontMatter instanceof PostFrontMatter) {
            return $this->routeBuilder->postRoute($language, $slug, $document->frontMatter->date);
        }

        $path = $this->pagePathResolver->resolve($document->discovered->relativePath, $slug);

        return $this->routeBuilder->pageRoute($language, $path);
    }

    private function imageResolves(string $image): bool
    {
        $target = rtrim($this->config->paths->content, '/') . '/' . ltrim($image, '/');
        $real   = realpath($target);
        $root   = realpath($this->config->paths->content);

        if ($real === false || $root === false || !is_file($real)) {
            return false;
        }

        $rootWithSlash = rtrim($root, '/') . '/';

        return strncmp($real, $rootWithSlash, strlen($rootWithSlash)) === 0;
    }

    /**
     * @param  list<ParsedDocument> $parsed
     * @param  list<string>         $errors
     * @return list<TranslationGroup>
     */
    private function groupTranslations(array $parsed, array &$errors): array
    {
        $candidates = array_map(
            static fn (ParsedDocument $document): TranslationCandidate => new TranslationCandidate(
                $document->discovered->language,
                $document->frontMatter->shared->translationKey,
                $document->identifier(),
            ),
            $parsed
        );

        try {
            return $this->translationGrouper->group($candidates);
        } catch (ContentException $e) {
            $errors[] = $e->getMessage();

            return [];
        }
    }

    /**
     * @param  list<TranslationGroup>       $groups
     * @param  array<string, string>        $urlByIdentifier
     * @param  array<string, DocumentStatus> $statusByIdentifier
     */
    private function buildHreflang(
        ParsedDocument $document,
        string $url,
        array $groups,
        array $urlByIdentifier,
        array $statusByIdentifier
    ): ?HreflangSet {
        $language = $document->discovered->language;
        $current  = new TranslatedDocument($language, $url, $document->frontMatter->shared->status);

        $key = $document->frontMatter->shared->translationKey;
        if ($key === null) {
            return $this->hreflangSetBuilder->build($current, []);
        }

        $group = null;
        foreach ($groups as $candidate) {
            if ($candidate->translationKey === $key) {
                $group = $candidate;

                break;
            }
        }

        if ($group === null) {
            return $this->hreflangSetBuilder->build($current, []);
        }

        $others = [];
        foreach ($group->identifiersByLanguage as $otherLanguage => $identifier) {
            if ($otherLanguage === $language) {
                continue;
            }

            $status = $statusByIdentifier[$identifier] ?? null;
            $otherUrl = $urlByIdentifier[$identifier] ?? null;
            if ($status === null || $otherUrl === null) {
                continue;
            }

            $others[] = new TranslatedDocument($otherLanguage, $otherUrl, $status);
        }

        return $this->hreflangSetBuilder->build($current, $others);
    }

    /**
     * @param  list<ParsedDocument>  $included
     * @param  array<string, string> $urlByIdentifier
     * @return list<NavCandidate>
     */
    private function navCandidatesFor(array $included, string $language, array $urlByIdentifier): array
    {
        $candidates = [];

        foreach ($included as $document) {
            if ($document->discovered->language !== $language) {
                continue;
            }

            $frontMatter = $document->frontMatter;
            if (!$frontMatter instanceof PageFrontMatter) {
                continue;
            }

            $url = $urlByIdentifier[$document->identifier()] ?? null;
            if ($url === null) {
                continue;
            }

            $path = $this->pagePathResolver->resolve($document->discovered->relativePath, $frontMatter->shared->slug);

            $candidates[] = new NavCandidate(
                $language,
                $path,
                $url,
                $frontMatter->navLabel ?? $frontMatter->shared->title,
                $frontMatter->navOrder,
                $frontMatter->navParent,
                $frontMatter->navGroup,
            );
        }

        return $candidates;
    }
}
