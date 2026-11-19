<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guards for the personal flag of new_item and update_item (#5469).
 *
 * The item form sent `folder_is_personal` from the folder being browsed while the folder id
 * came from the form's own folder list. Picking a shared folder from a personal one stored a
 * shared item as personal, with keys for its creator only; the other way round, a personal
 * item had its keys fanned out to every user. The flag decides who receives the keys, so it
 * must be read from the target folder on the server.
 */
class ItemPersonalFlagSourceTest extends TestCase
{
    /**
     * Returns one case of items.queries.php, from its label to the next top-level case.
     */
    private function caseBlock(string $case): string
    {
        $content = file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        self::assertIsString($content);

        $label = "\n    case '" . $case . "':";
        $start = strpos($content, $label);
        self::assertNotFalse($start, 'The ' . $case . ' case must exist.');
        $end = strpos($content, "\n    case '", $start + strlen($label));
        self::assertNotFalse($end, 'A case must follow ' . $case . '.');

        return substr($content, $start, $end - $start);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function itemWriteCases(): array
    {
        return [
            'new_item' => ['new_item'],
            'update_item' => ['update_item'],
        ];
    }

    /**
     * @dataProvider itemWriteCases
     */
    public function testPersonalFlagIsNeverReadFromTheClient(string $case): void
    {
        self::assertStringNotContainsString(
            "\$dataReceived['folder_is_personal']",
            $this->caseBlock($case),
            'The client flag describes the browsed folder, not the target one.'
        );
    }

    /**
     * @dataProvider itemWriteCases
     */
    public function testPersonalFlagComesFromTheTargetFolder(string $case): void
    {
        self::assertMatchesRegularExpression(
            "/\\\$targetFolderIdentity = getFolderIdentityWithPersonalFlag\(\(int\) \\\$inputData\['folderId'\]\)|"
            . "\\\$targetFolderIdentity = getFolderIdentityWithPersonalFlag\(\\\$inputData\['folderId'\]\)/",
            $this->caseBlock($case)
        );
        self::assertStringContainsString(
            "\$post_folder_is_personal = \$targetFolderIdentity['personal_folder'];",
            $this->caseBlock($case)
        );
    }

    public function testEditFormMoveOutOfAPersonalFolderRedistributesTheKeys(): void
    {
        $block = $this->caseBlock('update_item');

        self::assertStringContainsString(
            'movePersonalItemToSharedFolderSynchronously(',
            $block,
            'A personal item saved into a shared folder must hand its keys to that folder.'
        );
        self::assertStringContainsString(
            'laprItemsPersonalMoveBlocker(',
            $block,
            'A LAPR item must not reach a personal folder through the edit form either.'
        );
    }

    public function testFormNoLongerSendsTheBrowsedFolderFlag(): void
    {
        $content = file_get_contents(__DIR__ . '/../../app/pages/items.js.php');
        self::assertIsString($content);

        self::assertStringNotContainsString("'folder_is_personal':", $content);
    }
}
