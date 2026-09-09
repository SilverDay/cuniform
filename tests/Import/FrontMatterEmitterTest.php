<?php

declare(strict_types=1);

namespace Cuniform\Tests\Import;

use Cuniform\Content\FrontMatter\RestrictedYamlParser;
use Cuniform\Import\FrontMatterEmitter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrontMatterEmitterTest extends TestCase
{
    public function testEmitsScalarsListsAndBooleansInInsertionOrder(): void
    {
        $result = (new FrontMatterEmitter())->emit([
            'title'  => 'A Title',
            'noindex' => true,
            'tags'   => ['One', 'Two'],
        ], 'Body text.');

        self::assertSame(
            "---\ntitle: \"A Title\"\nnoindex: true\ntags:\n  - \"One\"\n  - \"Two\"\n---\nBody text.",
            $result
        );
    }

    public function testOmitsNullEmptyStringAndEmptyListFields(): void
    {
        $result = (new FrontMatterEmitter())->emit([
            'title'   => 'T',
            'updated' => null,
            'summary' => '',
            'aliases' => [],
        ], 'Body.');

        self::assertSame("---\ntitle: \"T\"\n---\nBody.", $result);
    }

    public function testFalseBooleanIsStillEmitted(): void
    {
        // Only null/''/[] are omitted — false is a real, meaningful value
        // (SPEC §5.2 noindex defaults to false, but an explicit "false" in
        // an emitted document is harmless and should round-trip exactly).
        $result = (new FrontMatterEmitter())->emit(['noindex' => false], 'Body.');

        self::assertStringContainsString('noindex: false', $result);
    }

    #[DataProvider('roundTrippableValues')]
    public function testValuesRoundTripThroughTheRealParser(string $value): void
    {
        $emitted = (new FrontMatterEmitter())->emit(['title' => $value], 'Body.');
        $titleLine = explode("\n", $emitted)[1];

        $parsed = (new RestrictedYamlParser())->parse($titleLine);

        self::assertSame($value, $parsed->data['title']);
    }

    /**
     * @return list<list<string>>
     */
    public static function roundTrippableValues(): array
    {
        return [
            ['plain text'],
            ['with "double quotes" inside'],
            ["with a backslash \\ inside"],
            ['both \\ and " together, and "more" after'],
            ['trailing backslash\\'],
            ['colon: still just text'],
            ['a # not a comment'],
        ];
    }

    public function testBodyIsAppendedAfterTheClosingDelimiterUnchanged(): void
    {
        $result = (new FrontMatterEmitter())->emit(['title' => 'T'], "Line one.\n\nLine two.");

        self::assertStringEndsWith("---\nLine one.\n\nLine two.", $result);
    }
}
