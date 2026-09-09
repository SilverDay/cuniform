<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Render\RenderedDocument;
use Cuniform\Routing\RouteBuilder;

/**
 * Per-language RSS 2.0 and Atom feeds (SPEC §11.1): latest `feed_items`
 * posts, full content, relative URLs made absolute, a declared `<language>`.
 * Pages never appear — this only ever looks at PostFrontMatter documents.
 * GUIDs/ids are the post's own permalink, never the source file path.
 */
final class FeedGenerator
{
    private readonly RouteBuilder $routeBuilder;
    private readonly AbsoluteUrlRewriter $urlRewriter;

    public function __construct(private readonly Config $config)
    {
        $this->routeBuilder = new RouteBuilder($config->urlPrefix, $config->languages, $config->permalink);
        $this->urlRewriter  = new AbsoluteUrlRewriter();
    }

    /**
     * @param  array<string, RenderedDocument> $renderedByIdentifier
     * @return list<ArtifactFile>
     */
    public function generate(ResolvedSite $site, array $renderedByIdentifier): array
    {
        $files = [];

        foreach ($this->config->languages as $language) {
            $posts = $this->postsFor($site, $language);
            if ($posts === []) {
                continue;
            }

            $prefix = $this->routeBuilder->prefixFor($language);

            $files[] = new ArtifactFile("{$prefix}feed.xml", $this->rss($language, $posts, $renderedByIdentifier));
            $files[] = new ArtifactFile("{$prefix}atom.xml", $this->atom($language, $posts, $renderedByIdentifier));
        }

        return $files;
    }

    /**
     * @return list<ResolvedDocument>
     */
    private function postsFor(ResolvedSite $site, string $language): array
    {
        $posts = array_values(array_filter(
            $site->documents,
            fn (ResolvedDocument $document): bool => $document->parsed->discovered->language === $language
                && $document->parsed->frontMatter instanceof PostFrontMatter
        ));

        usort(
            $posts,
            fn (ResolvedDocument $a, ResolvedDocument $b): int => $this->dateOf($b) <=> $this->dateOf($a)
        );

        return array_slice($posts, 0, $this->config->feedItems);
    }

    private function dateOf(ResolvedDocument $document): \DateTimeImmutable
    {
        \assert($document->parsed->frontMatter instanceof PostFrontMatter);

        return $document->parsed->frontMatter->date;
    }

    private function absoluteUrl(string $path): string
    {
        return rtrim($this->config->baseUrl, '/') . $path;
    }

    /**
     * @param  list<ResolvedDocument>           $posts
     * @param  array<string, RenderedDocument> $renderedByIdentifier
     */
    private function rss(string $language, array $posts, array $renderedByIdentifier): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $rss = $dom->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $dom->appendChild($rss);

        $channel = $dom->createElement('channel');
        $rss->appendChild($channel);

        $siteUrl = $this->absoluteUrl('/' . $this->routeBuilder->prefixFor($language));
        $channel->appendChild($dom->createElement('title', $this->config->title));
        $channel->appendChild($dom->createElement('link', $siteUrl));
        $channel->appendChild($dom->createElement('description', $this->config->title));
        $channel->appendChild($dom->createElement('language', $language));

        foreach ($posts as $post) {
            \assert($post->parsed->frontMatter instanceof PostFrontMatter);
            $rendered = $renderedByIdentifier[$post->parsed->identifier()];
            $url      = $this->absoluteUrl($post->url);

            $item = $dom->createElement('item');
            $item->appendChild($dom->createElement('title', $post->parsed->frontMatter->shared->title));
            $item->appendChild($dom->createElement('link', $url));

            $guid = $dom->createElement('guid', $url);
            $guid->setAttribute('isPermaLink', 'true');
            $item->appendChild($guid);

            $item->appendChild($dom->createElement('pubDate', $post->parsed->frontMatter->date->format(\DATE_RSS)));

            $description = $dom->createElement('description');
            $description->appendChild($dom->createCDATASection(
                $this->cdataSafe($this->urlRewriter->rewrite($rendered->bodyHtml, $this->config->baseUrl))
            ));
            $item->appendChild($description);

            $channel->appendChild($item);
        }

        return (string) $dom->saveXML();
    }

    /**
     * @param  list<ResolvedDocument>           $posts
     * @param  array<string, RenderedDocument> $renderedByIdentifier
     */
    private function atom(string $language, array $posts, array $renderedByIdentifier): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $feed = $dom->createElementNS('http://www.w3.org/2005/Atom', 'feed');
        $dom->appendChild($feed);

        $siteUrl = $this->absoluteUrl('/' . $this->routeBuilder->prefixFor($language));
        $feed->appendChild($dom->createElement('title', $this->config->title));
        $feed->appendChild($dom->createElement('id', $siteUrl));

        $alternateLink = $dom->createElement('link');
        $alternateLink->setAttribute('href', $siteUrl);
        $alternateLink->setAttribute('rel', 'alternate');
        $feed->appendChild($alternateLink);

        $latestDate = $posts === [] ? new \DateTimeImmutable('@0') : $this->dateOf($posts[0]);
        $feed->appendChild($dom->createElement('updated', $latestDate->format(\DATE_ATOM)));

        foreach ($posts as $post) {
            \assert($post->parsed->frontMatter instanceof PostFrontMatter);
            $rendered = $renderedByIdentifier[$post->parsed->identifier()];
            $url      = $this->absoluteUrl($post->url);

            $entry = $dom->createElement('entry');
            $entry->appendChild($dom->createElement('title', $post->parsed->frontMatter->shared->title));

            $entryLink = $dom->createElement('link');
            $entryLink->setAttribute('href', $url);
            $entry->appendChild($entryLink);

            $entry->appendChild($dom->createElement('id', $url));
            $entry->appendChild($dom->createElement('updated', $post->parsed->frontMatter->date->format(\DATE_ATOM)));
            $entry->appendChild($dom->createElement('published', $post->parsed->frontMatter->date->format(\DATE_ATOM)));

            $content = $dom->createElement('content');
            $content->setAttribute('type', 'html');
            $content->appendChild($dom->createTextNode($this->urlRewriter->rewrite($rendered->bodyHtml, $this->config->baseUrl)));
            $entry->appendChild($content);

            $feed->appendChild($entry);
        }

        return (string) $dom->saveXML();
    }

    /**
     * A `]]>` inside content would terminate the CDATA section early — split
     * it across two sections rather than assume it can never occur.
     */
    private function cdataSafe(string $html): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $html);
    }
}
