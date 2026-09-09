<?php

declare(strict_types=1);

namespace Cuniform\Tests\Template;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Template/helpers.php';

final class HelpersTest extends TestCase
{
    public function testEEscapesHtmlEntities(): void
    {
        self::assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', \e('<script>alert(1)</script>'));
    }

    public function testEEscapesQuotes(): void
    {
        self::assertSame('&quot;quoted&quot; and &#039;single&#039;', \e('"quoted" and \'single\''));
    }

    public function testEAttrEscapesTheSameAsE(): void
    {
        $value = '<b class="x">\'y\'</b>';
        self::assertSame(\e($value), \eAttr($value));
    }

    public function testEUrlAllowsHttpsAndEscapesEntities(): void
    {
        self::assertSame('https://example.test/?a=1&amp;b=2', \eUrl('https://example.test/?a=1&b=2'));
    }

    public function testEUrlAllowsRelativePaths(): void
    {
        self::assertSame('/de/post/', \eUrl('/de/post/'));
    }

    public function testEUrlRejectsJavascriptScheme(): void
    {
        self::assertSame('#', \eUrl('javascript:alert(1)'));
    }

    public function testEUrlRejectsDataScheme(): void
    {
        self::assertSame('#', \eUrl('data:text/html,<script>1</script>'));
    }

    public function testEJsEncodesAStringSafeForInlineScriptEmbedding(): void
    {
        $result = \eJs('</script><script>alert(1)</script>');

        self::assertStringNotContainsString('</script>', $result);
        self::assertStringNotContainsString('<script>', $result);
    }

    public function testEJsEncodesArraysAndScalars(): void
    {
        self::assertSame('42', \eJs(42));
        self::assertSame('true', \eJs(true));
        self::assertSame('["a","b"]', \eJs(['a', 'b']));
    }

    public function testEJsEscapesAmpersandsAndQuotes(): void
    {
        $result = \eJs('Tom & Jerry\'s "show"');

        self::assertStringNotContainsString('&', $result);
        self::assertStringNotContainsString('"show"', $result);
    }
}
