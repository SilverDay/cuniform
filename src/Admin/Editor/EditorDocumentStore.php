<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

use Cuniform\Admin\AdminException;
use Cuniform\Admin\Audit\AuditLogEntry;
use Cuniform\Admin\Audit\AuditLogWriter;
use Cuniform\Admin\Build\BuildRequestQueue;
use Cuniform\Build\DiscoveredDocument;
use Cuniform\Content\ContentException;
use Cuniform\Content\FilesystemGateway;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\FrontMatterEmitter;
use Cuniform\Content\FrontMatter\FrontMatterParser;

/**
 * The admin editor's document operations (SPEC §12, Path B): load, save
 * (create or update, with SHA-256 conflict detection), and move (a
 * language change — "the editor performs it as one rather than editing a
 * field"). Every write is followed by a git commit carrying the operator's
 * own identity (SPEC §12's "session identity"), via `proc_open` (§13.2).
 *
 * `save()`/`move()` return an EditorSaveOutcome rather than throwing for
 * the expected business outcomes (conflict, invalid front matter, deleted
 * identifier) — same pattern as Auth\LoginService/LoginOutcome. `load()`
 * throws AdminException for "no such document," matching how the rest of
 * this codebase treats a GET-time 404 (ContentException, RenderException,
 * ...) rather than adding a fourth outcome case nothing else would use.
 *
 * A document identifier is always resolved against DocumentIndex::discover()
 * — the actual set of files under `content/` — never built by joining a
 * request value onto the content root and trusting it (SPEC §13.2: "the
 * editor taking a document identifier, never a path from the request").
 *
 * SPEC §12: "On publish: flip status, commit, enqueue a build" — T29 already
 * decided publish is the `status` field, not a second endpoint (see
 * BUILD-ORDER.md T29's own note), so "on publish" here means "after a real
 * write whose resulting document is `published`": save()/move() enqueue a
 * build (T32, BuildRequestQueue) exactly then, never for a draft/scheduled
 * result and never for the no-op "nothing actually changed" branch in
 * save() that already skips the git commit for the same reason. Un-
 * publishing (published -> draft) does not itself enqueue — SPEC's own
 * text names only the publish direction, and the already-live page is
 * removed by the next build regardless of what triggers it (manual,
 * scheduled, or a later publish elsewhere).
 *
 * SPEC §13.2's audit log ("actor, action, timestamp, and resulting commit
 * SHA") is recorded here too (T33, AuditLogWriter), immediately after each
 * real commit — save()'s "nothing actually changed" branch produces no
 * commit and so gets no audit entry either, same reasoning as the build
 * queue above.
 */
final class EditorDocumentStore
{
    private readonly FilesystemGateway $gateway;
    private readonly FrontMatterParser $frontMatterParser;
    private readonly FrontMatterEmitter $frontMatterEmitter;
    private readonly DocumentIndex $index;
    private readonly string $contentRoot;

    /**
     * @param list<string> $languages
     */
    public function __construct(
        string $contentRoot,
        private readonly array $languages,
        string $defaultTimezone,
        private readonly GitRepository $git,
        private readonly BuildRequestQueue $buildQueue,
        private readonly AuditLogWriter $auditLog,
    ) {
        $this->gateway           = new FilesystemGateway($contentRoot);
        $this->frontMatterParser = new FrontMatterParser($defaultTimezone);
        $this->frontMatterEmitter = new FrontMatterEmitter();
        $this->index              = new DocumentIndex($contentRoot, $languages);

        $realRoot          = realpath($contentRoot);
        $this->contentRoot = $realRoot === false ? rtrim($contentRoot, '/') : $realRoot;
    }

    /**
     * @return array{documents: list<DocumentSummary>, errors: list<string>}
     */
    public function index(): array
    {
        return $this->index->summaries();
    }

    /**
     * The exact bytes save()/move() would write for $request — exposed so
     * the editor page can build the "what I was about to save" side of the
     * conflict diff without duplicating buildEmitterFields()'s mapping.
     */
    public function render(EditorSaveRequest $request): string
    {
        return $this->frontMatterEmitter->emit($this->buildEmitterFields($request), $request->body);
    }

    /**
     * @throws AdminException   When no such document is in the content index.
     * @throws ContentException When the on-disk front matter is invalid.
     */
    public function load(string $identifier): EditorDocument
    {
        $discovered = $this->findDiscovered($identifier);
        if ($discovered === null) {
            throw AdminException::documentNotFound($identifier);
        }

        return $this->loadDiscovered($discovered);
    }

    /**
     * SPEC §12: "The editor surfaces the translation group: when editing a
     * document with a translation_key, its sibling translations and their
     * statuses are visible." Empty when the document has no translation_key
     * at all — normal, not an error (SPEC §7.3).
     *
     * @return list<DocumentSummary>
     */
    public function translations(EditorDocument $document): array
    {
        if ($document->translationKey === null) {
            return [];
        }

        $summaries = $this->index->summaries()['documents'];

        return array_values(array_filter(
            $summaries,
            static fn (DocumentSummary $s): bool => $s->translationKey === $document->translationKey
                && $s->identifier !== $document->identifier,
        ));
    }

    public function save(EditorSaveRequest $request, string $authorName, string $authorEmail): EditorSaveOutcome
    {
        if (!in_array($request->language, $this->languages, true)) {
            return EditorSaveOutcome::invalid(["'{$request->language}' is not a configured language"]);
        }

        $existingRaw = null;

        if ($request->identifier === null) {
            $path = $this->deriveNewRelativePath($request);
            if ($path['path'] === null) {
                return EditorSaveOutcome::invalid($path['errors']);
            }

            $relativePath = $path['path'];
            $absolutePath = $this->absolutePathFor($relativePath);

            if (is_file($absolutePath)) {
                return EditorSaveOutcome::invalid(["a document already exists at '{$relativePath}'"]);
            }
        } else {
            $discovered = $this->findDiscovered($request->identifier);
            if ($discovered === null) {
                return EditorSaveOutcome::notFound($request->identifier);
            }

            $existingRaw   = $this->gateway->read($discovered->absolutePath);
            $currentSha256 = hash('sha256', $existingRaw);

            if ($currentSha256 !== $request->expectedSha256) {
                return EditorSaveOutcome::conflict($this->loadDiscovered($discovered), $existingRaw);
            }

            $relativePath = $discovered->relativePath;
            $absolutePath = $discovered->absolutePath;
        }

        $rendered = $this->render($request);

        try {
            $frontMatter = $this->frontMatterParser->parse($rendered, $request->kind, $relativePath);
        } catch (ContentException $e) {
            return EditorSaveOutcome::invalid([$e->getMessage()]);
        }

        $sha256 = hash('sha256', $rendered);
        $saved  = EditorDocument::fromParsed($relativePath, $request->language, $request->kind, $sha256, $frontMatter);

        if ($existingRaw === $rendered) {
            // Nothing actually changed once re-serialized — `git commit`
            // would fail with "nothing to commit," so skip it rather than
            // treat that as an error the operator needs to see.
            return EditorSaveOutcome::saved($saved, commitSha: null);
        }

        $this->writeAtomically($absolutePath, $rendered);

        $isCreate  = $request->identifier === null;
        $commitSha = $this->git->addAndCommit(
            [$relativePath],
            $this->commitMessage($relativePath, $isCreate),
            $authorName,
            $authorEmail,
        );

        $this->auditLog->record(new AuditLogEntry(new \DateTimeImmutable(), $authorName, $isCreate ? 'create' : 'update', $relativePath, $commitSha));
        $this->enqueueBuildIfPublished($saved->status);

        return EditorSaveOutcome::saved($saved, $commitSha);
    }

    /**
     * SPEC §12: "Language is chosen at document creation and determines the
     * file's location; changing it later is a move, and the editor
     * performs it as one rather than editing a field." A move always
     * requires a fresh, per-language slug (§5.4: "Slugs are per-language by
     * design") — there is no sense in which the old slug is still correct
     * once the language segment changes.
     */
    public function move(string $identifier, string $newLanguage, string $newSlug, string $authorName, string $authorEmail): EditorSaveOutcome
    {
        if (!in_array($newLanguage, $this->languages, true)) {
            return EditorSaveOutcome::invalid(["'{$newLanguage}' is not a configured language"]);
        }

        $discovered = $this->findDiscovered($identifier);
        if ($discovered === null) {
            return EditorSaveOutcome::notFound($identifier);
        }

        if ($newLanguage === $discovered->language) {
            return EditorSaveOutcome::invalid(['the document is already in this language — use save to edit it in place']);
        }

        $current = $this->loadDiscovered($discovered);
        $request = $current->asSaveRequest($newLanguage, $newSlug);

        $path = $this->deriveNewRelativePath($request);
        if ($path['path'] === null) {
            return EditorSaveOutcome::invalid($path['errors']);
        }

        $newRelativePath = $path['path'];
        $newAbsolutePath = $this->absolutePathFor($newRelativePath);

        if (is_file($newAbsolutePath)) {
            return EditorSaveOutcome::invalid(["a document already exists at '{$newRelativePath}'"]);
        }

        $rendered = $this->render($request);

        try {
            $frontMatter = $this->frontMatterParser->parse($rendered, $request->kind, $newRelativePath);
        } catch (ContentException $e) {
            return EditorSaveOutcome::invalid([$e->getMessage()]);
        }

        $this->writeAtomically($newAbsolutePath, $rendered);

        if (!@unlink($discovered->absolutePath)) {
            throw AdminException::documentWriteFailed($discovered->relativePath);
        }

        $commitSha = $this->git->addAndCommit(
            [$discovered->relativePath, $newRelativePath],
            "Move {$discovered->relativePath} to {$newRelativePath}",
            $authorName,
            $authorEmail,
        );

        $sha256 = hash('sha256', $rendered);
        $moved  = EditorDocument::fromParsed($newRelativePath, $newLanguage, $request->kind, $sha256, $frontMatter);

        $this->auditLog->record(new AuditLogEntry(new \DateTimeImmutable(), $authorName, 'move', $newRelativePath, $commitSha));
        $this->enqueueBuildIfPublished($moved->status);

        return EditorSaveOutcome::saved($moved, $commitSha);
    }

    private function enqueueBuildIfPublished(DocumentStatus $status): void
    {
        if ($status === DocumentStatus::Published) {
            $this->buildQueue->enqueue();
        }
    }

    /**
     * The relative path $request would live at once saved — the same path
     * save() itself resolves internally (an existing identifier's own
     * on-disk path, or a derived new one), exposed read-only so
     * PreviewRenderer (T30, SPEC §12 Path C) can splice an unsaved buffer
     * into the real corpus at the position it will occupy once saved,
     * without duplicating deriveNewRelativePath()'s rules.
     *
     * @return array{path: ?string, errors: list<string>}
     */
    public function previewPath(EditorSaveRequest $request): array
    {
        if ($request->identifier === null) {
            return $this->deriveNewRelativePath($request);
        }

        $discovered = $this->findDiscovered($request->identifier);
        if ($discovered === null) {
            return ['path' => null, 'errors' => ["'{$request->identifier}' no longer exists — it may have been deleted or edited elsewhere."]];
        }

        return ['path' => $discovered->relativePath, 'errors' => []];
    }

    private function loadDiscovered(DiscoveredDocument $discovered): EditorDocument
    {
        $raw         = $this->gateway->read($discovered->absolutePath);
        $sha256      = hash('sha256', $raw);
        $frontMatter = $this->frontMatterParser->parse($raw, $discovered->kind, $discovered->relativePath);

        return EditorDocument::fromParsed($discovered->relativePath, $discovered->language, $discovered->kind, $sha256, $frontMatter);
    }

    private function findDiscovered(string $identifier): ?DiscoveredDocument
    {
        foreach ($this->index->discover() as $document) {
            if ($document->relativePath === $identifier) {
                return $document;
            }
        }

        return null;
    }

    /**
     * @return array{path: ?string, errors: list<string>}
     */
    private function deriveNewRelativePath(EditorSaveRequest $request): array
    {
        $slug = trim($request->slug);
        if (!preg_match(FrontMatterParser::SLUG_PATTERN, $slug)) {
            return ['path' => null, 'errors' => ["invalid slug pattern: '{$slug}' does not match " . FrontMatterParser::SLUG_PATTERN]];
        }

        if ($request->kind === DocumentKind::Page) {
            // Flat only (SPEC §6.3's directory hierarchy is deliberately
            // not built here — see BUILD-ORDER.md T29's own note).
            return ['path' => "pages/{$request->language}/{$slug}.md", 'errors' => []];
        }

        $date = $request->date ?? '';
        if (!preg_match(FrontMatterParser::ISO8601_PATTERN, $date)) {
            return ['path' => null, 'errors' => ["'date' is not a valid ISO-8601 date: '{$date}'"]];
        }

        $datePart = substr($date, 0, 10);
        $year     = substr($date, 0, 4);

        return ['path' => "posts/{$request->language}/{$year}/{$datePart}-{$slug}.md", 'errors' => []];
    }

    /**
     * @return array<string, string|int|float|bool|list<string>|null>
     */
    private function buildEmitterFields(EditorSaveRequest $request): array
    {
        $fields = [
            'title'           => $request->title,
            'slug'            => $request->slug,
            'status'          => $request->status,
            'summary'         => $request->summary,
            'translation_key' => $request->translationKey,
            'updated'         => $request->updated,
            'image'           => $request->image,
            'image_alt'       => $request->imageAlt,
            'canonical'       => $request->canonical,
            'noindex'         => $request->noindex,
            'aliases'         => $request->aliases,
            'toc'             => $request->toc,
            'source_id'       => $request->sourceId,
        ];

        if ($request->kind === DocumentKind::Post) {
            $fields['date']   = $request->date;
            $fields['tags']   = $request->tags;
            $fields['series'] = $request->series;

            return $fields;
        }

        $fields['template']         = $request->template;
        $fields['nav_label']        = $request->navLabel;
        $fields['nav_order']        = $this->numericOrRaw($request->navOrder, asInt: true);
        $fields['nav_parent']       = $request->navParent;
        $fields['nav_group']        = $request->navGroup;
        $fields['sitemap_priority'] = $this->numericOrRaw($request->sitemapPriority, asInt: false);
        $fields['legal']            = $request->legal;

        return $fields;
    }

    /**
     * A numeric-looking string is converted to a real int/float so
     * FrontMatterEmitter emits it unquoted (see that class's own docblock
     * for why that matters). Anything else is passed through unchanged as
     * a string, so the round-trip parse below reports the *same* "must be
     * an integer/number" error FrontMatterParser already gives everywhere
     * else, rather than a second, differently-worded one invented here.
     */
    private function numericOrRaw(?string $value, bool $asInt): int|float|string|null
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        if ($asInt && preg_match('/^-?\d+$/', $trimmed)) {
            return (int) $trimmed;
        }

        if (!$asInt && is_numeric($trimmed)) {
            return (float) $trimmed;
        }

        return $value;
    }

    private function absolutePathFor(string $relativePath): string
    {
        return $this->contentRoot . '/' . $relativePath;
    }

    private function writeAtomically(string $absolutePath, string $contents): void
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw AdminException::documentWriteFailed($dir);
        }

        $tmp = $absolutePath . '.tmp-' . bin2hex(random_bytes(8));
        if (file_put_contents($tmp, $contents) === false) {
            throw AdminException::documentWriteFailed($absolutePath);
        }

        if (!rename($tmp, $absolutePath)) {
            @unlink($tmp);

            throw AdminException::documentWriteFailed($absolutePath);
        }
    }

    private function commitMessage(string $relativePath, bool $isCreate): string
    {
        return ($isCreate ? 'Create ' : 'Update ') . $relativePath;
    }
}
