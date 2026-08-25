<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guards on how a personal folder is recognised.
 *
 * getAllPersonalFolderIds() returns the folders whose own personal_folder flag is set. That is
 * not the same set as "the folders sitting in a personal tree": a sub-folder created under a
 * personal root keeps the flag at 0 when it was never written (legacy data, copy_folder, import).
 *
 * Every place that uses the list to keep personal objects OUT of something must therefore resolve
 * it by containment - getPersonalFolderIdsWithDescendants() - or the objects it means to protect
 * leak through the hole. items.perso is no fallback either: it is 0 on items created while the
 * client sent folder_is_personal = 0, which is exactly why the exclusion is folder based.
 */
class PersonalFolderContainmentTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . ltrim($relativePath, '/');
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }

    /**
     * Source of one method, bounded by the next method declaration, so an assertion about it
     * cannot accidentally read its neighbours.
     */
    private function methodBody(string $source, string $method): string
    {
        $start = strpos($source, 'private function ' . $method);
        self::assertIsInt($start, 'Method ' . $method . '() not found.');

        $next = strpos($source, "\n    private function ", $start + 1);

        return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
    }

    public function testRestoreSharekeysScopesSelectPersonalObjectsByContainment(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function restoreSharekeysScopeDefs\(bool \$personal = false\): array\s*\{\s*\$personalFolders = getPersonalFolderIdsWithDescendants\(\);/s',
            $mainFunctions,
            'A flag-based list lets the repair task fan a personal item out to every user (SEC-8).'
        );

        // The items scope carries the object itself (alias "o"), fields and files join their parent
        // item (alias "i"): every scope must be filtered, on its own alias.
        self::assertMatchesRegularExpression(
            '/function restoreSharekeysScopeDefs\(.*?\$scopeTest\(\'o\'\).*?\$scopeTest\(\'i\'\).*?\$scopeTest\(\'i\'\)/s',
            $mainFunctions,
            'An unfiltered scope hands its personal objects to every eligible user.'
        );
    }

    public function testSharedAndPersonalScopesArePartitionedByOneNegatedPredicate(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        // One predicate and its negation: an object belongs to exactly one scope, so none can fall
        // outside both and stay invisible to the tool.
        self::assertMatchesRegularExpression(
            '/return \$personal === true \? \'\(\' \. \$isPersonal \. \'\)\' : \'NOT \(\' \. \$isPersonal \. \'\)\';/',
            $mainFunctions
        );
        // An item whose folder was deleted has id_tree = NULL: without COALESCE the predicate and
        // its negation are both NULL and the object belongs to neither scope.
        self::assertMatchesRegularExpression(
            '/COALESCE\(\' \. \$alias \. \'\.id_tree, 0\) IN \(/',
            $mainFunctions
        );
    }

    public function testRestoreSharekeysScopesAreDefinedOnlyOnce(): void
    {
        // The Tools analysis is read as a prediction of what the repair task will do, so the two
        // must describe the same object set. One definition, shared, is what guarantees it.
        self::assertStringContainsString(
            'function restoreSharekeysScopeDefs(bool $personal = false): array',
            $this->source('app/sources/main.functions.php')
        );
        self::assertStringNotContainsString(
            'function restoreSharekeysScopeDefs',
            $this->source('app/sources/tools.queries.php'),
            'A second copy is how the analysis and the repair drifted apart in the first place.'
        );
    }

    public function testPersonalRepairIsOwnerOnlyAndNeverFansOut(): void
    {
        $trait = $this->source('app/scripts/traits/SharekeysRepairTrait.php');

        self::assertStringContainsString(
            'foreach (restoreSharekeysScopeDefs(true) as $scopeName => $def) {',
            $trait,
            'The personal pass must run on the personal scope, not on a filtered shared one.'
        );

        // batchUpsertSharekeys() is the fan-out primitive: one call per object, N rows. It must
        // stay in the shared pass only - a personal object gets exactly one row, for its owner.
        $personalPass = $this->methodBody($trait, 'restorePersonalScopeSharekeys');
        self::assertStringNotContainsString(
            'batchUpsertSharekeys(',
            $personalPass,
            'Fanning a personal object out to every eligible user is SEC-8, the leak this guards.'
        );
        self::assertStringContainsString('personalSharekeyKeepList($ownerId, $systemUserIds)', $personalPass);
    }

    public function testPersonalRepairRefusesToGuessAnOwner(): void
    {
        $trait = $this->source('app/scripts/traits/SharekeysRepairTrait.php');

        self::assertStringContainsString('personalOwnerConflictsWithCreator(', $trait);
        self::assertMatchesRegularExpression(
            '/if \(isset\(\$tpRefs\[\$objectId\]\) === false\) \{\s*\+\+\$stats\[\'no_reference\'\];\s*continue;/s',
            $trait,
            'With no reference key the foreign sharekeys are the only way back: leave them alone.'
        );
    }

    public function testPersonalFolderOwnersMapReadsTheAbsoluteRootOnly(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        // Sub-folders of a personal tree carry personal_folder = 1 too, so matching every flagged
        // ancestor reports several owners for one folder and loses it as ambiguous.
        self::assertMatchesRegularExpression(
            '/function personalFolderOwnersMap\(\).*?personal_root\.personal_folder = %i\s*AND personal_root\.parent_id = %i/s',
            $mainFunctions
        );
        self::assertMatchesRegularExpression(
            '/function personalFolderOwnersMap\(\).*?\$ownerId = personalRootOwnerId\(/s',
            $mainFunctions,
            'The owner decision stays in the shared, unit-tested logic module.'
        );
    }

    public function testSharekeysRepairTaskBuildsOnTheSharedScopes(): void
    {
        $trait = $this->source('app/scripts/traits/SharekeysRepairTrait.php');

        self::assertStringContainsString(
            'foreach (restoreSharekeysScopeDefs() as $scopeName => $def) {',
            $trait
        );
        self::assertStringNotContainsString('getAllPersonalFolderIds()', $trait);
        self::assertStringNotContainsString(
            'n.personal_folder = 0',
            $trait,
            'The nested_tree join tested the folder flag, which is the bug being guarded here.'
        );

        // The task must not name the object sources any more: that is the shared definition's job.
        foreach (["prefixTable('items')", "prefixTable('categories_items')", "prefixTable('files')"] as $source) {
            self::assertStringNotContainsString($source, $trait);
        }
    }

    public function testForeignPersonalFolderListIsResolvedByContainment(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function getForeignPersonalFolderIds\(int \$userId\): array\s*\{\s*\$allPersonalFolders = getPersonalFolderIdsWithDescendants\(\);/s',
            $mainFunctions,
            'This list is subtracted when keys are redistributed from TP_USER; a missing folder '
            . 'hands the target user a sharekey on another user\'s personal items.'
        );
    }

    public function testContainmentHelperKeepsUsingTheNestedTreeBounds(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function getPersonalFolderIdsWithDescendants\(\): array.*?folder\.nleft >= personal_root\.nleft.*?folder\.nright <= personal_root\.nright/s',
            $mainFunctions,
            'Containment is the MPTT bound check; anything else reintroduces the flag dependency.'
        );
    }
}
