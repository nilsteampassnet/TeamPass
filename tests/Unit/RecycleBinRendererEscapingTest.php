<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: the recycle-bin renderer must encode every server value — regression guard for
 * GHSA-r298-6mxv-j9hc.
 *
 * The page builds table rows by concatenating server values into an HTML string and injecting
 * it with jQuery .html(). The deleted-item rows interpolated the label, its folder path and
 * the deleting user verbatim, so a crafted item label executed in the browser of the
 * administrator, manager or HR user opening Utilities > Deletion. The neighbouring knowledge-base
 * rows already used htmlEncode(); the item and folder rows did not.
 *
 * This guard is the second layer: the API no longer stores raw markup, but the recycle bin must
 * stay safe whatever a row happens to contain (legacy data, import, LDAP sync, direct DB write).
 */
class RecycleBinRendererEscapingTest extends TestCase
{
    private function rendererSource(): string
    {
        $path = __DIR__ . '/../../app/pages/utilities.deletion.js.php';
        self::assertFileExists($path, 'utilities.deletion.js.php not found');
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    /**
     * The exact sink named by the advisory.
     */
    public function testDeletedItemRowsEncodeEveryServerValue(): void
    {
        $src = $this->rendererSource();

        self::assertStringContainsString(
            "htmlEncode(value.path ? value.path : value.label)",
            $src,
            'The deleted item label/path is attacker-controlled and must be encoded'
        );
        self::assertStringContainsString(
            "htmlEncode(value.folder_path ? value.folder_path : value.folder_label)",
            $src,
            'The folder path of a deleted item must be encoded'
        );
        self::assertStringContainsString('htmlEncode(value.name)', $src, 'The deleting user name must be encoded');
        self::assertStringContainsString('htmlEncode(value.login)', $src, 'The deleting user login must be encoded');
    }

    /**
     * The folder rows shared the same sink: the label was interpolated raw while the date and
     * user next to it were already encoded.
     */
    public function testDeletedFolderLabelIsEncoded(): void
    {
        $src = $this->rendererSource();

        self::assertStringContainsString(
            'htmlEncode(folderLabel)',
            $src,
            'The deleted folder label must be encoded'
        );
        self::assertStringNotContainsString(
            "'<td class=\"font-weight-bold\">' + folderLabel + folderExtra",
            $src,
            'The folder label must not be interpolated raw'
        );
    }

    /**
     * The raw interpolations must not come back.
     */
    public function testNoRawInterpolationRemainsInTheRows(): void
    {
        $src = $this->rendererSource();

        foreach ([
            "'<td class=\"font-weight-bold\">' + (value.path ? value.path : value.label) + '</td>'",
            "</i>' + value.date + '</td>'",
            "</i>' + value.name + ' [' + value.login + ']</td>'",
        ] as $rawSink) {
            self::assertStringNotContainsString(
                $rawSink,
                $src,
                'A server value is interpolated without encoding: ' . $rawSink
            );
        }
    }
}
