<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Routing\RouteBuilder;

/**
 * One `sitemap.xml` covering every language (SPEC §11.2), each URL carrying
 * `xhtml:link` alternates for its translations (the same HreflangSet stage 4
 * already built — no second hreflang computation here). Excludes `noindex`
 * documents; drafts and not-yet-due scheduled documents are already absent
 * from ResolvedSite (SPEC §5.6).
 *
 * Also includes the generated listing pages T17 adds — the home index,
 * each tag archive, each series index, each year archive — but only their
 * first page: "excludes ... pagination beyond page 1" (§11.2) only makes
 * sense once something *has* pagination beyond page 1, which is what this
 * sentence was written for. Listing pages carry no `xhtml:link` alternates
 * of their own: they aren't a translated document with a `translation_key`
 * (SPEC §7.3), so there's no natural alternate to advertise — same
 * reasoning as ListingTemplateStage's ViewModels always carrying a null
 * hreflang. The search page and 404s are deliberately excluded — neither
 * is content a search engine should index.
 */
final class SitemapGenerator
{
    private const NAMESPACE_SITEMAP = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    private const NAMESPACE_XHTML   = 'http://www.w3.org/1999/xhtml';

    private readonly RouteBuilder $routeBuilder;

    public function __construct(private readonly Config $config)
    {
        $this->routeBuilder = new RouteBuilder($config->urlPrefix, $config->languages, $config->permalink);
    }

    public function generate(ResolvedSite $site, ListingSet $listing): ArtifactFile
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $urlset = $dom->createElementNS(self::NAMESPACE_SITEMAP, 'urlset');
        $urlset->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xhtml', self::NAMESPACE_XHTML);
        $dom->appendChild($urlset);

        foreach ($site->documents as $document) {
            if ($document->parsed->frontMatter->shared->noindex) {
                continue;
            }

            $urlset->appendChild($this->urlElement($dom, $document));
        }

        foreach ($this->listingUrls($listing) as $url) {
            $urlset->appendChild($this->bareUrlElement($dom, $url));
        }

        return new ArtifactFile('sitemap.xml', (string) $dom->saveXML());
    }

    /**
     * @return list<string> route paths, page 1 only
     */
    private function listingUrls(ListingSet $listing): array
    {
        $urls = [];

        foreach ($this->config->languages as $language) {
            $urls[] = $this->routeBuilder->indexRoute($language, 1);

            foreach ($listing->tagsByLanguage[$language] ?? [] as $tag) {
                $urls[] = $this->routeBuilder->tagRoute($language, $tag->slug, 1);
            }

            foreach ($listing->seriesByLanguage[$language] ?? [] as $series) {
                $urls[] = $this->routeBuilder->seriesRoute($language, $series->slug);
            }

            foreach ($listing->yearsByLanguage[$language] ?? [] as $year) {
                $urls[] = $this->routeBuilder->archiveRoute($language, $year->year);
            }
        }

        return $urls;
    }

    private function bareUrlElement(\DOMDocument $dom, string $routePath): \DOMElement
    {
        $url = $dom->createElement('url');
        $url->appendChild($dom->createElement('loc', $this->absoluteUrl($routePath)));

        return $url;
    }

    private function urlElement(\DOMDocument $dom, ResolvedDocument $document): \DOMElement
    {
        $url = $dom->createElement('url');
        $url->appendChild($dom->createElement('loc', $this->absoluteUrl($document->url)));

        $lastmod = $this->lastmod($document);
        if ($lastmod !== null) {
            $url->appendChild($dom->createElement('lastmod', $lastmod));
        }

        if ($document->hreflang !== null) {
            foreach ($document->hreflang->alternates as $alternate) {
                $link = $dom->createElementNS(self::NAMESPACE_XHTML, 'xhtml:link');
                $link->setAttribute('rel', 'alternate');
                $link->setAttribute('hreflang', $alternate->hreflang);
                $link->setAttribute('href', $this->absoluteUrl($alternate->url, false));
                $url->appendChild($link);
            }
        }

        return $url;
    }

    private function lastmod(ResolvedDocument $document): ?string
    {
        $shared = $document->parsed->frontMatter->shared;
        if ($shared->updated !== null) {
            return $shared->updated->format('Y-m-d');
        }

        $frontMatter = $document->parsed->frontMatter;
        if ($frontMatter instanceof PostFrontMatter) {
            return $frontMatter->date->format('Y-m-d');
        }

        return null;
    }

    /**
     * @param bool $isRoutePath Whether $path is a bare route (needs the base
     *                          URL prefixed) or already absolute (an
     *                          hreflang alternate's URL, already absolute —
     *                          see HreflangSetBuilder).
     */
    private function absoluteUrl(string $path, bool $isRoutePath = true): string
    {
        return $isRoutePath ? rtrim($this->config->baseUrl, '/') . $path : $path;
    }
}
