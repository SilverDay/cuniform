<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

use Cuniform\Build\ContentDiscoverer;
use Cuniform\Build\DiscoveredDocument;
use Cuniform\Content\ContentException;
use Cuniform\Content\FilesystemGateway;
use Cuniform\Content\FrontMatter\FrontMatterParser;

/**
 * Read-only view of every document under `content/` (SPEC §12's editor
 * needs both a bare file list — to resolve a request identifier without
 * trusting a path from the request, SPEC §13.2 — and a richer, parsed
 * listing for the document/translation screens). Reuses `ContentDiscoverer`
 * (T19) rather than re-walking the tree with different logic.
 *
 * `summaries()` does not throw on one document's invalid front matter the
 * way the build pipeline does (SPEC §5.5's "collect everything, then fail")
 * — a single broken file elsewhere in the tree would otherwise make the
 * *entire* editor unusable, including for editing the very file that's
 * broken. It is skipped from the listing and its error surfaced instead,
 * same shape as a build report but non-fatal here.
 */
final class DocumentIndex
{
    private readonly ContentDiscoverer $discoverer;
    private readonly FilesystemGateway $gateway;
    private readonly FrontMatterParser $frontMatterParser;

    /**
     * @param list<string> $languages
     */
    public function __construct(string $contentRoot, array $languages)
    {
        $this->discoverer        = new ContentDiscoverer($contentRoot, $languages);
        $this->gateway           = new FilesystemGateway($contentRoot);
        $this->frontMatterParser = new FrontMatterParser();
    }

    /**
     * @return list<DiscoveredDocument>
     */
    public function discover(): array
    {
        return $this->discoverer->discover();
    }

    /**
     * @return array{documents: list<DocumentSummary>, errors: list<string>}
     */
    public function summaries(): array
    {
        $documents = [];
        $errors    = [];

        foreach ($this->discover() as $discovered) {
            try {
                $raw         = $this->gateway->read($discovered->absolutePath);
                $frontMatter = $this->frontMatterParser->parse($raw, $discovered->kind, $discovered->relativePath);
            } catch (ContentException $e) {
                $errors[] = $e->getMessage();

                continue;
            }

            $documents[] = new DocumentSummary(
                identifier: $discovered->relativePath,
                kind: $discovered->kind,
                language: $discovered->language,
                title: $frontMatter->shared->title,
                slug: $frontMatter->shared->slug,
                status: $frontMatter->shared->status,
                translationKey: $frontMatter->shared->translationKey,
                mtime: $discovered->mtime,
            );
        }

        return ['documents' => $documents, 'errors' => $errors];
    }
}
