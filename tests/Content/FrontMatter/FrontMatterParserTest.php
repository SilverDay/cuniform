<?php

declare(strict_types=1);

namespace Cuniform\Tests\Content\FrontMatter;

use Cuniform\Content\ContentException;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\FrontMatterParser;
use Cuniform\Content\FrontMatter\LegalRole;
use Cuniform\Content\FrontMatter\NavGroup;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use PHPUnit\Framework\TestCase;

final class FrontMatterParserTest extends TestCase
{
    public function testParsesAMinimalValidPost(): void
    {
        $doc = <<<'MD'
        ---
        title: Security Culture
        slug: security-culture
        status: published
        summary: Why culture beats compliance.
        date: 2026-03-14
        ---
        # Body

        Content here.
        MD;

        $result = (new FrontMatterParser())->parse($doc, DocumentKind::Post);

        self::assertInstanceOf(PostFrontMatter::class, $result);
        self::assertSame('Security Culture', $result->shared->title);
        self::assertSame('security-culture', $result->shared->slug);
        self::assertSame(DocumentStatus::Published, $result->shared->status);
        self::assertSame("# Body\n\nContent here.", $result->body);
    }

    public function testParsesAMinimalValidPage(): void
    {
        $doc = <<<'MD'
        ---
        title: Legal Notice
        slug: legal-notice
        status: published
        summary: Provider identification.
        legal: impressum
        ---
        Body text.
        MD;

        $result = (new FrontMatterParser())->parse($doc, DocumentKind::Page);

        self::assertInstanceOf(PageFrontMatter::class, $result);
        self::assertSame(LegalRole::Impressum, $result->legal);
        self::assertSame('page.php', $result->template);
        self::assertSame('Body text.', $result->body);
    }

    public function testBodyNeverContainsTheFrontMatterBlock(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-01-01\n---\nBody only.";

        $result = (new FrontMatterParser())->parse($doc, DocumentKind::Post);

        self::assertStringNotContainsString('---', $result->body);
        self::assertStringNotContainsString('title:', $result->body);
        self::assertSame('Body only.', $result->body);
    }

    public function testMissingRequiredKeyFails(): void
    {
        $doc = "---\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-01-01\n---\nBody";

        try {
            (new FrontMatterParser())->parse($doc, DocumentKind::Post);
            self::fail('Expected a ContentException.');
        } catch (ContentException $e) {
            self::assertStringContainsString("missing required key 'title'", $e->getMessage());
        }
    }

    public function testMissingDateFailsForPostOnly(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\n---\nBody";

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches("/missing required key 'date'/");
        (new FrontMatterParser())->parse($doc, DocumentKind::Post);
    }

    public function testDateIsNotRequiredForPages(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\n---\nBody";

        $result = (new FrontMatterParser())->parse($doc, DocumentKind::Page);

        self::assertInstanceOf(PageFrontMatter::class, $result);
    }

    public function testUnknownKeyFails(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-01-01\nbogus: 1\n---\nBody";

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches("/unknown front matter key 'bogus'/");
        (new FrontMatterParser())->parse($doc, DocumentKind::Post);
    }

    public function testPostOnlyKeyOnAPageIsUnknown(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-01-01\n---\nBody";

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches("/unknown front matter key 'date'/");
        (new FrontMatterParser())->parse($doc, DocumentKind::Page);
    }

    public function testPageOnlyKeyOnAPostIsUnknown(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-01-01\nnav_order: 1\n---\nBody";

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches("/unknown front matter key 'nav_order'/");
        (new FrontMatterParser())->parse($doc, DocumentKind::Post);
    }

    public function testInvalidSlugPatternFails(): void
    {
        $doc = "---\ntitle: T\nslug: Not_Valid!\nstatus: draft\nsummary: S\ndate: 2026-01-01\n---\nBody";

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches('/invalid slug pattern/');
        (new FrontMatterParser())->parse($doc, DocumentKind::Post);
    }

    public function testInvalidStatusFails(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: sometimes\nsummary: S\ndate: 2026-01-01\n---\nBody";

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches("/'status' must be one of/");
        (new FrontMatterParser())->parse($doc, DocumentKind::Post);
    }

    public function testSummaryOver200CharactersFails(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: \"" . str_repeat('x', 201) . "\"\ndate: 2026-01-01\n---\nBody";

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches('/at most 200 characters/');
        (new FrontMatterParser())->parse($doc, DocumentKind::Post);
    }

    public function testImageWithoutImageAltFails(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-01-01\nimage: /media/x.jpg\n---\nBody";

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches("/'image_alt' is required/");
        (new FrontMatterParser())->parse($doc, DocumentKind::Post);
    }

    public function testImageWithImageAltSucceeds(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-01-01\n"
            . "image: /media/x.jpg\nimage_alt: A cat\n---\nBody";

        $result = (new FrontMatterParser())->parse($doc, DocumentKind::Post);

        self::assertSame('/media/x.jpg', $result->shared->image);
        self::assertSame('A cat', $result->shared->imageAlt);
    }

    public function testNoindexAndTocDefaultToFalse(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-01-01\n---\nBody";

        $result = (new FrontMatterParser())->parse($doc, DocumentKind::Post);

        self::assertFalse($result->shared->noindex);
        self::assertFalse($result->shared->toc);
        self::assertSame([], $result->shared->aliases);
    }

    public function testDateWithoutOffsetAssumesTheDefaultTimezone(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-03-14T10:00:00\n---\nBody";

        $result = (new FrontMatterParser('Europe/Berlin'))->parse($doc, DocumentKind::Post);

        if (!$result instanceof PostFrontMatter) {
            self::fail('Expected a PostFrontMatter.');
        }

        self::assertSame('Europe/Berlin', $result->date->getTimezone()->getName());
    }

    public function testDateWithExplicitOffsetIsRespected(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: 2026-03-14T10:00:00+02:00\n---\nBody";

        $result = (new FrontMatterParser('Europe/Berlin'))->parse($doc, DocumentKind::Post);

        if (!$result instanceof PostFrontMatter) {
            self::fail('Expected a PostFrontMatter.');
        }

        self::assertSame('+02:00', $result->date->format('P'));
    }

    public function testUnparseableDateFails(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: draft\nsummary: S\ndate: next tuesday\n---\nBody";

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches('/not a valid ISO-8601 date/');
        (new FrontMatterParser())->parse($doc, DocumentKind::Post);
    }

    public function testTagsAndSeriesOnAPost(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: published\nsummary: S\ndate: 2026-01-01\n"
            . "tags: [awareness, culture]\nseries: security-basics\n---\nBody";

        $result = (new FrontMatterParser())->parse($doc, DocumentKind::Post);

        if (!$result instanceof PostFrontMatter) {
            self::fail('Expected a PostFrontMatter.');
        }

        self::assertSame(['awareness', 'culture'], $result->tags);
        self::assertSame('security-basics', $result->series);
    }

    public function testPageNavGroupEnumParses(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: published\nsummary: S\nnav_group: footer\n---\nBody";

        $result = (new FrontMatterParser())->parse($doc, DocumentKind::Page);

        if (!$result instanceof PageFrontMatter) {
            self::fail('Expected a PageFrontMatter.');
        }

        self::assertSame(NavGroup::Footer, $result->navGroup);
    }

    public function testPageInvalidLegalValueFails(): void
    {
        $doc = "---\ntitle: T\nslug: t\nstatus: published\nsummary: S\nlegal: taxes\n---\nBody";

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches("/'legal' must be one of/");
        (new FrontMatterParser())->parse($doc, DocumentKind::Page);
    }

    public function testMultipleErrorsAreReportedTogether(): void
    {
        $doc = "---\nslug: Not Valid\nstatus: sometimes\nsummary: S\ndate: 2026-01-01\n---\nBody";

        try {
            (new FrontMatterParser())->parse($doc, DocumentKind::Post);
            self::fail('Expected a ContentException.');
        } catch (ContentException $e) {
            self::assertStringContainsString("missing required key 'title'", $e->getMessage());
            self::assertStringContainsString('invalid slug pattern', $e->getMessage());
            self::assertStringContainsString("'status' must be one of", $e->getMessage());
        }
    }

    public function testNoFrontMatterDelimiterTreatsWholeDocumentAsBody(): void
    {
        $doc = "# Just a Markdown document\n\nNo front matter here.";

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches("/missing required key 'title'/");
        (new FrontMatterParser())->parse($doc, DocumentKind::Post);
    }
}
