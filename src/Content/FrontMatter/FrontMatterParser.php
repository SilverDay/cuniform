<?php

declare(strict_types=1);

namespace Cuniform\Content\FrontMatter;

use Cuniform\Content\ContentException;

/**
 * Splits a document into front matter + body and validates the front matter
 * against the shared schema (SPEC §5.2) plus the post-only (§5.3) or
 * page-only (§6.2) schema for the given DocumentKind. Every problem in one
 * document is collected and reported together (§5.5), not just the first.
 *
 * The body returned here never includes the front matter block — this is
 * what "front matter is never passed to the renderer" (§4.6) depends on.
 */
final class FrontMatterParser
{
    private const SHARED_REQUIRED = ['title', 'slug', 'status', 'summary'];

    private const SHARED_KEYS = [
        'title', 'slug', 'status', 'summary', 'translation_key', 'updated',
        'image', 'image_alt', 'canonical', 'noindex', 'aliases', 'toc', 'source_id',
    ];

    private const POST_ONLY_KEYS = ['date', 'tags', 'series'];

    private const PAGE_ONLY_KEYS = [
        'template', 'nav_label', 'nav_order', 'nav_parent', 'nav_group', 'sitemap_priority', 'legal',
    ];

    // Public: Cuniform\Admin\Editor\EditorDocumentStore (T29) reuses these to
    // pre-validate a slug/date before it can even derive a new document's file
    // path — the substantive validation still happens exactly once, here, via
    // the emit-then-reparse round trip; this only avoids a second, drifting
    // copy of the same two patterns.
    public const SLUG_PATTERN = '/^[a-z0-9-]{1,96}$/';

    public const ISO8601_PATTERN =
        '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)?$/';

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param string $defaultTimezone Applied to a date/updated value with no
     *                                explicit offset. SPEC §5.3 states this for
     *                                `date`; applied to `updated` too for the
     *                                same reason a site has one configured
     *                                timezone, not two.
     */
    public function __construct(private readonly string $defaultTimezone = 'Europe/Berlin')
    {
    }

    public function parse(string $raw, DocumentKind $kind, string $sourcePath = ''): PostFrontMatter|PageFrontMatter
    {
        $this->errors = [];

        [$yaml, $body] = $this->split($raw);

        $yamlResult = (new RestrictedYamlParser())->parse($yaml);
        $this->errors = [...$this->errors, ...$yamlResult->errors];
        $data          = $yamlResult->data;

        $allowedKeys = [...self::SHARED_KEYS, ...($kind === DocumentKind::Post ? self::POST_ONLY_KEYS : self::PAGE_ONLY_KEYS)];
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowedKeys, true)) {
                $this->errors[] = "unknown front matter key '{$key}'";
            }
        }

        $requiredKeys = [...self::SHARED_REQUIRED, ...($kind === DocumentKind::Post ? ['date'] : [])];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                $this->errors[] = "missing required key '{$key}'";
            }
        }

        $shared = $this->readSharedFields($data);

        $result = $kind === DocumentKind::Post
            ? $this->buildPost($shared, $data, $body)
            : $this->buildPage($shared, $data, $body);

        if ($this->errors !== []) {
            throw ContentException::invalidFrontMatter($sourcePath, $this->errors);
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $raw): array
    {
        if ($raw !== '---' && !str_starts_with($raw, "---\n")) {
            return ['', $raw];
        }

        $closingAt = strpos($raw, "\n---", 3);
        if ($closingAt === false) {
            return ['', $raw];
        }

        $yaml = substr($raw, 4, $closingAt - 4);

        $afterClosingDelimiter = $closingAt + 4;
        $bodyStartsAt          = strpos($raw, "\n", $afterClosingDelimiter);
        $body                  = $bodyStartsAt === false ? '' : substr($raw, $bodyStartsAt + 1);

        return [$yaml, $body];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function readSharedFields(array $data): SharedFrontMatter
    {
        return new SharedFrontMatter(
            title: $this->stringOr($data, 'title', ''),
            slug: $this->readSlug($data),
            status: $this->readStatus($data),
            summary: $this->readSummary($data),
            translationKey: $this->optionalString($data, 'translation_key'),
            updated: $this->optionalDate($data, 'updated'),
            image: $this->optionalString($data, 'image'),
            imageAlt: $this->readImageAlt($data),
            canonical: $this->optionalString($data, 'canonical'),
            noindex: $this->boolOr($data, 'noindex', false),
            aliases: $this->stringListOr($data, 'aliases'),
            toc: $this->boolOr($data, 'toc', false),
            sourceId: $this->optionalString($data, 'source_id'),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function buildPost(SharedFrontMatter $shared, array $data, string $body): PostFrontMatter
    {
        $date = $this->optionalDate($data, 'date') ?? new \DateTimeImmutable('@0');

        return new PostFrontMatter(
            shared: $shared,
            date: $date,
            tags: $this->stringListOr($data, 'tags'),
            series: $this->optionalString($data, 'series'),
            body: $body,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function buildPage(SharedFrontMatter $shared, array $data, string $body): PageFrontMatter
    {
        return new PageFrontMatter(
            shared: $shared,
            template: $this->stringOr($data, 'template', 'page.php'),
            navLabel: $this->optionalString($data, 'nav_label'),
            navOrder: $this->optionalInt($data, 'nav_order'),
            navParent: $this->optionalString($data, 'nav_parent'),
            navGroup: $this->optionalEnum($data, 'nav_group', NavGroup::class),
            sitemapPriority: $this->optionalFloat($data, 'sitemap_priority'),
            legal: $this->optionalEnum($data, 'legal', LegalRole::class),
            body: $body,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function readSlug(array $data): string
    {
        $slug = $this->stringOr($data, 'slug', '');
        if ($slug !== '' && !preg_match(self::SLUG_PATTERN, $slug)) {
            $this->errors[] = "invalid slug pattern: '{$slug}' does not match " . self::SLUG_PATTERN;

            return '';
        }

        return $slug;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function readStatus(array $data): DocumentStatus
    {
        $value = $this->stringOr($data, 'status', '');
        if ($value === '') {
            return DocumentStatus::Draft;
        }

        $status = DocumentStatus::tryFrom($value);
        if ($status === null) {
            $allowed = implode(', ', array_map(static fn (DocumentStatus $s): string => $s->value, DocumentStatus::cases()));
            $this->errors[] = "'status' must be one of {$allowed}, got '{$value}'";

            return DocumentStatus::Draft;
        }

        return $status;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function readSummary(array $data): string
    {
        $summary = $this->stringOr($data, 'summary', '');
        if ($summary !== '' && strlen($summary) > 200) {
            $this->errors[] = "'summary' must be at most 200 characters, got " . strlen($summary);
        }

        return $summary;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function readImageAlt(array $data): ?string
    {
        $imageAlt = $this->optionalString($data, 'image_alt');
        if (array_key_exists('image', $data) && $imageAlt === null) {
            $this->errors[] = "'image_alt' is required when 'image' is set";
        }

        return $imageAlt;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function stringOr(array $data, string $key, string $default): string
    {
        if (!array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];
        if (!is_string($value)) {
            $this->errors[] = "'{$key}' must be a string";

            return $default;
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function optionalString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];
        if (!is_string($value)) {
            $this->errors[] = "'{$key}' must be a string";

            return null;
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function optionalInt(array $data, string $key): ?int
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];
        if (!is_int($value)) {
            $this->errors[] = "'{$key}' must be an integer";

            return null;
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function optionalFloat(array $data, string $key): ?float
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];
        if (!is_float($value) && !is_int($value)) {
            $this->errors[] = "'{$key}' must be a number";

            return null;
        }

        return (float) $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function boolOr(array $data, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];
        if (!is_bool($value)) {
            $this->errors[] = "'{$key}' must be a boolean";

            return $default;
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed> $data
     * @return list<string>
     */
    private function stringListOr(array $data, string $key): array
    {
        if (!array_key_exists($key, $data)) {
            return [];
        }

        $value = $data[$key];
        if (!is_array($value) || !array_is_list($value)) {
            $this->errors[] = "'{$key}' must be a list";

            return [];
        }

        $items = [];
        foreach ($value as $i => $item) {
            if (!is_string($item)) {
                $this->errors[] = "'{$key}[{$i}]' must be a string";

                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @template T of \BackedEnum
     * @param    array<array-key, mixed> $data
     * @param    class-string<T>         $enumClass
     * @return   T|null
     */
    private function optionalEnum(array $data, string $key, string $enumClass): ?\BackedEnum
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];
        if (!is_string($value)) {
            $this->errors[] = "'{$key}' must be a string";

            return null;
        }

        $case = $enumClass::tryFrom($value);
        if ($case === null) {
            $allowed = implode(', ', array_map(static fn (\BackedEnum $c): string => (string) $c->value, $enumClass::cases()));
            $this->errors[] = "'{$key}' must be one of {$allowed}, got '{$value}'";

            return null;
        }

        return $case;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function optionalDate(array $data, string $key): ?\DateTimeImmutable
    {
        $value = $this->optionalString($data, $key);
        if ($value === null) {
            return null;
        }

        if (!preg_match(self::ISO8601_PATTERN, $value)) {
            $this->errors[] = "'{$key}' is not a valid ISO-8601 date: '{$value}'";

            return null;
        }

        $hasOffset = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $value);

        try {
            return new \DateTimeImmutable($value, $hasOffset ? null : new \DateTimeZone($this->defaultTimezone));
        } catch (\Exception) {
            $this->errors[] = "'{$key}' is not a valid ISO-8601 date: '{$value}'";

            return null;
        }
    }
}
