<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\KbArticleDatePersistence;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

require_once __DIR__ . '/../../app/sources/kb_dates.php';

/** In-memory storage boundary for executing the production controller actions. */
final class DB
{
    public static array $article = [];
    public static array $items = [];
    public static array $trash = [];
    public static array $writes = [];
    public static bool $idOccupied = false;
    public static ?int $concurrentEdit = null;

    /** Resolve the known category without an installation database. */
    public static function queryFirstRow(string $sql, mixed ...$args): array
    {
        return str_contains($sql, 'kb_comments') ? ['id' => 9, 'kb_id' => 1, 'author_id' => 2] : ['id' => 7];
    }

    /** Exercise both restoration branches, including a new id after a collision. */
    public static function queryFirstField(string $sql, mixed ...$args): int
    {
        return self::$idOccupied ? 1 : 0;
    }

    /** Return the deleted article or its associated item ids. */
    public static function query(string $sql, mixed ...$args): array
    {
        return str_contains($sql, 'kb_items')
            ? array_map(static fn (int $id): array => ['item_id' => $id], self::$items)
            : [['increment_id' => 10, 'valeur' => json_encode(self::$trash)]];
    }

    /** Capture the actual article values written by the handler. */
    public static function insert(string $table, array $values): void
    {
        self::$writes[] = [$table, $values];
        if ($table === 'fixture_kb') {
            self::$article = $values + ['id' => self::$idOccupied ? 2 : 1, 'category' => 'Network'];
        }
    }

    /** Apply only the fields the handler actually updates. */
    public static function update(string $table, array $values, mixed ...$args): void
    {
        self::$writes[] = [$table, $values];
        if ($table === 'fixture_kb') {
            if (self::$concurrentEdit !== null) {
                self::$article['updated_at'] = self::$concurrentEdit;
            }
            self::$article = array_replace(self::$article, $values);
        }
    }

    /** Delete the article while keeping the independently recorded trash snapshot. */
    public static function delete(string $table, mixed ...$args): void
    {
        if ($table === 'fixture_kb') {
            self::$article = [];
        }
    }

    /** Return the id allocated by the fixture. */
    public static function insertId(): int
    {
        return self::$idOccupied ? 2 : 1;
    }
}

/** Use a deterministic server clock in the production action snippets. */
function time(): int { return 1000; }
/** Keep table resolution independent of real configuration. */
function prefixTable(string $table): string { return 'fixture_' . $table; }
/** Replace only transport encryption at the boundary. */
function prepareExchangedData(mixed $data, string $direction): mixed { return $direction === 'decode' ? $data : json_encode($data); }
/** Read the article from the test storage. */
function kbLoadRow(int $id): ?array { return DB::$article === [] ? null : DB::$article; }
/** Keep the actual date serialization in the controller's response. */
function kbBuildEntryPayload(array $article, mixed ...$args): array { return $article + \kbArticleDatePayload($article, []); }
/** Keep sanitization out of this persistence fixture. */
function kbSanitizeArticleContent(string $html): string { return $html; }
/** Return the stored links. */
function kbLoadAssociatedItemIds(int $id): array { return DB::$items; }
/** Preserve links hidden from the current editor, as the real access helper does. */
function kbBuildPersistedAssociatedItemIds(int $id, array $requested, mixed $session): array { return array_values(array_unique(array_merge($requested, DB::$items))); }
/** The fixture owns the edit lock. */
function kbEditionLockSaveStatus(int $id, int $user): array { return ['allowed' => true]; }
/** No other editor owns the fixture's article. */
function kbIsLocked(mixed ...$args): array { return ['status' => false]; }
/** External side effects are outside this storage regression. */
function kbInsertLog(mixed ...$args): void {}
/** External side effects are outside this storage regression. */
function emitKbEvent(mixed ...$args): void {}
/** External side effects are outside this storage regression. */
function tpQueueKnowledgeBasePublicationNotification(mixed ...$args): void {}
/** External side effects are outside this storage regression. */
function kbReleaseAllEditionLocks(mixed ...$args): void {}
/** Capture the production trash snapshot before the article is deleted. */
function kbInsertMiscRow(string $type, string $prefix, array $payload, mixed ...$args): void { DB::$trash = $payload; }
/** Decode a recorded trash snapshot. */
function kbMiscDecode(string $json): array { return json_decode($json, true); }
/** Attachment ids stay attached to the restored article outside this fixture. */
function kbReassignAttachmentsToKbId(mixed ...$args): void {}
/** Comment ids stay attached to the restored article outside this fixture. */
function kbReassignCommentsToKbId(mixed ...$args): void {}
/** Return an attachment belonging to the selected article. */
function kbFindAttachmentRowById(string $id): array { return ['payload' => ['kb_id' => 1]]; }
/** File deletion is external to the timestamp persistence. */
function kbDeleteAttachmentFileAndRow(mixed ...$args): void {}
/** Allow comments in the fixture. */
function kbCanComment(mixed ...$args): bool { return true; }
/** Allow comment deletion in the fixture. */
function kbCanDeleteComment(mixed ...$args): bool { return true; }
/** Keep input filtering outside this persistence regression. */
function kbSanitizeTextInput(string $text): string { return $text; }
/** Keep the upload storage boundary independent of the real attachment directory. */
function kbAttachmentBaseDirectory(array $settings): string { return sys_get_temp_dir(); }
/** Use a harmless attachment name in the storage fixture. */
function kbAttachmentSanitizeFilename(string $name): string { return $name; }
/** The fixture uses an allowed text attachment. */
function kbAttachmentExtensionIsAllowed(mixed ...$args): bool { return true; }
/** Use a deterministic attachment id without creating a file. */
function kbGenerateToken(): string { return 'kb-dates-test'; }
/** File inspection is outside this timestamp persistence regression. */
function kbSanitizeMimeType(string $path): string { return 'text/plain'; }
/** Retain the attachment's article identity. */
function kbAttachmentIntitule(int $id, string $attachment): string { return 'kb_' . $id . '_' . $attachment; }
/** Serialize attachment metadata at the storage boundary. */
function kbMiscEncode(array $payload): string { return json_encode($payload); }

/** Execute real save, comment, attachment and recycle-bin actions with a fake storage boundary. */
final class KbArticleDatePersistenceTest extends TestCase
{
    private static string $source;
    private ?UploadedFile $upload = null;
    private array $uploads = [];

    /** Load the actual access and persistence helpers without booting the HTTP handler. */
    public static function setUpBeforeClass(): void
    {
        self::$source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/kb.queries.php'));
        foreach (['kbIsAdmin', 'kbCanEdit', 'kbCanDelete', 'kbTrashEntry', 'kbMarkArticleUpdated'] as $function) {
            $start = strpos(self::$source, 'function ' . $function . '(');
            self::assertNotFalse($start);
            $end = strpos(self::$source, "\n}", $start) + 2;
            eval('namespace ' . __NAMESPACE__ . '; use Symfony\Component\HttpFoundation\Session\SessionInterface; '
                . substr(self::$source, $start, $end - $start));
        }
    }

    protected function setUp(): void
    {
        DB::$article = ['id' => 1, 'label' => 'R&amp;D O&#039;Brien &quot;x&quot; &lt;y&gt;',
            'description' => '<p>Body</p>', 'category' => 'Network', 'category_id' => 7,
            'author_id' => 2, 'anyone_can_modify' => 0, 'allow_comments' => 1,
            'created_at' => 100, 'updated_at' => 200];
        DB::$items = [];
        DB::$trash = [];
        DB::$writes = [];
        DB::$idOccupied = false;
        DB::$concurrentEdit = null;
    }

    /** Run the selected production switch branch without initializing the web application. */
    private function action(string $action, array $payload, int $user = 2, bool $admin = false): array
    {
        global $SETTINGS;
        $SETTINGS = [];
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturnCallback(static fn (string $key, mixed $default = null): mixed =>
            ['user-id' => $user, 'user-admin' => $admin ? 1 : 0, 'user-login' => 'alice', 'key' => 'session-key'][$key] ?? $default);
        $key = 'session-key';
        $data = $payload;
        $request = new Request([], ['kb_id' => 1], [], [], ['attachments' =>
            $this->uploads !== [] ? $this->uploads : ($this->upload === null ? [] : [$this->upload])]);
        $lang = new class {
            /** Translate error keys without loading a language catalog. */
            public function get(string $key): string { return $key; }
        };
        $antiXss = new class {
            /** Keep input filtering outside this persistence regression. */
            public function xss_clean(string $value): string { return $value; }
        };
        if (!defined(__NAMESPACE__ . '\KB_DESCRIPTION_MAX_BYTES')) {
            define(__NAMESPACE__ . '\KB_DESCRIPTION_MAX_BYTES', 4194304);
        }
        $start = strpos(self::$source, "    case '" . $action . "':");
        self::assertNotFalse($start);
        $end = strpos(self::$source, "\n    case '", $start + 1);
        self::assertNotFalse($end);
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . '; switch ($action) {' . substr(self::$source, $start, $end - $start) . '}');
            return json_decode((string) ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }
    }

    /** Dates supplied by the browser cannot replace the server's creation or modification time. */
    public function testCreationAndEffectiveEditUseServerDates(): void
    {
        $payload = DB::$article;
        $payload['id'] = 0;
        $payload['created_at'] = 9999;
        $payload['updated_at'] = 9999;
        $created = $this->action('save_kb', $payload);
        self::assertFalse($created['error']);
        self::assertSame(1000, DB::$article['created_at']);
        self::assertNull(DB::$article['updated_at']);
        $payload['id'] = 1;
        $payload['description'] = '<p>Edited</p>';
        $edited = $this->action('save_kb', $payload);
        self::assertFalse($edited['error']);
        self::assertSame(1000, DB::$article['created_at']);
        self::assertSame(1000, $edited['entry']['updated_at']);
    }

    /** Resaving identical values and reordered links preserves the previous timestamp. */
    public function testNoOpSavePreservesDatesAndHiddenAssociations(): void
    {
        DB::$items = [3, 1];
        $payload = DB::$article + ['associated_items' => [1]];
        self::assertFalse($this->action('save_kb', $payload)['error']);
        self::assertSame(100, DB::$article['created_at']);
        self::assertSame(200, DB::$article['updated_at']);
        $payload['associated_items'] = [1, 4];
        self::assertFalse($this->action('save_kb', $payload)['error']);
        self::assertSame(1000, DB::$article['updated_at']);
    }

    /** Existing ownership and administrator gates reject edits without touching dates. */
    public function testRejectedEditsDoNotChangeDates(): void
    {
        foreach ([[3, false], [2, true]] as [$user, $admin]) {
            self::assertTrue($this->action('save_kb', DB::$article, $user, $admin)['error']);
            self::assertSame([], DB::$writes);
            self::assertSame(200, DB::$article['updated_at']);
        }
    }

    /** A no-op save must not overwrite a timestamp committed by another request. */
    public function testNoOpDoesNotWriteBackAStaleTimestamp(): void
    {
        DB::$concurrentEdit = 700;
        self::assertFalse($this->action('save_kb', DB::$article)['error']);
        self::assertSame(700, DB::$article['updated_at']);
    }

    /** Comments are discussion activity; a successful attachment removal is an article edit. */
    public function testCommentsAndAttachmentRemovalHaveDifferentDateSemantics(): void
    {
        foreach ([['add_comment', ['kb_id' => 1, 'comment' => 'Hello']],
            ['delete_comment', ['kb_id' => 1, 'comment_id' => 9]]] as [$action, $payload]) {
            self::assertFalse($this->action($action, $payload)['error']);
            self::assertSame(200, DB::$article['updated_at']);
        }
        self::assertFalse($this->action('delete_attachment', ['kb_id' => 1, 'attachment_id' => 'file'])['error']);
        self::assertSame(100, DB::$article['created_at']);
        self::assertSame(1000, DB::$article['updated_at']);
    }

    /** Only a successfully persisted upload advances the date. */
    public function testUploadDatesAreWrittenOnlyAfterSuccess(): void
    {
        self::assertTrue($this->action('upload_attachment', [])['error']);
        self::assertSame(200, DB::$article['updated_at']);
        $this->upload = $this->createMock(UploadedFile::class);
        $this->upload->method('isValid')->willReturn(false);
        self::assertTrue($this->action('upload_attachment', [])['error']);
        self::assertSame(200, DB::$article['updated_at']);
        $this->upload = $this->createMock(UploadedFile::class);
        $this->upload->method('isValid')->willReturn(true);
        $this->upload->method('getClientOriginalName')->willReturn('article.txt');
        $this->upload->method('getSize')->willReturn(3);
        $this->upload->method('move')->willReturn(new File(__FILE__));
        $response = $this->action('upload_attachment', []);
        self::assertFalse($response['error']);
        self::assertSame(100, $response['entry']['created_at']);
        self::assertSame(1000, $response['entry']['updated_at']);
    }

    /** One request updates the article date once, including a batch that partially succeeds. */
    public function testUploadBatchTouchesArticleOnlyOnce(): void
    {
        $valid = $this->createMock(UploadedFile::class);
        $valid->method('isValid')->willReturn(true);
        $valid->method('getClientOriginalName')->willReturn('article.txt');
        $valid->method('getSize')->willReturn(3);
        $valid->method('move')->willReturn(new File(__FILE__));
        $invalid = $this->createMock(UploadedFile::class);
        $invalid->method('isValid')->willReturn(false);
        foreach ([[$valid, $valid], [$valid, $invalid], [$invalid, $valid]] as $files) {
            DB::$writes = [];
            DB::$article['updated_at'] = 200;
            $this->uploads = $files;
            $response = $this->action('upload_attachment', []);
            $stored = array_filter(DB::$writes, static fn (array $write): bool => $write[0] === 'fixture_misc');
            $touches = array_filter(DB::$writes, static fn (array $write): bool => $write[0] === 'fixture_kb');
            self::assertSame(in_array($invalid, $files, true), $response['error']);
            self::assertCount($stored === [] ? 0 : 1, $touches);
            self::assertSame($stored === [] ? 200 : 1000, DB::$article['updated_at']);
            self::assertSame(100, DB::$article['created_at']);
        }
    }

    /** A later storage exception cannot leave already stored attachments without a dated edit. */
    public function testPartialUploadStorageFailureStillRecordsOneEdit(): void
    {
        $valid = $this->createMock(UploadedFile::class);
        $valid->method('isValid')->willReturn(true);
        $valid->method('getClientOriginalName')->willReturn('first.txt');
        $valid->method('getSize')->willReturn(3);
        $valid->method('move')->willReturn(new File(__FILE__));
        $failing = $this->createMock(UploadedFile::class);
        $failing->method('isValid')->willReturn(true);
        $failing->method('getClientOriginalName')->willReturn('second.txt');
        $failing->method('getSize')->willReturn(3);
        $failing->method('move')->willThrowException(new \RuntimeException('Test storage failure'));
        $this->uploads = [$valid, $failing];
        try {
            $this->action('upload_attachment', []);
            self::fail('The storage exception must propagate');
        } catch (\RuntimeException $exception) {
            self::assertSame('Test storage failure', $exception->getMessage());
        }
        self::assertCount(1, array_filter(DB::$writes, static fn (array $write): bool => $write[0] === 'fixture_kb'));
        self::assertSame(1000, DB::$article['updated_at']);
    }

    /** Both restoration paths keep the recorded dates rather than using the restore time. */
    public function testDeleteAndRestorePreserveDatesEvenWhenTheIdChanges(): void
    {
        self::assertFalse($this->action('delete_kb', ['id' => 1])['error']);
        self::assertSame([], DB::$article);
        self::assertSame(100, DB::$trash['created_at']);
        self::assertSame(200, DB::$trash['updated_at']);
        foreach ([false, true] as $collision) {
            DB::$idOccupied = $collision;
            self::assertFalse($this->action('restore_deleted_kbs', ['ids' => [10]], 2, true)['error']);
            self::assertSame($collision ? 2 : 1, DB::$article['id']);
            self::assertSame(100, DB::$article['created_at']);
            self::assertSame(200, DB::$article['updated_at']);
        }
        unset(DB::$trash['created_at'], DB::$trash['updated_at']);
        $this->action('restore_deleted_kbs', ['ids' => [10]], 2, true);
        self::assertNull(DB::$article['created_at']);
        self::assertNull(DB::$article['updated_at']);
    }
}
