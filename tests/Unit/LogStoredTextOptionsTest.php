<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\LogStoredTextOptions;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../Fixtures/stored_text_search_db.php';
require_once __DIR__ . '/../../app/sources/stored_text_search.php';
require_once __DIR__ . '/../../app/sources/log_display_logic.php';

const OTV_USER_ID = 9901;
const TP_USER_ID = 9902;
const SSH_USER_ID = 9903;
const API_USER_ID = 9904;

/** Keep metadata and cursors isolated from the installed application. */
class DB extends \StoredTextSearchFixtureDatabase {}

/** Verify support for non-default table prefixes. */
function prefixTable(string $table): string { return 'log_options_fixture_' . $table; }

/** Filter options use decoded matching before both page boundaries and the more flag. */
#[RequiresPhpExtension('sqlite3')]
class LogStoredTextOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        DB::reset();
        DB::$connection->exec('CREATE TABLE log_options_fixture_users (id INTEGER PRIMARY KEY,login TEXT,name TEXT,lastname TEXT);
            CREATE TABLE log_options_fixture_nested_tree (id INTEGER PRIMARY KEY,title TEXT,nlevel INTEGER,nleft INTEGER);');
        $user = DB::$connection->prepare('INSERT INTO log_options_fixture_users VALUES (:id,:login,:name,"")');
        $folder = DB::$connection->prepare('INSERT INTO log_options_fixture_nested_tree VALUES (:id,:name,2,:id)');
        foreach (range(1, 165) as $id) {
            $label = $id <= 100 ? 'R&amp;D &quot;Lab&quot;' : 'Ampere ' . sprintf('%05d', $id);
            $user->bindValue(':id', $id, SQLITE3_INTEGER);
            $user->bindValue(':login', $id <= 100 ? 'user' . $id : 'ampuser' . sprintf('%05d', $id));
            $user->bindValue(':name', $label);
            $user->execute();
            $folder->bindValue(':id', $id, SQLITE3_INTEGER);
            $folder->bindValue(':name', $label);
            $folder->execute();
        }
        DB::$connection->exec("INSERT INTO log_options_fixture_users VALUES (9901,'amp_system','Amp',''),(0,'amp_zero','Amp',''),
            (500,'O&apos;Brien','R&amp;D',''),(501,'O&#039;Brien','R&amp;D','');
            INSERT INTO log_options_fixture_nested_tree VALUES (500,'O&apos;Brien',2,500),(501,'O&#039;Brien',2,501),
            (600,'&lt;Guide&gt;',2,600),(601,'&amp;lt;literal&amp;gt;',2,601);");
    }

    /** Execute the two real handlers, intercepting only their final process exit. */
    private function request(string $action, string $term, int $page = 1): array
    {
        $params = ['action' => $action];
        $request = Request::create('/', 'GET', ['term' => $term, 'page' => $page]);
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/logs.datatables.php');
        $start = strpos($source, "} elseif (isset(\$params['action']) && \$params['action'] === 'user_options')");
        $end = strpos($source, '/* ACTIVE AUTHENTICATION LOCKOUTS */', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . '; if (false) {' . str_replace('exit;', 'return;', substr($source, $start, $end - $start)) . '}');
            return json_decode((string) ob_get_contents(), true);
        } finally {
            ob_end_clean();
        }
    }

    /** Neither false entity candidates nor system accounts may occupy a user option slot. */
    public function testUserPagesAreDenseAndExcludeSystemIdentities(): void
    {
        $first = $this->request('user_options', 'amp');
        self::assertSame(range(101, 130), array_column($first['results'], 'id'));
        self::assertTrue($first['pagination']['more']);
        self::assertSame(range(131, 160), array_column($this->request('user_options', 'amp', 2)['results'], 'id'));
        $last = $this->request('user_options', 'amp', 3);
        self::assertSame(range(161, 165), array_column($last['results'], 'id'));
        self::assertFalse($last['pagination']['more']);
        self::assertSame([], $this->request('user_options', 'quot')['results']);
    }

    /** Hierarchy order is preserved while matching happens before folder pagination. */
    public function testFolderPagesUseDecodedNamesAndPreserveOrder(): void
    {
        self::assertSame(range(101, 130), array_column($this->request('folder_options', 'amp')['results'], 'id'));
        $last = $this->request('folder_options', 'amp', 3);
        self::assertSame(range(161, 165), array_column($last['results'], 'id'));
        self::assertFalse($last['pagination']['more']);
        self::assertSame([], $this->request('folder_options', 'quot')['results']);
    }

    /** Matching does not change the HTML-escaped Select2 response contract. */
    public function testApostrophesAndAngleBracketsRemainSearchableAndEscaped(): void
    {
        foreach (['user_options', 'folder_options'] as $action) {
            self::assertSame([500, 501], array_column($this->request($action, "O'Brien")['results'], 'id'));
        }
        $literal = $this->request('folder_options', '<Guide>');
        self::assertSame([600], array_column($literal['results'], 'id'));
        self::assertStringContainsString('&lt;Guide&gt;', $literal['results'][0]['text']);
        self::assertStringNotContainsString('<Guide>', $literal['results'][0]['text']);
        self::assertSame([601], array_column($this->request('folder_options', '<literal>')['results'], 'id'));
        self::assertSame([], $this->request('folder_options', '&lt;literal&gt;')['results']);
        self::assertSame(0, DB::$activeCursors);
    }

    /** Opening a hierarchy picker without a term retains its bounded SQL page. */
    public function testEmptyFolderLookupDoesNotStreamTheWholeHierarchy(): void
    {
        $result = $this->request('folder_options', '', 2);
        self::assertSame(range(31, 60), array_column($result['results'], 'id'));
        self::assertTrue($result['pagination']['more']);
        self::assertCount(1, DB::$calls);
        self::assertStringContainsString('LIMIT %i_logs_offset, %i_logs_limit', DB::$calls[0][0]);
    }
}
