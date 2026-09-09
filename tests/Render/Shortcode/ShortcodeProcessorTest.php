<?php

declare(strict_types=1);

namespace Cuniform\Tests\Render\Shortcode;

use Cuniform\Render\Md2Html;
use Cuniform\Render\RenderException;
use Cuniform\Render\Shortcode\ShortcodeBodyType;
use Cuniform\Render\Shortcode\ShortcodeHandler;
use Cuniform\Render\Shortcode\ShortcodeHandlerRegistry;
use Cuniform\Render\Shortcode\ShortcodeProcessor;
use PHPUnit\Framework\TestCase;

final class ShortcodeProcessorTest extends TestCase
{
    public function testUnknownShortcodeNameIsLeftAsLiteralText(): void
    {
        $html = $this->process('Some text [bogus attr="1"] continues.', []);

        self::assertStringContainsString('[bogus attr=&quot;1&quot;]', $html);
    }

    public function testSelfClosingInlineShortcodeSubstitutesInPlace(): void
    {
        $handler = $this->fakeHandler('kbd', ShortcodeBodyType::None, false, static function (array $attrs): string {
            return '<kbd>' . ($attrs['key'] ?? '') . '</kbd>';
        });

        $html = $this->process('Press [kbd key="Enter"] to continue.', [$handler]);

        self::assertStringContainsString('<p>Press <kbd>Enter</kbd> to continue.</p>', $html);
    }

    public function testBlockLevelShortcodeUnwrapsTheParagraphWrapper(): void
    {
        $handler = $this->fakeHandler('divider', ShortcodeBodyType::None, true, static function (): string {
            return '<hr class="fancy">';
        });

        $html = $this->process("Before.\n\n[divider]\n\nAfter.", [$handler]);

        self::assertStringContainsString('<hr class="fancy">', $html);
        self::assertStringNotContainsString('<p><hr class="fancy"></p>', $html);
        self::assertStringNotContainsString('%%CFSC', $html);
    }

    public function testShortcodeInsideFencedCodeBlockIsNotInterpreted(): void
    {
        $handler = $this->fakeHandler('figure', ShortcodeBodyType::None, true, static fn (): string => '<figure>REPLACED</figure>');

        $markdown = "```\n[figure src=\"x.png\"]\n```";
        $html     = $this->process($markdown, [$handler]);

        self::assertStringNotContainsString('REPLACED', $html);
        self::assertStringContainsString('[figure src=&quot;x.png&quot;]', $html);
    }

    public function testShortcodeInsideInlineCodeSpanIsNotInterpreted(): void
    {
        $handler = $this->fakeHandler('figure', ShortcodeBodyType::None, false, static fn (): string => 'REPLACED');

        $html = $this->process('Use `[figure src="x"]` in your post.', [$handler]);

        self::assertStringNotContainsString('REPLACED', $html);
        self::assertStringContainsString('<code>[figure src=&quot;x&quot;]</code>', $html);
    }

    public function testPairedShortcodeWithTextBodyIsEscaped(): void
    {
        $handler = $this->fakeHandler('note', ShortcodeBodyType::Text, true, static function (array $attrs, ?string $body): string {
            return '<div class="note">' . $body . '</div>';
        });

        $html = $this->process("[note]<b>not markdown</b>[/note]", [$handler]);

        self::assertStringContainsString('<div class="note">&lt;b&gt;not markdown&lt;/b&gt;</div>', $html);
    }

    public function testPairedShortcodeWithRawBodyIsUnprocessed(): void
    {
        $handler = $this->fakeHandler('raw', ShortcodeBodyType::Raw, false, static function (array $attrs, ?string $body): string {
            return '<span data-raw="' . htmlspecialchars((string) $body, ENT_QUOTES) . '"></span>';
        });

        $html = $this->process('See [raw]**not bold** & <weird>[/raw] here.', [$handler]);

        self::assertStringContainsString('data-raw="**not bold** &amp; &lt;weird&gt;"', $html);
    }

    public function testPairedShortcodeWithMarkdownBodyIsRenderedRecursively(): void
    {
        $handler = $this->fakeHandler('wrap', ShortcodeBodyType::Markdown, true, static function (array $attrs, ?string $body): string {
            return '<div class="wrap">' . $body . '</div>';
        });

        $html = $this->process("[wrap]This is **bold**.[/wrap]", [$handler]);

        self::assertStringContainsString('<div class="wrap"><p>This is <strong>bold</strong>.</p>', $html);
    }

    public function testMarkdownBodyCanContainNestedShortcodes(): void
    {
        $wrap = $this->fakeHandler('wrap', ShortcodeBodyType::Markdown, true, static function (array $attrs, ?string $body): string {
            return '<div class="wrap">' . $body . '</div>';
        });
        $kbd = $this->fakeHandler('kbd', ShortcodeBodyType::None, false, static function (array $attrs): string {
            return '<kbd>' . ($attrs['key'] ?? '') . '</kbd>';
        });

        $html = $this->process('[wrap]Press [kbd key="Esc"] now.[/wrap]', [$wrap, $kbd]);

        self::assertStringContainsString('<kbd>Esc</kbd>', $html);
        self::assertStringContainsString('<div class="wrap">', $html);
    }

    public function testUnclosedPairedShortcodeThrowsRenderException(): void
    {
        $handler = $this->fakeHandler('note', ShortcodeBodyType::Text, true, static fn (): string => '');

        $this->expectException(RenderException::class);
        $this->expectExceptionMessageMatches('/no matching \[\/note\]/');
        $this->process('[note]never closed', [$handler]);
    }

    public function testDuplicateHandlerNameThrowsOnRegistryConstruction(): void
    {
        $a = $this->fakeHandler('dup', ShortcodeBodyType::None, false, static fn (): string => 'a');
        $b = $this->fakeHandler('dup', ShortcodeBodyType::None, false, static fn (): string => 'b');

        $this->expectException(RenderException::class);
        $this->expectExceptionMessageMatches("/'dup'/");
        new ShortcodeHandlerRegistry([$a, $b]);
    }

    public function testAttributesArePassedRawAndUnescapedToTheHandler(): void
    {
        $captured = null;
        $handler  = $this->fakeHandler('cap', ShortcodeBodyType::None, false, static function (array $attrs) use (&$captured): string {
            $captured = $attrs;

            return 'ok';
        });

        $this->process('[cap value="Tom & Jerry"]', [$handler]);

        self::assertSame(['value' => 'Tom & Jerry'], $captured);
    }

    public function testQuotedAndUnquotedAttributeValuesBothParse(): void
    {
        $captured = null;
        $handler  = $this->fakeHandler('cap', ShortcodeBodyType::None, false, static function (array $attrs) use (&$captured): string {
            $captured = $attrs;

            return 'ok';
        });

        $this->process('[cap type=warn label="Be careful"]', [$handler]);

        self::assertSame(['type' => 'warn', 'label' => 'Be careful'], $captured);
    }

    /**
     * @param list<ShortcodeHandler> $handlers
     */
    private function process(string $markdown, array $handlers): string
    {
        $processor = new ShortcodeProcessor(
            new Md2Html(['headless' => true]),
            new ShortcodeHandlerRegistry($handlers)
        );

        return $processor->convert($markdown);
    }

    private function fakeHandler(string $name, ShortcodeBodyType $bodyType, bool $blockLevel, \Closure $render): ShortcodeHandler
    {
        return new class ($name, $bodyType, $blockLevel, $render) implements ShortcodeHandler {
            public function __construct(
                private readonly string $name,
                private readonly ShortcodeBodyType $bodyType,
                private readonly bool $blockLevel,
                private readonly \Closure $renderFn,
            ) {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function bodyType(): ShortcodeBodyType
            {
                return $this->bodyType;
            }

            public function isBlockLevel(): bool
            {
                return $this->blockLevel;
            }

            public function render(array $attributes, ?string $body): string
            {
                return ($this->renderFn)($attributes, $body);
            }
        };
    }
}
