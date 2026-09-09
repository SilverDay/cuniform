<?php

declare(strict_types=1);

namespace Cuniform\Tests\Import;

use Cuniform\Import\HtmlToMarkdownConverter;
use PHPUnit\Framework\TestCase;

final class HtmlToMarkdownConverterTest extends TestCase
{
    public function testParagraphsWithInlineFormattingAndALink(): void
    {
        $result = $this->convert('<p>Hello <strong>world</strong>, visit <a href="http://example.com">this site</a>.</p>');

        self::assertSame("Hello **world**, visit [this site](http://example.com).\n", $result->markdown);
        self::assertSame([], $result->warnings);
    }

    public function testMultipleParagraphsAreSeparatedByABlankLine(): void
    {
        $result = $this->convert('<p>First.</p><p>Second.</p>');

        self::assertSame("First.\n\nSecond.\n", $result->markdown);
    }

    public function testHeadingLevels(): void
    {
        self::assertSame("## Section\n", $this->convert('<h2>Section</h2>')->markdown);
        self::assertSame("### Sub\n", $this->convert('<h3>Sub</h3>')->markdown);
    }

    public function testUnorderedList(): void
    {
        $result = $this->convert('<ul><li>one</li><li>two <strong>bold</strong></li></ul>');

        self::assertSame("- one\n- two **bold**\n", $result->markdown);
    }

    public function testOrderedListIsNumbered(): void
    {
        $result = $this->convert('<ol><li>first</li><li>second</li></ol>');

        self::assertSame("1. first\n2. second\n", $result->markdown);
    }

    public function testNestedList(): void
    {
        $result = $this->convert('<ul><li>parent<ul><li>child a</li><li>child b</li></ul></li><li>sibling</li></ul>');

        self::assertSame("- parent\n    - child a\n    - child b\n- sibling\n", $result->markdown);
    }

    public function testBlockquote(): void
    {
        $result = $this->convert('<blockquote><p>Quoted text.</p></blockquote>');

        self::assertSame("> Quoted text.\n", $result->markdown);
    }

    public function testFencedCodeBlockWithLanguageFromClass(): void
    {
        $result = $this->convert('<pre><code class="language-php">echo 1;\necho 2;</code></pre>');

        self::assertStringStartsWith("```php\n", $result->markdown);
        self::assertStringEndsWith("```\n", $result->markdown);
    }

    public function testFencedCodeBlockWithNoLanguage(): void
    {
        $result = $this->convert('<pre><code>plain</code></pre>');

        self::assertStringStartsWith("```\nplain\n```", $result->markdown);
    }

    public function testInlineCode(): void
    {
        self::assertSame("Use `foo()` here.\n", $this->convert('<p>Use <code>foo()</code> here.</p>')->markdown);
    }

    public function testEmphasisAndStrikethrough(): void
    {
        self::assertSame("*italic*\n", $this->convert('<p><em>italic</em></p>')->markdown);
        self::assertSame("~~gone~~\n", $this->convert('<p><del>gone</del></p>')->markdown);
    }

    public function testHorizontalRule(): void
    {
        self::assertSame("---\n", $this->convert('<hr>')->markdown);
    }

    public function testHardBreak(): void
    {
        self::assertSame("line one  \nline two\n", $this->convert('<p>line one<br>line two</p>')->markdown);
    }

    public function testImageBecomesMarkdownImageSyntax(): void
    {
        $result = $this->convert('<p>Look: <img src="http://x/y.jpg" alt="desc"></p>');

        self::assertSame("Look: ![desc](http://x/y.jpg)\n", $result->markdown);
        self::assertSame([], $result->warnings);
    }

    public function testImageWithNoSrcIsDroppedAndReported(): void
    {
        $result = $this->convert('<p><img alt="orphan"></p>');

        self::assertSame('', $result->markdown);
        self::assertNotSame([], $result->warnings);
    }

    public function testSpanIsTransparentAndInlinesWithSurroundingText(): void
    {
        // No <p> wrapper at all — bare inline content directly under a
        // block container (SPEC §A.2's wpautop case, but at the DOM level:
        // a <div>/<span> combination with no explicit <p>) must still read
        // as one flowing line, not split across separate paragraphs.
        $result = $this->convert('<div><span>red text</span> normal</div>');

        self::assertSame("red text normal\n", $result->markdown);
    }

    public function testUnsupportedBlockTagKeepsTextAndReports(): void
    {
        $result = $this->convert('<table><tr><td>cell</td></tr></table>');

        self::assertStringContainsString('cell', $result->markdown);
        self::assertNotSame([], $result->warnings);
    }

    public function testScriptAndStyleAreDroppedEntirelyNotJustUnwrapped(): void
    {
        $result = $this->convert('<p>before</p><script>alert(1)</script><style>body{color:red}</style><p>after</p>');

        self::assertSame("before\n\nafter\n", $result->markdown);
        self::assertStringNotContainsString('alert', $result->markdown);
        self::assertStringNotContainsString('color:red', $result->markdown);
        self::assertCount(2, $result->warnings);
    }

    public function testNeverEmitsRawHtmlIntoTheMarkdownOutput(): void
    {
        // The T35 acceptance criterion, checked directly: whatever HTML
        // constructs go in, no literal "<" tag-opening character should
        // survive into the emitted Markdown for an unsupported element —
        // it's converted, stripped, or reported, never passed through raw.
        $result = $this->convert('<article><div class="x"><iframe src="evil"></iframe><marquee>spin</marquee></div></article>');

        self::assertStringNotContainsString('<', $result->markdown);
        self::assertNotSame([], $result->warnings);
    }

    public function testGutenbergContentIsStrippedAndConvertedTheSameAsClassic(): void
    {
        $gutenberg = "<!-- wp:paragraph -->\n<p>Hello <strong>world</strong>.</p>\n<!-- /wp:paragraph -->";

        self::assertSame("Hello **world**.\n", $this->convert($gutenberg)->markdown);
    }

    public function testWpautopStyleContentWithNoExplicitParagraphTagsIsNormalized(): void
    {
        $result = $this->convert("First paragraph line.\n\nSecond paragraph line.");

        self::assertSame("First paragraph line.\n\nSecond paragraph line.\n", $result->markdown);
    }

    public function testEmptyContentProducesEmptyMarkdown(): void
    {
        self::assertSame('', $this->convert('')->markdown);
        self::assertSame('', $this->convert('   ')->markdown);
    }

    public function testWpCaptionShortcodeEndsUpAsACuniformFigureShortcode(): void
    {
        $html = '<p>[caption id="attachment_5" width="300"]<img src="http://x/a.jpg" alt="alt text" width="300" height="200" /> A nice caption[/caption]</p>';

        $result = $this->convert($html);

        self::assertSame(
            "[figure src=\"http://x/a.jpg\" alt=\"alt text\" caption=\"A nice caption\" width=\"300\" height=\"200\"]\n",
            $result->markdown
        );
        self::assertSame([], $result->warnings);
    }

    public function testUnknownWpShortcodeSurvivesIntoTheMarkdownVerbatim(): void
    {
        $result = $this->convert('<p>Some text [myplugin foo="bar"] more text.</p>');

        self::assertSame("Some text [myplugin foo=\"bar\"] more text.\n", $result->markdown);
        self::assertNotSame([], $result->warnings);
        self::assertStringContainsString('myplugin', $result->warnings[0]);
    }

    public function testUtf8ContentIsPreserved(): void
    {
        $result = $this->convert('<p>Café — “curly quotes” and Übung.</p>');

        self::assertSame("Café — “curly quotes” and Übung.\n", $result->markdown);
    }

    private function convert(string $html): \Cuniform\Import\ConversionResult
    {
        return (new HtmlToMarkdownConverter())->convert($html);
    }
}
