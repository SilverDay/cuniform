<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Content\ContentException;
use Cuniform\Content\FilesystemGateway;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\FrontMatterParser;

/**
 * Stage 3 — Parse (SPEC §10.1). FrontMatterParser (T4) already collects every
 * problem *within* one document's front matter; this collects across
 * *documents* — the layer T4's acceptance criteria ("multiple bad documents
 * produce one report") always assumed would exist, since a single-document
 * parser has no way to see a second document at all.
 */
final class DocumentParser
{
    public function __construct(
        private readonly FilesystemGateway $gateway,
        private readonly FrontMatterParser $frontMatterParser,
    ) {
    }

    /**
     * @param  list<DiscoveredDocument> $discovered
     * @return list<ParsedDocument>
     *
     * @throws ContentException When any document's front matter is invalid —
     *                          message lists every offending document.
     */
    public function parse(array $discovered): array
    {
        $parsed = [];
        $errors = [];

        foreach ($discovered as $document) {
            $kind = $document->kind === DocumentKind::Post ? DocumentKind::Post : DocumentKind::Page;

            try {
                $raw         = $this->gateway->read($document->absolutePath);
                $frontMatter = $this->frontMatterParser->parse($raw, $kind, $document->relativePath);
            } catch (ContentException $e) {
                $errors[] = $e->getMessage();

                continue;
            }

            $parsed[] = new ParsedDocument($document, $frontMatter);
        }

        if ($errors !== []) {
            throw ContentException::parsingFailed($errors);
        }

        return $parsed;
    }
}
