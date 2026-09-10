<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Editor;

use Cuniform\Admin\AdminException;
use Cuniform\Admin\Audit\AuditLogReader;
use Cuniform\Admin\Audit\AuditLogWriter;
use Cuniform\Admin\Build\BuildRequestQueue;
use Cuniform\Admin\Editor\EditorDocumentStore;
use Cuniform\Admin\Editor\EditorSaveRequest;
use Cuniform\Admin\Editor\EditorSaveStatus;
use Cuniform\Admin\Editor\GitRepository;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use PHPUnit\Framework\TestCase;

final class EditorDocumentStoreTest extends TestCase
{
    private string $root;
    private string $contentRoot;
    private string $varRoot;
    private string $buildRequestPath;
    private string $auditLogPath;
    private EditorDocumentStore $store;

    protected function setUp(): void
    {
        $this->root        = sys_get_temp_dir() . '/cuniform_editorstore_' . uniqid();
        $this->contentRoot = $this->root . '/content';
        // Deliberately outside $this->root/the git repo it becomes below —
        // in a real deployment var/ is never inside the content git repo
        // either (SPEC §3: separate, sibling directories under the vhost
        // root), so a BuildRequestQueue write here must never show up as
        // an untracked file under content's own git status.
        $this->varRoot = sys_get_temp_dir() . '/cuniform_editorstore_var_' . uniqid();

        mkdir($this->contentRoot . '/posts/en/2026', 0o755, true);
        mkdir($this->contentRoot . '/posts/de/2026', 0o755, true);
        mkdir($this->contentRoot . '/pages/en', 0o755, true);

        $this->git($this->root, ['git', 'init', '-q', '-b', 'main']);

        file_put_contents(
            $this->contentRoot . '/posts/en/2026/2026-03-14-hello.md',
            "---\ntitle: \"Hello\"\nslug: \"hello\"\nstatus: \"published\"\nsummary: \"Hello summary\"\n"
            . "translation_key: \"greeting\"\ndate: \"2026-03-14T10:00:00+01:00\"\n---\nHello body.",
        );
        file_put_contents(
            $this->contentRoot . '/posts/de/2026/2026-03-14-hallo.md',
            "---\ntitle: \"Hallo\"\nslug: \"hallo\"\nstatus: \"draft\"\nsummary: \"Hallo summary\"\n"
            . "translation_key: \"greeting\"\ndate: \"2026-03-14T10:00:00+01:00\"\n---\nHallo body.",
        );
        file_put_contents(
            $this->contentRoot . '/posts/en/2026/2026-05-01-standalone.md',
            "---\ntitle: \"Standalone\"\nslug: \"standalone\"\nstatus: \"published\"\nsummary: \"S\"\n"
            . "date: \"2026-05-01T09:00:00+01:00\"\n---\nStandalone body.",
        );

        $this->git($this->contentRoot, ['git', 'add', '-A']);
        $this->git($this->contentRoot, ['git', '-c', 'user.name=Setup', '-c', 'user.email=setup@example.test', 'commit', '-q', '-m', 'Initial fixture content']);

        $this->buildRequestPath = $this->varRoot . '/build-requested';
        $this->auditLogPath     = $this->varRoot . '/log/audit.jsonl';

        $this->store = new EditorDocumentStore(
            $this->contentRoot,
            ['en', 'de'],
            'Europe/Berlin',
            new GitRepository($this->contentRoot),
            new BuildRequestQueue($this->buildRequestPath),
            new AuditLogWriter($this->auditLogPath),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        $this->removeDirectory($this->varRoot);
    }

    public function testLoadReturnsTheParsedDocumentWithItsSha256(): void
    {
        $doc = $this->store->load('posts/en/2026/2026-03-14-hello.md');

        self::assertSame('Hello', $doc->title);
        self::assertSame(DocumentStatus::Published, $doc->status);
        self::assertSame('greeting', $doc->translationKey);
        self::assertSame(64, strlen($doc->sha256));
    }

    public function testLoadThrowsForAnIdentifierNotInTheContentIndex(): void
    {
        $this->expectException(AdminException::class);

        $this->store->load('posts/en/2026/does-not-exist.md');
    }

    public function testLoadRefusesAnIdentifierThatIsNotExactlyWhatTheIndexDiscovered(): void
    {
        // Identifiers are resolved against DocumentIndex::discover() (SPEC
        // §13.2: "never a path from the request") — a differently-spelled
        // reference to the same real file is not accepted just because it
        // would also resolve on disk.
        $this->expectException(AdminException::class);
        $this->store->load('./posts/en/2026/2026-03-14-hello.md');
    }

    public function testTranslationsReturnsSiblingsInOtherLanguagesButNotItself(): void
    {
        $doc = $this->store->load('posts/en/2026/2026-03-14-hello.md');

        $translations = $this->store->translations($doc);

        self::assertCount(1, $translations);
        self::assertSame('de', $translations[0]->language);
        self::assertSame(DocumentStatus::Draft, $translations[0]->status);
    }

    public function testTranslationsIsEmptyWhenTheDocumentHasNoTranslationKey(): void
    {
        $doc = $this->store->load('posts/en/2026/2026-05-01-standalone.md');

        self::assertSame([], $this->store->translations($doc));
    }

    public function testPreviewPathOfAnExistingIdentifierIsItsOnDiskRelativePath(): void
    {
        $request = $this->postRequest(
            identifier: 'posts/en/2026/2026-03-14-hello.md',
            expectedSha256: 'irrelevant-for-preview',
            slug: 'hello',
            title: 'Hello',
            date: '2026-03-14T10:00:00+01:00',
        );

        $result = $this->store->previewPath($request);

        self::assertSame('posts/en/2026/2026-03-14-hello.md', $result['path']);
        self::assertSame([], $result['errors']);
    }

    public function testPreviewPathOfANewDocumentIsTheSameDerivedPathSaveWouldUse(): void
    {
        $request = $this->postRequest(identifier: null, expectedSha256: null, slug: 'brand-new', title: 'Brand New', date: '2026-06-01T09:00:00+01:00');

        $result = $this->store->previewPath($request);

        self::assertSame('posts/en/2026/2026-06-01-brand-new.md', $result['path']);
        self::assertSame([], $result['errors']);
        self::assertFileDoesNotExist($this->contentRoot . '/posts/en/2026/2026-06-01-brand-new.md', 'preview must never write anything');
    }

    public function testPreviewPathOfAnUnknownIdentifierIsAnError(): void
    {
        $request = $this->postRequest(
            identifier: 'posts/en/2026/does-not-exist.md',
            expectedSha256: null,
            slug: 'x',
            title: 'X',
            date: '2026-06-01T09:00:00+01:00',
        );

        $result = $this->store->previewPath($request);

        self::assertNull($result['path']);
        self::assertNotEmpty($result['errors']);
    }

    public function testSaveCreatesANewPostAtTheDerivedPathAndCommitsIt(): void
    {
        $request = $this->postRequest(identifier: null, expectedSha256: null, slug: 'new-post', title: 'New Post', date: '2026-04-01T09:00:00+01:00');

        $outcome = $this->store->save($request, 'Jane Operator', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Saved, $outcome->status);
        self::assertNotNull($outcome->commitSha);
        self::assertFileExists($this->contentRoot . '/posts/en/2026/2026-04-01-new-post.md');

        $log = $this->git($this->contentRoot, ['git', 'log', '-1', '--format=%an <%ae>%n%s']);
        self::assertSame("Jane Operator <jane@example.test>\nCreate posts/en/2026/2026-04-01-new-post.md\n", $log);
    }

    public function testSaveRefusesToCreateWhenTheDerivedPathAlreadyExists(): void
    {
        $request = $this->postRequest(identifier: null, expectedSha256: null, slug: 'hello', title: 'Duplicate', date: '2026-03-14T10:00:00+01:00');

        $outcome = $this->store->save($request, 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Invalid, $outcome->status);
        self::assertNotEmpty($outcome->errors);
    }

    public function testSaveUpdatesAnExistingDocumentWhenTheShaMatches(): void
    {
        $doc = $this->store->load('posts/en/2026/2026-03-14-hello.md');

        $request = $this->postRequest(
            identifier: $doc->identifier,
            expectedSha256: $doc->sha256,
            slug: 'hello',
            title: 'Hello Edited',
            date: '2026-03-14T10:00:00+01:00',
            translationKey: 'greeting',
            body: 'Edited body.',
        );

        $outcome = $this->store->save($request, 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Saved, $outcome->status);
        self::assertSame('Hello Edited', $outcome->document?->title);
        self::assertStringContainsString('Edited body.', (string) file_get_contents($this->contentRoot . '/posts/en/2026/2026-03-14-hello.md'));
    }

    public function testSaveRefusesAndReturnsAConflictWhenTheShaDoesNotMatch(): void
    {
        $doc = $this->store->load('posts/en/2026/2026-03-14-hello.md');

        // Someone else changes the file after the editor loaded it.
        file_put_contents(
            $this->contentRoot . '/posts/en/2026/2026-03-14-hello.md',
            str_replace('Hello body.', 'Changed by someone else.', (string) file_get_contents($this->contentRoot . '/posts/en/2026/2026-03-14-hello.md')),
        );

        $request = $this->postRequest(
            identifier: $doc->identifier,
            expectedSha256: $doc->sha256,
            slug: 'hello',
            title: 'Hello Edited',
            date: '2026-03-14T10:00:00+01:00',
            body: 'My edit.',
        );

        $outcome = $this->store->save($request, 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Conflict, $outcome->status);
        self::assertNotNull($outcome->current);
        self::assertNotNull($outcome->currentRaw);
        self::assertStringContainsString('Changed by someone else.', $outcome->currentRaw);

        // The write was refused — the concurrent edit is still on disk.
        self::assertStringContainsString(
            'Changed by someone else.',
            (string) file_get_contents($this->contentRoot . '/posts/en/2026/2026-03-14-hello.md'),
        );
    }

    public function testSaveReturnsInvalidWithoutWritingWhenFrontMatterFailsValidation(): void
    {
        $request = $this->postRequest(identifier: null, expectedSha256: null, slug: 'no-title', title: '', date: '2026-04-02T09:00:00+01:00');

        $outcome = $this->store->save($request, 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Invalid, $outcome->status);
        self::assertNotEmpty($outcome->errors);
        self::assertFileDoesNotExist($this->contentRoot . '/posts/en/2026/2026-04-02-no-title.md');
    }

    public function testSaveReturnsInvalidWhenImageIsSetWithoutImageAlt(): void
    {
        $request = new EditorSaveRequest(
            identifier: null,
            expectedSha256: null,
            language: 'en',
            kind: DocumentKind::Post,
            title: 'With Image',
            slug: 'with-image',
            status: 'draft',
            summary: 'S',
            translationKey: null,
            updated: null,
            image: '/media/2026/03/photo.jpg',
            imageAlt: null,
            canonical: null,
            noindex: false,
            aliases: [],
            toc: false,
            sourceId: null,
            date: '2026-04-03T09:00:00+01:00',
            tags: [],
            series: null,
            template: null,
            navLabel: null,
            navOrder: null,
            navParent: null,
            navGroup: null,
            sitemapPriority: null,
            legal: null,
            body: 'Body.',
        );

        $outcome = $this->store->save($request, 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Invalid, $outcome->status);
        self::assertStringContainsString('image_alt', $outcome->errors[0]);
    }

    public function testSaveReturnsNotFoundWhenTheIdentifierNoLongerExists(): void
    {
        $request = $this->postRequest(
            identifier: 'posts/en/2026/2026-03-14-hello.md',
            expectedSha256: 'irrelevant',
            slug: 'hello',
            title: 'X',
            date: '2026-03-14T10:00:00+01:00',
        );

        unlink($this->contentRoot . '/posts/en/2026/2026-03-14-hello.md');

        $outcome = $this->store->save($request, 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::NotFound, $outcome->status);
    }

    public function testSaveWithByteIdenticalContentSkipsTheGitCommit(): void
    {
        $doc = $this->store->load('posts/en/2026/2026-05-01-standalone.md');

        // First, save it back in place unchanged, so the on-disk bytes end
        // up exactly the emitter's own canonical serialization — otherwise
        // a second save could still differ from the original file's
        // formatting even with no real edit.
        $first = $this->store->save($doc->asSaveRequestForUpdate(), 'Jane', 'jane@example.test');
        self::assertSame(EditorSaveStatus::Saved, $first->status);
        self::assertNotNull($first->commitSha);
        self::assertNotNull($first->document);

        $reloaded = $this->store->load($first->document->identifier);
        $second   = $this->store->save($reloaded->asSaveRequestForUpdate(), 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Saved, $second->status);
        self::assertNull($second->commitSha);
    }

    public function testMoveWritesTheDocumentUnderTheNewLanguageAndRemovesTheOld(): void
    {
        $outcome = $this->store->move('posts/en/2026/2026-05-01-standalone.md', 'de', 'alleinstehend', 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Saved, $outcome->status);
        self::assertSame('de', $outcome->document?->language);
        self::assertFileDoesNotExist($this->contentRoot . '/posts/en/2026/2026-05-01-standalone.md');
        self::assertFileExists($this->contentRoot . '/posts/de/2026/2026-05-01-alleinstehend.md');

        $status = $this->git($this->contentRoot, ['git', 'status', '--porcelain']);
        self::assertSame('', trim($status));
    }

    public function testMoveToTheSameLanguageIsInvalid(): void
    {
        $outcome = $this->store->move('posts/en/2026/2026-05-01-standalone.md', 'en', 'still-standalone', 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Invalid, $outcome->status);
    }

    public function testMoveToAnAlreadyOccupiedPathIsInvalid(): void
    {
        // 'hello' (2026-03-14, en) moved to 'de' with slug 'hallo' would
        // derive the exact same path as the existing 2026-03-14 'hallo'
        // fixture (same date, same slug) — a real collision.
        $outcome = $this->store->move('posts/en/2026/2026-03-14-hello.md', 'de', 'hallo', 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Invalid, $outcome->status);
        self::assertFileExists($this->contentRoot . '/posts/en/2026/2026-03-14-hello.md');
    }

    public function testMoveOfAnUnknownIdentifierIsNotFound(): void
    {
        $outcome = $this->store->move('posts/en/2026/does-not-exist.md', 'de', 'whatever', 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::NotFound, $outcome->status);
    }

    public function testSavingANewDraftDoesNotEnqueueABuild(): void
    {
        $request = $this->postRequest(identifier: null, expectedSha256: null, slug: 'still-a-draft', title: 'Draft', date: '2026-04-04T09:00:00+01:00', status: 'draft');

        $this->store->save($request, 'Jane', 'jane@example.test');

        self::assertFileDoesNotExist($this->buildRequestPath, 'SPEC §10.5/§12: only a publish enqueues a build');
    }

    public function testSavingANewPublishedDocumentEnqueuesABuild(): void
    {
        $request = $this->postRequest(identifier: null, expectedSha256: null, slug: 'brand-new-published', title: 'Published', date: '2026-04-05T09:00:00+01:00', status: 'published');

        $outcome = $this->store->save($request, 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Saved, $outcome->status);
        self::assertFileExists($this->buildRequestPath);
    }

    public function testEditingAnAlreadyPublishedDocumentEnqueuesABuildEvenThoughStatusDidNotChange(): void
    {
        $doc = $this->store->load('posts/en/2026/2026-03-14-hello.md');
        self::assertSame(DocumentStatus::Published, $doc->status);

        $request = $this->postRequest(
            identifier: $doc->identifier,
            expectedSha256: $doc->sha256,
            slug: 'hello',
            title: 'Hello Edited',
            date: '2026-03-14T10:00:00+01:00',
            status: 'published',
        );

        $this->store->save($request, 'Jane', 'jane@example.test');

        self::assertFileExists($this->buildRequestPath, 'the live page changed and needs a rebuild, even though it was already published');
    }

    public function testSavingAScheduledDocumentDoesNotEnqueueABuild(): void
    {
        $request = $this->postRequest(identifier: null, expectedSha256: null, slug: 'later', title: 'Later', date: '2099-01-01T09:00:00+01:00', status: 'scheduled');

        $this->store->save($request, 'Jane', 'jane@example.test');

        self::assertFileDoesNotExist($this->buildRequestPath);
    }

    public function testSavingAnUnchangedPublishedDocumentDoesNotEnqueueABuild(): void
    {
        // Same double-round-trip as testSaveWithByteIdenticalContentSkipsTheGitCommit,
        // which needs the first save to normalize serialization before a
        // second, truly byte-identical save can skip the git commit — and,
        // for the same reason, skip enqueueing a pointless rebuild too.
        $doc = $this->store->load('posts/en/2026/2026-05-01-standalone.md');

        $first = $this->store->save($doc->asSaveRequestForUpdate(), 'Jane', 'jane@example.test');
        self::assertSame(EditorSaveStatus::Saved, $first->status);
        self::assertNotNull($first->document);
        self::assertFileExists($this->buildRequestPath, 'the first save is a real, published change');

        unlink($this->buildRequestPath);

        $reloaded = $this->store->load($first->document->identifier);
        $second   = $this->store->save($reloaded->asSaveRequestForUpdate(), 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Saved, $second->status);
        self::assertNull($second->commitSha);
        self::assertFileDoesNotExist($this->buildRequestPath, 'nothing actually changed on the second, byte-identical save');
    }

    public function testMovingAPublishedDocumentEnqueuesABuild(): void
    {
        $outcome = $this->store->move('posts/en/2026/2026-03-14-hello.md', 'de', 'hallo-neu', 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Saved, $outcome->status);
        self::assertFileExists($this->buildRequestPath);
    }

    public function testMovingADraftDocumentDoesNotEnqueueABuild(): void
    {
        $outcome = $this->store->move('posts/de/2026/2026-03-14-hallo.md', 'en', 'hallo-en', 'Jane', 'jane@example.test');

        self::assertSame(EditorSaveStatus::Saved, $outcome->status);
        self::assertFileDoesNotExist($this->buildRequestPath);
    }

    public function testSavingANewDocumentRecordsACreateAuditEntryWithTheCommitSha(): void
    {
        $request = $this->postRequest(identifier: null, expectedSha256: null, slug: 'audited-post', title: 'Audited', date: '2026-04-06T09:00:00+01:00');

        $outcome = $this->store->save($request, 'Jane Operator', 'jane@example.test');

        $entries = (new AuditLogReader($this->auditLogPath))->recent();
        self::assertCount(1, $entries);
        self::assertSame('Jane Operator', $entries[0]->actor);
        self::assertSame('create', $entries[0]->action);
        self::assertSame('posts/en/2026/2026-04-06-audited-post.md', $entries[0]->identifier);
        self::assertSame($outcome->commitSha, $entries[0]->commitSha);
    }

    public function testEditingAnExistingDocumentRecordsAnUpdateAuditEntry(): void
    {
        $doc     = $this->store->load('posts/en/2026/2026-03-14-hello.md');
        $request = $this->postRequest(identifier: $doc->identifier, expectedSha256: $doc->sha256, slug: 'hello', title: 'Hello Edited', date: '2026-03-14T10:00:00+01:00', status: 'published');

        $this->store->save($request, 'Jane', 'jane@example.test');

        $entries = (new AuditLogReader($this->auditLogPath))->recent();
        self::assertCount(1, $entries);
        self::assertSame('update', $entries[0]->action);
    }

    public function testMovingADocumentRecordsAMoveAuditEntry(): void
    {
        $this->store->move('posts/en/2026/2026-05-01-standalone.md', 'de', 'alleinstehend', 'Jane', 'jane@example.test');

        $entries = (new AuditLogReader($this->auditLogPath))->recent();
        self::assertCount(1, $entries);
        self::assertSame('move', $entries[0]->action);
        self::assertSame('posts/de/2026/2026-05-01-alleinstehend.md', $entries[0]->identifier);
    }

    public function testANoOpSaveRecordsNoAuditEntry(): void
    {
        $doc = $this->store->load('posts/en/2026/2026-05-01-standalone.md');

        $this->store->save($doc->asSaveRequestForUpdate(), 'Jane', 'jane@example.test');
        $this->store->save($this->store->load($doc->identifier)->asSaveRequestForUpdate(), 'Jane', 'jane@example.test');

        $entries = (new AuditLogReader($this->auditLogPath))->recent();
        self::assertCount(1, $entries, 'the second, byte-identical save produced no commit and so must add no audit entry');
    }

    private function postRequest(
        ?string $identifier,
        ?string $expectedSha256,
        string $slug,
        string $title,
        string $date,
        ?string $translationKey = null,
        string $body = 'Body.',
        string $status = 'draft',
    ): EditorSaveRequest {
        return new EditorSaveRequest(
            identifier: $identifier,
            expectedSha256: $expectedSha256,
            language: 'en',
            kind: DocumentKind::Post,
            title: $title,
            slug: $slug,
            status: $status,
            summary: 'Summary',
            translationKey: $translationKey,
            updated: null,
            image: null,
            imageAlt: null,
            canonical: null,
            noindex: false,
            aliases: [],
            toc: false,
            sourceId: null,
            date: $date,
            tags: [],
            series: null,
            template: null,
            navLabel: null,
            navOrder: null,
            navParent: null,
            navGroup: null,
            sitemapPriority: null,
            legal: null,
            body: $body,
        );
    }

    /**
     * @param list<string> $command
     */
    private function git(string $cwd, array $command): string
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process     = proc_open($command, $descriptors, $pipes, $cwd);
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $stdout === false ? '' : $stdout;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) && !is_link($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}
