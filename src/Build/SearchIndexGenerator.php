<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Render\RenderedDocument;

/**
 * One `search-index.json` shared across languages (SPEC §11.3), filtered
 * client-side by its `lang` field. Warns above `search_index_warn_bytes`
 * (default 750 KB) rather than failing the build — SPEC's documented
 * response (drop body_plain, then split per language, then shard) is an
 * operator decision once it actually happens, not something to guess an
 * automatic strategy for here.
 */
final class SearchIndexGenerator
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param  array<string, RenderedDocument> $renderedByIdentifier
     * @return array{0: ArtifactFile, 1: list<string>}
     */
    public function generate(ResolvedSite $site, array $renderedByIdentifier): array
    {
        $entries = [];
        foreach ($site->documents as $document) {
            $entries[] = $this->entryFor($document, $renderedByIdentifier[$document->parsed->identifier()]);
        }

        $json = json_encode($entries, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        $warnings = [];
        $bytes    = strlen($json);
        if ($bytes > $this->config->build->searchIndexWarnBytes) {
            $warnings[] = sprintf(
                'search-index.json is %d bytes, over the %d byte threshold (SPEC §11.3) — '
                . 'consider dropping body_plain, then splitting per language, then sharding',
                $bytes,
                $this->config->build->searchIndexWarnBytes,
            );
        }

        return [new ArtifactFile('search-index.json', $json), $warnings];
    }

    /**
     * @return array{path: string, lang: string, type: string, title: string, date: ?string, tags: list<string>, summary: string, body_plain: string}
     */
    private function entryFor(ResolvedDocument $document, RenderedDocument $rendered): array
    {
        $frontMatter = $document->parsed->frontMatter;
        $isPost      = $frontMatter instanceof PostFrontMatter;

        return [
            'path'       => $document->url,
            'lang'       => $document->parsed->discovered->language,
            'type'       => $isPost ? 'post' : 'page',
            'title'      => $frontMatter->shared->title,
            'date'       => $isPost ? $frontMatter->date->format('Y-m-d') : null,
            'tags'       => $isPost ? $frontMatter->tags : [],
            'summary'    => $frontMatter->shared->summary,
            'body_plain' => $this->plainText($rendered->bodyHtml),
        ];
    }

    private function plainText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
