<?php

declare(strict_types=1);

namespace Cuniform\Tests\Content\FrontMatter;

use Cuniform\Content\FrontMatter\RestrictedYamlParser;
use PHPUnit\Framework\TestCase;

final class RestrictedYamlParserTest extends TestCase
{
    public function testParsesPlainScalars(): void
    {
        $result = (new RestrictedYamlParser())->parse("title: Hello World\nslug: hello-world\n");

        self::assertSame([], $result->errors);
        self::assertSame(['title' => 'Hello World', 'slug' => 'hello-world'], $result->data);
    }

    public function testParsesDoubleQuotedStringsWithEscapes(): void
    {
        $result = (new RestrictedYamlParser())->parse('title: "Say \"hi\""');

        self::assertSame([], $result->errors);
        self::assertSame('Say "hi"', $result->data['title']);
    }

    public function testParsesSingleQuotedStringsWithEscapedQuote(): void
    {
        $result = (new RestrictedYamlParser())->parse("title: 'It''s here'");

        self::assertSame([], $result->errors);
        self::assertSame("It's here", $result->data['title']);
    }

    public function testParsesBooleans(): void
    {
        $result = (new RestrictedYamlParser())->parse("noindex: true\ntoc: false\n");

        self::assertSame([], $result->errors);
        self::assertTrue($result->data['noindex']);
        self::assertFalse($result->data['toc']);
    }

    public function testParsesIntegersAndFloats(): void
    {
        $result = (new RestrictedYamlParser())->parse("nav_order: 3\nsitemap_priority: 0.8\n");

        self::assertSame([], $result->errors);
        self::assertSame(3, $result->data['nav_order']);
        self::assertSame(0.8, $result->data['sitemap_priority']);
    }

    public function testLeavesDateLikeScalarsAsPlainStrings(): void
    {
        $result = (new RestrictedYamlParser())->parse('date: 2026-03-14');

        self::assertSame([], $result->errors);
        self::assertSame('2026-03-14', $result->data['date']);
    }

    public function testParsesInlineFlowSequence(): void
    {
        $result = (new RestrictedYamlParser())->parse('tags: [security, awareness, "multi word"]');

        self::assertSame([], $result->errors);
        self::assertSame(['security', 'awareness', 'multi word'], $result->data['tags']);
    }

    public function testParsesBlockSequenceIndented(): void
    {
        $result = (new RestrictedYamlParser())->parse("aliases:\n  - /old-path/\n  - /older-path/\n");

        self::assertSame([], $result->errors);
        self::assertSame(['/old-path/', '/older-path/'], $result->data['aliases']);
    }

    public function testParsesBlockSequenceUnindented(): void
    {
        $result = (new RestrictedYamlParser())->parse("aliases:\n- /old-path/\n- /older-path/\n");

        self::assertSame([], $result->errors);
        self::assertSame(['/old-path/', '/older-path/'], $result->data['aliases']);
    }

    public function testBareKeyWithNoFollowingItemsIsAnEmptyList(): void
    {
        $result = (new RestrictedYamlParser())->parse("aliases:\ntitle: Next Key\n");

        self::assertSame([], $result->errors);
        self::assertSame([], $result->data['aliases']);
        self::assertSame('Next Key', $result->data['title']);
    }

    public function testRejectsAnchors(): void
    {
        $result = (new RestrictedYamlParser())->parse('title: &anchor Hello');

        self::assertNotSame([], $result->errors);
        self::assertStringContainsString('anchors', $result->errors[0]);
    }

    public function testRejectsAliasReferences(): void
    {
        $result = (new RestrictedYamlParser())->parse('title: *anchor');

        self::assertNotSame([], $result->errors);
        self::assertStringContainsString('alias', $result->errors[0]);
    }

    public function testRejectsCustomTags(): void
    {
        $result = (new RestrictedYamlParser())->parse('title: !!str Hello');

        self::assertNotSame([], $result->errors);
        self::assertStringContainsString('tags', $result->errors[0]);
    }

    public function testRejectsMergeKeys(): void
    {
        $result = (new RestrictedYamlParser())->parse('<<: *defaults');

        self::assertNotSame([], $result->errors);
        self::assertStringContainsString('merge keys', $result->errors[0]);
    }

    public function testRejectsNestedMappings(): void
    {
        $result = (new RestrictedYamlParser())->parse("outer:\n  inner: value\n");

        self::assertNotSame([], $result->errors);
        self::assertStringContainsString('indentation', $result->errors[0]);
    }

    public function testRejectsUnparseableLines(): void
    {
        $result = (new RestrictedYamlParser())->parse('this is not key value');

        self::assertNotSame([], $result->errors);
        self::assertStringContainsString('cannot parse line', $result->errors[0]);
    }

    public function testCollectsMultipleErrorsInOnePass(): void
    {
        $result = (new RestrictedYamlParser())->parse("title: &a Hello\nsummary: *b\n");

        self::assertCount(2, $result->errors);
    }

    public function testBlankLinesAreIgnored(): void
    {
        $result = (new RestrictedYamlParser())->parse("title: Hello\n\n\nslug: hello\n");

        self::assertSame([], $result->errors);
        self::assertSame(['title' => 'Hello', 'slug' => 'hello'], $result->data);
    }
}
