<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\ImportFormat\ImportFormat;

/**
 * Unit tests for the ImportFormat parser used by the "Other managers" import
 * (feature F13).
 *
 * The class is pure (no DB/session/filesystem dependency): each parser turns a
 * raw third-party export into the normalised TeamPass import row shape
 * (label, login, pwd, url, description, folder).
 *
 * Covered:
 *   - parse() dispatch + unknown-format handling.
 *   - parseBitwarden(): folder mapping, login extraction, TOTP/card append,
 *     encrypted-export rejection, structural errors, root items.
 *   - parseLastpass(): header mapping, nested folders, secure-note URL sentinel.
 *   - parseOnePassword(): title/login mapping, tag-as-folder, header reordering.
 *   - parseKeepassxc(): "Root/" stripping, group path, field mapping.
 *   - CSV robustness: BOM stripping, quoted embedded newlines.
 *   - Rows without a label are dropped.
 */
class ImportFormatTest extends TestCase
{
    // =========================================================================
    // Dispatch
    // =========================================================================

    public function testSupportedFormats(): void
    {
        $this->assertSame(
            ['bitwarden', 'lastpass', '1password', 'keepassxc'],
            ImportFormat::supportedFormats()
        );
    }

    public function testParseUnknownFormatReturnsError(): void
    {
        $result = ImportFormat::parse('does-not-exist', 'whatever');
        $this->assertTrue($result['error']);
        $this->assertSame('import_error_unknown_format', $result['message']);
        $this->assertSame([], $result['items']);
    }

    public function testParseIsCaseAndSpaceInsensitive(): void
    {
        $csv = "url,username,password,totp,extra,name,grouping,fav\n"
             . "https://x.com,bob,secret,,note,My Site,,0\n";
        $result = ImportFormat::parse('  LastPass  ', $csv);
        $this->assertFalse($result['error']);
        $this->assertCount(1, $result['items']);
    }

    // =========================================================================
    // Bitwarden
    // =========================================================================

    public function testBitwardenMapsLoginAndFolder(): void
    {
        $json = json_encode([
            'encrypted' => false,
            'folders'   => [['id' => 'f1', 'name' => 'Work/Servers']],
            'items'     => [[
                'type'     => 1,
                'name'     => 'SSH root',
                'folderId' => 'f1',
                'notes'    => 'prod box',
                'login'    => [
                    'username' => 'root',
                    'password' => 'p@ss',
                    'uris'     => [['uri' => 'https://host.example']],
                ],
            ]],
        ]);

        $result = ImportFormat::parseBitwarden($json);
        $this->assertFalse($result['error']);
        $this->assertCount(1, $result['items']);

        $item = $result['items'][0];
        $this->assertSame('SSH root', $item['label']);
        $this->assertSame('root', $item['login']);
        $this->assertSame('p@ss', $item['pwd']);
        $this->assertSame('https://host.example', $item['url']);
        $this->assertSame('Work/Servers', $item['folder']);
        $this->assertSame('prod box', $item['description']);
    }

    public function testBitwardenNullFolderGoesToRoot(): void
    {
        $json = json_encode([
            'encrypted' => false,
            'folders'   => [],
            'items'     => [[
                'type'     => 1,
                'name'     => 'No folder',
                'folderId' => null,
                'login'    => ['username' => 'u', 'password' => 'p'],
            ]],
        ]);

        $result = ImportFormat::parseBitwarden($json);
        $this->assertSame('', $result['items'][0]['folder']);
    }

    public function testBitwardenAppendsTotpAndCardToNotes(): void
    {
        $json = json_encode([
            'encrypted' => false,
            'items'     => [[
                'type'  => 1,
                'name'  => 'With TOTP',
                'notes' => 'base note',
                'login' => ['username' => 'u', 'password' => 'p', 'totp' => 'JBSWY3DP'],
                'card'  => ['number' => '4111111111111111', 'code' => '123'],
            ]],
        ]);

        $result = ImportFormat::parseBitwarden($json);
        $description = $result['items'][0]['description'];

        $this->assertStringContainsString('base note', $description);
        $this->assertStringContainsString('TOTP: JBSWY3DP', $description);
        $this->assertStringContainsString('4111111111111111', $description);
    }

    public function testBitwardenSecureNoteHasNoLoginButKeepsNotes(): void
    {
        $json = json_encode([
            'encrypted' => false,
            'items'     => [[
                'type'  => 2,
                'name'  => 'A note',
                'notes' => 'secret memo',
            ]],
        ]);

        $result = ImportFormat::parseBitwarden($json);
        $item = $result['items'][0];

        $this->assertSame('A note', $item['label']);
        $this->assertSame('', $item['login']);
        $this->assertSame('', $item['pwd']);
        $this->assertSame('secret memo', $item['description']);
    }

    public function testBitwardenEncryptedExportIsRejected(): void
    {
        $json = json_encode([
            'encrypted' => true,
            'items'     => [],
        ]);

        $result = ImportFormat::parseBitwarden($json);
        $this->assertTrue($result['error']);
        $this->assertSame('import_error_encrypted_export', $result['message']);
    }

    public function testBitwardenInvalidJsonIsRejected(): void
    {
        $result = ImportFormat::parseBitwarden('{not valid json');
        $this->assertTrue($result['error']);
        $this->assertSame('import_error_invalid_structure', $result['message']);
    }

    public function testBitwardenMissingItemsKeyIsRejected(): void
    {
        $result = ImportFormat::parseBitwarden(json_encode(['folders' => []]));
        $this->assertTrue($result['error']);
        $this->assertSame('import_error_invalid_structure', $result['message']);
    }

    // =========================================================================
    // LastPass
    // =========================================================================

    public function testLastpassMapsColumnsAndNestedFolder(): void
    {
        $csv = "url,username,password,totp,extra,name,grouping,fav\n"
             . "https://bank.example,alice,s3cr3t,,my notes,Bank Login,Personal\\Banking,0\n";

        $result = ImportFormat::parseLastpass($csv);
        $this->assertFalse($result['error']);
        $item = $result['items'][0];

        $this->assertSame('Bank Login', $item['label']);
        $this->assertSame('alice', $item['login']);
        $this->assertSame('s3cr3t', $item['pwd']);
        $this->assertSame('https://bank.example', $item['url']);
        $this->assertSame('my notes', $item['description']);
        $this->assertSame('Personal\\Banking', $item['folder']);
    }

    public function testLastpassSecureNoteUrlSentinelIsStripped(): void
    {
        $csv = "url,username,password,totp,extra,name,grouping,fav\n"
             . "http://sn,,,,a secure note,My Note,Personal,0\n";

        $result = ImportFormat::parseLastpass($csv);
        $item = $result['items'][0];

        $this->assertSame('My Note', $item['label']);
        $this->assertSame('', $item['url']);
        $this->assertSame('a secure note', $item['description']);
    }

    public function testLastpassHandlesQuotedEmbeddedNewline(): void
    {
        $csv = "url,username,password,totp,extra,name,grouping,fav\n"
             . "https://x.example,joe,pw,,\"line1\nline2\",Multi,,0\n";

        $result = ImportFormat::parseLastpass($csv);
        $this->assertCount(1, $result['items']);
        $this->assertStringContainsString('line1', $result['items'][0]['description']);
        $this->assertStringContainsString('line2', $result['items'][0]['description']);
    }

    // =========================================================================
    // 1Password
    // =========================================================================

    public function testOnePasswordMapsTitleAndTagFolder(): void
    {
        $csv = "Title,Url,Username,Password,OTPAuth,Favorite,Archived,Tags,Notes\n"
             . "Gmail,https://gmail.com,me@x.example,pw123,,,,Email,some note\n";

        $result = ImportFormat::parseOnePassword($csv);
        $item = $result['items'][0];

        $this->assertSame('Gmail', $item['label']);
        $this->assertSame('me@x.example', $item['login']);
        $this->assertSame('pw123', $item['pwd']);
        $this->assertSame('https://gmail.com', $item['url']);
        $this->assertSame('some note', $item['description']);
        $this->assertSame('Email', $item['folder']);
    }

    public function testOnePasswordUsesFirstTagOnlyAsFolder(): void
    {
        $csv = "Title,Url,Username,Password,Tags,Notes\n"
             . "Item,https://x.example,u,p,\"Primary,Secondary\",note\n";

        $result = ImportFormat::parseOnePassword($csv);
        $this->assertSame('Primary', $result['items'][0]['folder']);
    }

    public function testOnePasswordWithoutTagsIsFlat(): void
    {
        $csv = "Title,Url,Username,Password,Notes\n"
             . "Item,https://x.example,u,p,note\n";

        $result = ImportFormat::parseOnePassword($csv);
        $this->assertSame('', $result['items'][0]['folder']);
    }

    public function testOnePasswordColumnReorderingIsHandled(): void
    {
        // Columns in a different order than the canonical export.
        $csv = "Notes,Password,Username,Title,Url\n"
             . "a note,secret,bob,My Account,https://acct.example\n";

        $result = ImportFormat::parseOnePassword($csv);
        $item = $result['items'][0];

        $this->assertSame('My Account', $item['label']);
        $this->assertSame('bob', $item['login']);
        $this->assertSame('secret', $item['pwd']);
        $this->assertSame('https://acct.example', $item['url']);
        $this->assertSame('a note', $item['description']);
    }

    // =========================================================================
    // KeePassXC
    // =========================================================================

    public function testKeepassxcMapsFieldsAndStripsRoot(): void
    {
        $csv = "\"Group\",\"Title\",\"Username\",\"Password\",\"URL\",\"Notes\",\"TOTP\",\"Icon\"\n"
             . "\"Root/Databases\",\"MySQL\",\"admin\",\"dbpw\",\"https://db.example\",\"prod\",\"\",\"0\"\n";

        $result = ImportFormat::parseKeepassxc($csv);
        $item = $result['items'][0];

        $this->assertSame('MySQL', $item['label']);
        $this->assertSame('admin', $item['login']);
        $this->assertSame('dbpw', $item['pwd']);
        $this->assertSame('https://db.example', $item['url']);
        $this->assertSame('prod', $item['description']);
        $this->assertSame('Databases', $item['folder']);
    }

    public function testKeepassxcBareRootBecomesEmptyFolder(): void
    {
        $csv = "\"Group\",\"Title\",\"Username\",\"Password\",\"URL\",\"Notes\"\n"
             . "\"Root\",\"Top item\",\"u\",\"p\",\"\",\"\"\n";

        $result = ImportFormat::parseKeepassxc($csv);
        $this->assertSame('', $result['items'][0]['folder']);
    }

    public function testKeepassxcDeepGroupPathPreserved(): void
    {
        $csv = "\"Group\",\"Title\",\"Username\",\"Password\",\"URL\",\"Notes\"\n"
             . "\"Root/A/B/C\",\"Deep\",\"u\",\"p\",\"\",\"\"\n";

        $result = ImportFormat::parseKeepassxc($csv);
        $this->assertSame('A/B/C', $result['items'][0]['folder']);
    }

    // =========================================================================
    // Cross-cutting
    // =========================================================================

    public function testRowsWithoutLabelAreDropped(): void
    {
        $csv = "Title,Url,Username,Password,Notes\n"
             . "Has label,https://x.example,u,p,n\n"
             . ",https://y.example,u2,p2,n2\n";

        $result = ImportFormat::parseOnePassword($csv);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Has label', $result['items'][0]['label']);
    }

    public function testCsvBomIsStripped(): void
    {
        $bom = "\xEF\xBB\xBF";
        $csv = $bom . "Title,Url,Username,Password,Notes\n"
             . "Item,https://x.example,u,p,n\n";

        $result = ImportFormat::parseOnePassword($csv);
        $this->assertCount(1, $result['items']);
        // Without BOM stripping the first header key would be "\xEF\xBB\xBFtitle"
        // and the title would not be picked up.
        $this->assertSame('Item', $result['items'][0]['label']);
    }

    public function testEmptyContentReturnsNoItems(): void
    {
        $result = ImportFormat::parseLastpass('');
        // No header → invalid structure.
        $this->assertTrue($result['error']);
        $this->assertSame('import_error_invalid_structure', $result['message']);
    }

    public function testHeaderOnlyReturnsEmptyItemList(): void
    {
        $result = ImportFormat::parseOnePassword("Title,Url,Username,Password,Notes\n");
        $this->assertFalse($result['error']);
        $this->assertSame([], $result['items']);
    }
}
