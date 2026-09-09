<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Content\Slugifier;
use Cuniform\I18n\DateFormatter;
use Cuniform\Routing\RouteBuilder;

/**
 * Builds the per-language listing data SPEC §10.1's Resolve stage lists —
 * tag/series indexes, generalized to year archives and the plain post
 * list the home index paginates over — from the same ResolvedSite stage 4
 * (SiteResolver) already produced. A second, independent read rather than
 * something SiteResolver itself computes: see ListingSet's own docblock.
 *
 * Tags and series are grouped by their *slugified* form (Slugifier, same
 * transliteration rules as SPEC §5.4), not their literal front-matter
 * text — two authored spellings that slugify the same way (say "Awareness"
 * and "awareness") share one archive and one URL rather than silently
 * colliding on `/{L}tag/awareness/` with two different post lists. The
 * first-encountered spelling becomes the group's display label.
 */
final class ListingResolver
{
    private readonly Slugifier $slugifier;
    private readonly RouteBuilder $routeBuilder;

    public function __construct(
        private readonly Config $config,
        private readonly DateFormatter $dateFormatter,
    ) {
        $this->slugifier   = new Slugifier();
        $this->routeBuilder = new RouteBuilder($config->urlPrefix, $config->languages, $config->permalink);
    }

    public function resolve(ResolvedSite $site): ListingSet
    {
        $postsByLanguage   = [];
        $tagsByLanguage    = [];
        $seriesByLanguage  = [];
        $yearsByLanguage   = [];

        foreach ($this->config->languages as $language) {
            $posts = $this->postsFor($site, $language);

            $postsByLanguage[$language]  = $posts;
            $tagsByLanguage[$language]   = $this->tagArchives($posts);
            $seriesByLanguage[$language] = $this->seriesArchives($posts);
            $yearsByLanguage[$language]  = $this->yearArchives($posts);
        }

        return new ListingSet($postsByLanguage, $tagsByLanguage, $seriesByLanguage, $yearsByLanguage);
    }

    /**
     * @return list<PostSummary>
     */
    private function postsFor(ResolvedSite $site, string $language): array
    {
        $documents = array_values(array_filter(
            $site->documents,
            static fn (ResolvedDocument $d): bool => $d->parsed->discovered->language === $language
                && $d->parsed->frontMatter instanceof PostFrontMatter
        ));

        usort($documents, fn (ResolvedDocument $a, ResolvedDocument $b): int => $this->dateOf($b) <=> $this->dateOf($a));

        return array_map(fn (ResolvedDocument $d): PostSummary => $this->summaryOf($d, $language), $documents);
    }

    private function summaryOf(ResolvedDocument $document, string $language): PostSummary
    {
        $frontMatter = $document->parsed->frontMatter;
        \assert($frontMatter instanceof PostFrontMatter);

        $tags = array_map(function (string $tag) use ($language): array {
            $slug = $this->slugifier->slugify($tag);

            return ['slug' => $slug, 'url' => $this->routeBuilder->tagRoute($language, $slug, 1), 'label' => $tag];
        }, $frontMatter->tags);

        return new PostSummary(
            $frontMatter->shared->title,
            $document->url,
            $frontMatter->shared->summary,
            $frontMatter->date,
            $this->dateFormatter->format($frontMatter->date, $language),
            $tags,
            $frontMatter->series,
        );
    }

    private function dateOf(ResolvedDocument $document): \DateTimeImmutable
    {
        \assert($document->parsed->frontMatter instanceof PostFrontMatter);

        return $document->parsed->frontMatter->date;
    }

    /**
     * @param  list<PostSummary> $posts
     * @return list<TagArchive>
     */
    private function tagArchives(array $posts): array
    {
        /** @var array<string, array{label: string, posts: list<PostSummary>}> $bySlug */
        $bySlug = [];

        foreach ($posts as $post) {
            foreach ($post->tags as $tag) {
                $bySlug[$tag['slug']] ??= ['label' => $tag['label'], 'posts' => []];
                $bySlug[$tag['slug']]['posts'][] = $post;
            }
        }

        return array_map(
            static fn (string $slug, array $group): TagArchive => new TagArchive($slug, $group['label'], $group['posts']),
            array_keys($bySlug),
            $bySlug
        );
    }

    /**
     * @param  list<PostSummary> $posts
     * @return list<SeriesArchive>
     */
    private function seriesArchives(array $posts): array
    {
        /** @var array<string, array{label: string, posts: list<PostSummary>}> $bySlug */
        $bySlug = [];

        foreach ($posts as $post) {
            if ($post->series === null) {
                continue;
            }

            $slug = $this->slugifier->slugify($post->series);
            $bySlug[$slug] ??= ['label' => $post->series, 'posts' => []];
            $bySlug[$slug]['posts'][] = $post;
        }

        return array_map(
            static fn (string $slug, array $group): SeriesArchive => new SeriesArchive($slug, $group['label'], $group['posts']),
            array_keys($bySlug),
            $bySlug
        );
    }

    /**
     * @param  list<PostSummary> $posts
     * @return list<YearArchive>
     */
    private function yearArchives(array $posts): array
    {
        /** @var array<int, list<PostSummary>> $byYear */
        $byYear = [];

        foreach ($posts as $post) {
            $year            = (int) $post->date->format('Y');
            $byYear[$year] ??= [];
            $byYear[$year][] = $post;
        }

        return array_map(
            static fn (int $year, array $posts): YearArchive => new YearArchive($year, $posts),
            array_keys($byYear),
            $byYear
        );
    }
}
