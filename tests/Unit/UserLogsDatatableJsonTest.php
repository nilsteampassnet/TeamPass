<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: the user-logs datatable ("See log" on the Users page) must answer valid JSON.
 *
 * The answer used to be concatenated by hand. The block closing "aaData" was duplicated, so an
 * account without any log - typically one just created at its first LDAP login - received
 * '"aaData": [][]' and DataTables reported "Invalid JSON response". Labels were also inserted
 * with only their double quotes escaped, so a backslash or a control character broke it too.
 */
class UserLogsDatatableJsonTest extends TestCase
{
    private function datatableSource(): string
    {
        $path = __DIR__ . '/../../app/sources/users.logs.datatable.php';
        self::assertFileExists($path, 'users.logs.datatable.php not found');
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    public function testAnswerIsEncodedNotConcatenated(): void
    {
        $src = $this->datatableSource();

        self::assertMatchesRegularExpression(
            "/echo json_encode\\(\\[\\s*'sEcho'.*'aaData' => \\\$aaData,/s",
            $src,
            'The datatable answer must be produced by json_encode()'
        );

        self::assertStringNotContainsString(
            '"aaData": ',
            $src,
            'The datatable answer must not be concatenated by hand'
        );
    }
}
