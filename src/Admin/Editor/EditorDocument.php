<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\LegalRole;
use Cuniform\Content\FrontMatter\NavGroup;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Content\FrontMatter\PostFrontMatter;

/**
 * A document as loaded into the editor (SPEC §12): every shared (§5.2),
 * post-only (§5.3) and page-only (§6.2) front matter field, flattened onto
 * one value object rather than kept as the PostFrontMatter|PageFrontMatter
 * union — a front matter *form* binds to one flat field set regardless of
 * kind, and the fields that don't apply to this document's kind are simply
 * null/empty. `sha256` is the loaded version's hash — what
 * EditorDocumentStore::save() compares the on-disk file against before
 * writing (SPEC §12's conflict handling).
 */
final class EditorDocument
{
    /**
     * @param list<string> $aliases
     * @param list<string> $tags
     */
    public function __construct(
        public readonly string $identifier,
        public readonly DocumentKind $kind,
        public readonly string $language,
        public readonly string $sha256,
        public readonly string $title,
        public readonly string $slug,
        public readonly DocumentStatus $status,
        public readonly string $summary,
        public readonly ?string $translationKey,
        public readonly ?\DateTimeImmutable $updated,
        public readonly ?string $image,
        public readonly ?string $imageAlt,
        public readonly ?string $canonical,
        public readonly bool $noindex,
        public readonly array $aliases,
        public readonly bool $toc,
        public readonly ?string $sourceId,
        public readonly ?\DateTimeImmutable $date,
        public readonly array $tags,
        public readonly ?string $series,
        public readonly ?string $template,
        public readonly ?string $navLabel,
        public readonly ?int $navOrder,
        public readonly ?string $navParent,
        public readonly ?NavGroup $navGroup,
        public readonly ?float $sitemapPriority,
        public readonly ?LegalRole $legal,
        public readonly string $body,
    ) {
    }

    public static function fromParsed(
        string $identifier,
        string $language,
        DocumentKind $kind,
        string $sha256,
        PostFrontMatter|PageFrontMatter $frontMatter,
    ): self {
        $shared = $frontMatter->shared;
        $isPost = $frontMatter instanceof PostFrontMatter;
        $isPage = $frontMatter instanceof PageFrontMatter;

        return new self(
            identifier: $identifier,
            kind: $kind,
            language: $language,
            sha256: $sha256,
            title: $shared->title,
            slug: $shared->slug,
            status: $shared->status,
            summary: $shared->summary,
            translationKey: $shared->translationKey,
            updated: $shared->updated,
            image: $shared->image,
            imageAlt: $shared->imageAlt,
            canonical: $shared->canonical,
            noindex: $shared->noindex,
            aliases: $shared->aliases,
            toc: $shared->toc,
            sourceId: $shared->sourceId,
            date: $isPost ? $frontMatter->date : null,
            tags: $isPost ? $frontMatter->tags : [],
            series: $isPost ? $frontMatter->series : null,
            template: $isPage ? $frontMatter->template : null,
            navLabel: $isPage ? $frontMatter->navLabel : null,
            navOrder: $isPage ? $frontMatter->navOrder : null,
            navParent: $isPage ? $frontMatter->navParent : null,
            navGroup: $isPage ? $frontMatter->navGroup : null,
            sitemapPriority: $isPage ? $frontMatter->sitemapPriority : null,
            legal: $isPage ? $frontMatter->legal : null,
            body: $frontMatter->body,
        );
    }

    /**
     * Builds a save request that would recreate this document as a new file
     * — used by EditorDocumentStore::move() (SPEC §12: "changing [language]
     * later is a move ... the editor performs it as one rather than editing
     * a field"). $identifier/$expectedSha256 are null because a move always
     * targets a path that must not already exist, the same as creating a
     * brand new document.
     */
    public function asSaveRequest(string $language, string $slug): EditorSaveRequest
    {
        return $this->toSaveRequest(identifier: null, expectedSha256: null, language: $language, slug: $slug);
    }

    /**
     * The update-in-place counterpart of asSaveRequest(): keeps this
     * document's own identifier and loaded sha256, so EditorDocumentStore::
     * save() treats it as "write back over what's on disk" rather than
     * "create a new file" — used when re-submitting a document unchanged
     * (or with typed-object edits rather than raw form fields).
     */
    public function asSaveRequestForUpdate(): EditorSaveRequest
    {
        return $this->toSaveRequest(identifier: $this->identifier, expectedSha256: $this->sha256, language: $this->language, slug: $this->slug);
    }

    private function toSaveRequest(?string $identifier, ?string $expectedSha256, string $language, string $slug): EditorSaveRequest
    {
        return new EditorSaveRequest(
            identifier: $identifier,
            expectedSha256: $expectedSha256,
            language: $language,
            kind: $this->kind,
            title: $this->title,
            slug: $slug,
            status: $this->status->value,
            summary: $this->summary,
            translationKey: $this->translationKey,
            updated: self::dateToString($this->updated),
            image: $this->image,
            imageAlt: $this->imageAlt,
            canonical: $this->canonical,
            noindex: $this->noindex,
            aliases: $this->aliases,
            toc: $this->toc,
            sourceId: $this->sourceId,
            date: self::dateToString($this->date),
            tags: $this->tags,
            series: $this->series,
            template: $this->template,
            navLabel: $this->navLabel,
            navOrder: $this->navOrder === null ? null : (string) $this->navOrder,
            navParent: $this->navParent,
            navGroup: $this->navGroup?->value,
            sitemapPriority: $this->sitemapPriority === null ? null : (string) $this->sitemapPriority,
            legal: $this->legal?->value,
            body: $this->body,
        );
    }

    private static function dateToString(?\DateTimeImmutable $date): ?string
    {
        return $date?->format('Y-m-d\TH:i:sP');
    }
}
