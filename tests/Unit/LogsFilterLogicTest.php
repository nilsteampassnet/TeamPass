<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../../app/vendor/sergeytsalkov/meekrodb/db.class.php';
require_once __DIR__ . '/../../app/sources/logs_filter_logic.php';

/** Test log filtering decisions without a database or the SQLite extension. */
class LogsFilterLogicTest extends TestCase
{
    /** Keep selectable fields aligned with the visible search choices. */
    public function testColumnSelectionOnlyAcceptsTheUiAllowList(): void
    {
        foreach (['i.id', 'i.label', 't.title', 'l.action'] as $column) {
            self::assertSame([$column], getItemLogSearchColumns($column));
        }
        self::assertSame(['u.login', 'u.name', 'u.lastname'], getItemLogSearchColumns('u.login'));
        foreach ([null, [], 'i.label) OR 1=1 --', 'u.name', 'unknown', 'l.date', 'l.raison', 't.personal_folder'] as $invalid) {
            self::assertSame(getItemLogSearchColumns('all'), getItemLogSearchColumns($invalid));
        }
        self::assertNotContains('l.date', getItemLogSearchColumns('all'));
    }

    /**
     * Build a canonical payload the way the handlers do, so a test never asserts on a shape the
     * normaliser would have rejected.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function filters(array $overrides = []): array
    {
        return logsNormalizeFilters(array_merge(
            ['source' => 'system', 'date_from' => '2026-09-01', 'date_to' => '2026-09-08'],
            $overrides
        ));
    }

    /**
     * Prefixed table names the item purge needs to reproduce the view's joins.
     *
     * @return array<string, string>
     */
    private function tables(): array
    {
        return ['items' => 'tp_items', 'users' => 'tp_users', 'nested_tree' => 'tp_nested_tree'];
    }

    /**
     * Build a purge scope the way the handler does.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{table: string, where: WhereClause}|null
     */
    private function purge(array $overrides = [], ?string $userLogin = null): ?array
    {
        return buildLogsPurgeFilter($this->filters($overrides), $userLogin, $this->tables());
    }

    /** Reject invalid scopes before any deletion is attempted. */
    public function testInvalidOrUnresolvablePurgeScopeIsRejected(): void
    {
        // The knowledge base keeps its own purge route, and an unknown source falls back to
        // 'system' rather than to a scope nobody selected.
        self::assertNull($this->purge(['source' => 'kb']));

        // A bounded date range is mandatory: without it the predicate would match the whole table.
        foreach ([['date_from' => ''], ['date_to' => ''], ['date_from' => 'today'],
            ['date_from' => '2026-09-08', 'date_to' => '2026-09-01']] as $dates) {
            self::assertNull($this->purge($dates));
        }

        // Facets a flat single-table DELETE cannot express refuse the purge instead of silently
        // widening it beyond the announced scope.
        self::assertNull($this->purge(['term' => 'admin']));
        self::assertNull($this->purge(['source' => 'items', 'folder_id' => 7]));
        self::assertNull($this->purge(['source' => 'items', 'scope' => 'personal']));

        // Failed authentications are matched by submitted login: no login, no purge at all.
        foreach ([null, ''] as $login) {
            self::assertNull($this->purge(['types' => ['failed'], 'user_id' => 42], $login));
        }
    }

    /** The purge deletes from the table the selected source lives in, never another one. */
    public function testPurgeTargetsTheTableOfTheSelectedSource(): void
    {
        self::assertSame('log_system', $this->purge()['table']);
        self::assertSame('log_items', $this->purge(['source' => 'items'])['table']);
    }

    /** A user filter spanning failed authentications and other types keeps both matching rules. */
    public function testUserScopeCombinesLoginAndIdMatchingAcrossTypes(): void
    {
        $filter = $this->purge(['types' => ['connections', 'failed'], 'user_id' => 42], 'jdoe');
        [$sql, $args] = $filter['where']->textAndArgs();

        // qui also stores IP addresses: a numeric comparison would match unrelated rows.
        self::assertStringContainsString('qui = %s', $sql);
        self::assertContains('42', $args);
        self::assertNotContains(42, $args, 'The user id must reach the query as a string.');
        self::assertContains('jdoe', $args);
        self::assertContains('jdoe | tp_src=api', $args);
        self::assertStringNotContainsString('id_user', $sql);
    }

    /** Without failed authentications in scope the login lookup is not required. */
    public function testUserScopeWithoutFailedTypesNeedsNoLogin(): void
    {
        $filter = $this->purge(['types' => ['connections', 'errors'], 'user_id' => 42]);
        self::assertNotNull($filter);
        [$sql, $args] = $filter['where']->textAndArgs();
        self::assertStringContainsString('qui = %s', $sql);
        self::assertNotContains('failed_auth', $args);
    }

    /** Web is the exact negation of the API predicate, so no row is counted twice nor lost. */
    public function testChannelWebIsTheStrictComplementOfChannelApi(): void
    {
        $predicates = [
            'system' => logsSystemApiPredicate('')[0],
            'items' => "COALESCE(raison, '') LIKE %ss",
        ];

        foreach ($predicates as $source => $predicate) {
            $api = $this->purge(['source' => $source, 'channel' => 'api'])['where']->textAndArgs();
            $web = $this->purge(['source' => $source, 'channel' => 'web'])['where']->textAndArgs();

            self::assertStringContainsString($predicate, $api[0]);
            self::assertSame($api[1], $web[1], 'Both channels must bind the same values.');
            // negateLast() wraps the clause, so Web is literally "NOT (the API predicate)".
            self::assertSame(
                str_replace('(' . $predicate . ')', '(NOT (' . $predicate . '))', $api[0]),
                $web[0]
            );
        }
    }

    /** A NULL column must not drop a row from the Web channel through three-valued logic. */
    public function testChannelPredicateNeutralisesNullColumns(): void
    {
        [$systemSql] = $this->purge(['channel' => 'web'])['where']->textAndArgs();
        self::assertStringContainsString("COALESCE(field_1, '')", $systemSql);
        self::assertStringContainsString("COALESCE(label, '')", $systemSql);

        [$itemsSql] = $this->purge(['source' => 'items', 'channel' => 'web'])['where']->textAndArgs();
        self::assertStringContainsString("COALESCE(raison, '')", $itemsSql);
    }

    /** Date ranges include the full last day, including daylight-saving transitions. */
    public function testDateRangeIncludesTheLastDayAndHandlesDst(): void
    {
        $timezone = date_default_timezone_get();
        try {
            date_default_timezone_set('Europe/Paris');
            foreach (['2026-09-08' => 24, '2026-03-29' => 23, '2026-10-25' => 25] as $day => $hours) {
                [$start, $end] = getLogsPurgeDateRange($day, $day);
                self::assertSame($hours * 3600, $end - $start);
                self::assertSame($day . ' 23:59:59', date('Y-m-d H:i:s', $end - 1));
            }
        } finally {
            date_default_timezone_set($timezone);
        }
        foreach ([['', ''], [null, null], [[], '2026-09-08'], ['2026-02-30', '2026-03-01'],
            ['2026-09-09', '2026-09-08'], ['2026-9-8', '2026-09-08'], ['today', 'tomorrow'], ["2026-09-08\0", '2026-09-08']] as [$start, $end]) {
            self::assertNull(getLogsPurgeDateRange($start, $end));
        }
    }

    /** Translated labels and stored codes remain searchable when wording changes. */
    public function testActionSearchUsesTheLanguageCatalogAndStoredCodes(): void
    {
        foreach (['english', 'french', 'arabic'] as $language) {
            $lang = new Language($language, __DIR__ . '/../../app/includes/language');
            foreach (['at_creation', 'at_shown', 'at_manual', 'at_access', 'at_password_copied', 'at_password_shown_edit_form'] as $action) {
                self::assertContains($action, getItemLogActionSearchCodes((string) $lang->get($action), $lang));
                self::assertContains($action, getItemLogActionSearchCodes(strtoupper($action), $lang));
            }
            self::assertSame([], getItemLogActionSearchCodes('no-such-action-5368', $lang));
        }
    }

    /**
     * The item view hides a log row whose item, author or folder has disappeared, so the purge
     * must not reach it either: it would destroy more than the table announced.
     */
    public function testItemPurgeIsNarrowedToTheRowsTheViewCanDisplay(): void
    {
        [$sql, $args] = $this->purge(['source' => 'items'])['where']->textAndArgs();

        self::assertStringContainsString('id_item IN (SELECT i.id FROM %l AS i INNER JOIN %l AS t ON (i.id_tree = t.id))', $sql);
        self::assertStringContainsString('id_user IN (SELECT id FROM %l)', $sql);
        self::assertContains('tp_items', $args);
        self::assertContains('tp_nested_tree', $args);
        self::assertContains('tp_users', $args);

        // Without those names the displayed scope cannot be reproduced, so the purge is refused
        // rather than run unnarrowed.
        foreach ([[], ['items' => 'tp_items'], ['items' => 'tp_items', 'users' => 'tp_users']] as $partial) {
            self::assertNull(buildLogsPurgeFilter($this->filters(['source' => 'items']), null, $partial));
        }

        // The system view uses a LEFT JOIN and hides nothing, so it needs no narrowing.
        [$systemSql] = $this->purge()['where']->textAndArgs();
        self::assertStringNotContainsString('SELECT id FROM', $systemSql);
    }

    /** Unknown keys never reach the query builders. */
    public function testFilterNormalisationDropsEverythingOutsideTheAllowLists(): void
    {
        $filters = logsNormalizeFilters([
            'source' => 'items) OR 1=1 --',
            'types' => ['connections', 'nope'],
            'actions' => ['at_copy', 'at_nope', 'DROP TABLE'],
            'user_id' => 'abc',
            'folder_id' => -3,
            'scope' => 'both',
            'channel' => 'carrier-pigeon',
            'unknown_facet' => 'kept?',
        ]);

        self::assertSame('system', $filters['source']);
        self::assertSame(['connections'], $filters['types']);
        // Item actions are not selectable under the system source.
        self::assertSame([], $filters['actions']);
        self::assertNull($filters['user_id']);
        self::assertNull($filters['folder_id']);
        self::assertSame('', $filters['scope']);
        self::assertSame('', $filters['channel']);
        self::assertArrayNotHasKey('unknown_facet', $filters);
    }

    /** An empty type selection means every type, never an empty IN () that silently matches none. */
    public function testEmptyTypeSelectionMeansEveryType(): void
    {
        foreach ([[], null, ['nope']] as $types) {
            self::assertSame(logsAllowedSystemTypes(), logsNormalizeFilters(['source' => 'system', 'types' => $types])['types']);
        }
        // Types belong to the system source only: another source must not carry one.
        self::assertSame([], logsNormalizeFilters(['source' => 'items', 'types' => ['connections']])['types']);
    }

    /** The historical "all users" sentinel of the purge form is not a user id. */
    public function testAllUsersSentinelResolvesToNoUserFilter(): void
    {
        foreach ([-1, '-1', 0, '0'] as $sentinel) {
            self::assertNull(logsNormalizeFilters(['user_id' => $sentinel])['user_id']);
        }
        self::assertSame(42, logsNormalizeFilters(['user_id' => '42'])['user_id']);
    }

    /** Folder and personal scope only exist on the item source. */
    public function testItemOnlyFacetsAreStrippedFromOtherSources(): void
    {
        foreach (['system', 'kb'] as $source) {
            $filters = logsNormalizeFilters(['source' => $source, 'folder_id' => 7, 'scope' => 'personal']);
            self::assertNull($filters['folder_id']);
            self::assertSame('', $filters['scope']);
        }
        $items = logsNormalizeFilters(['source' => 'items', 'folder_id' => 7, 'scope' => 'personal']);
        self::assertSame(7, $items['folder_id']);
        self::assertSame('personal', $items['scope']);
    }

    /** 'admin' is one interface choice covering two stored types. */
    public function testAdminTypeExpandsToBothStoredTypes(): void
    {
        self::assertSame(['admin_action', 'user_mngt'], logsSystemTypeDbValues(['admin']));
        self::assertSame(['user_connection', 'error'], logsSystemTypeDbValues(['connections', 'errors']));
        self::assertSame([], logsSystemTypeDbValues(['nope']));
        // Overlapping keys must not repeat a value in the IN () list.
        self::assertSame(['admin_action', 'user_mngt'], logsSystemTypeDbValues(['admin', 'admin']));
    }

    /** The column contract is the union of what the selected types feed, in a stable order. */
    public function testVisibleColumnsFollowTheSelectedTypes(): void
    {
        self::assertSame(['date', 'type', 'label', 'user'], logsVisibleColumns('system', ['errors']));
        self::assertSame(['date', 'type', 'label', 'user', 'source'], logsVisibleColumns('system', ['connections']));
        self::assertSame(
            ['date', 'type', 'label', 'user', 'ip', 'channel', 'actions'],
            logsVisibleColumns('system', ['failed'])
        );
        self::assertSame(['date', 'type', 'label', 'user', 'target'], logsVisibleColumns('system', ['admin']));

        // Order must not depend on the order the client sent its selection in.
        $union = ['date', 'type', 'label', 'user', 'source', 'ip', 'channel', 'actions', 'target'];
        self::assertSame($union, logsVisibleColumns('system', logsAllowedSystemTypes()));
        self::assertSame($union, logsVisibleColumns('system', ['admin', 'failed', 'errors', 'connections']));

        self::assertSame(
            ['date', 'id', 'label', 'folder', 'user', 'action', 'api', 'personal'],
            logsVisibleColumns('items', [])
        );
        self::assertSame(['date', 'label', 'user', 'action', 'details'], logsVisibleColumns('kb', []));
    }

    /** The read predicates carry every active facet, on the aliases the queries join. */
    public function testReadPredicatesCarryEveryActiveFacet(): void
    {
        [$systemSql, $systemArgs] = buildSystemLogFilter($this->filters([
            'types' => ['connections'], 'term' => 'jdoe', 'user_id' => 42, 'channel' => 'api',
        ]))->textAndArgs();
        self::assertStringContainsString('l.type IN %ls', $systemSql);
        self::assertStringContainsString('l.date >= %i', $systemSql);
        self::assertStringContainsString('l.date < %i', $systemSql);
        self::assertStringContainsString('l.qui = %s', $systemSql);
        self::assertStringContainsString('u.login LIKE %ss', $systemSql);
        self::assertContains('jdoe', $systemArgs);

        $lang = new Language('english', __DIR__ . '/../../app/includes/language');
        [$itemsSql] = buildItemLogFilter($this->filters([
            'source' => 'items', 'actions' => ['at_copy'], 'user_id' => 42,
            'folder_id' => 7, 'scope' => 'personal',
        ]), $lang)->textAndArgs();
        self::assertStringContainsString('l.action IN %ls', $itemsSql);
        self::assertStringContainsString('l.id_user = %i', $itemsSql);
        self::assertStringContainsString('i.id_tree = %i', $itemsSql);
        self::assertStringContainsString('t.personal_folder = %i', $itemsSql);
    }

    /** Name the facets that block a purge, so the interface can say what to clear. */
    public function testBlockingFacetsAreReportedByName(): void
    {
        self::assertSame([], logsPurgeBlockingFacets($this->filters()));
        self::assertSame(['term'], logsPurgeBlockingFacets($this->filters(['term' => 'x'])));
        self::assertSame(
            ['term', 'folder', 'scope'],
            logsPurgeBlockingFacets($this->filters(['source' => 'items', 'term' => 'x', 'folder_id' => 7, 'scope' => 'shared']))
        );
    }

    /**
     * An empty type list must never reach the query as an empty IN (): MeekroDB rejects it, so a
     * filter set built for another source would abort the request instead of matching nothing.
     */
    public function testSystemPredicateNeverBuildsAnEmptyTypeList(): void
    {
        // 'types' is legitimately empty in a payload normalized for another source, so handing
        // one of those to this builder is the realistic way to reach the empty list.
        foreach (['items', 'kb'] as $otherSource) {
            $filters = logsNormalizeFilters(['source' => $otherSource]);
            self::assertSame([], $filters['types']);

            [$sql, $args] = buildSystemLogFilter($filters)->textAndArgs();
            self::assertStringContainsString('l.type IN %ls', $sql);
            self::assertSame(logsSystemTypeDbValues(logsAllowedSystemTypes()), $args[0]);
            foreach ($args as $arg) {
                self::assertNotSame([], $arg);
            }
        }
    }

    /**
     * Facet options are ordered by what the reader sees, accents included.
     */
    public function testFacetOptionsAreOrderedByTranslatedLabel(): void
    {
        $labels = [
            'at_export' => 'Export',
            'at_access' => 'Demande d’accès',
            'at_moved' => 'Déplacé',
            'at_copy' => 'Copie faite',
        ];
        // 'Déplacé' belongs between 'Demande' and 'Export'; a byte comparison would push both
        // accented labels after every unaccented one.
        self::assertSame(
            ['at_copy', 'at_access', 'at_moved', 'at_export'],
            logsSortByLabel(array_keys($labels), $labels)
        );

        // A value with no label falls back to its own code rather than disappearing.
        self::assertSame(['aaa', 'zzz'], logsSortByLabel(['zzz', 'aaa'], []));

        foreach (['english', 'french'] as $language) {
            $lang = new Language($language, __DIR__ . '/../../app/includes/language');
            $actionLabels = [];
            foreach (logsAllowedItemActions() as $action) {
                $actionLabels[$action] = (string) $lang->get($action);
            }
            $sorted = logsSortByLabel(logsAllowedItemActions(), $actionLabels);

            self::assertSame(count(logsAllowedItemActions()), count($sorted));
            self::assertSame([], array_diff($sorted, logsAllowedItemActions()));
            // Ordering must not depend on the order the codes are declared in.
            self::assertSame($sorted, logsSortByLabel(array_reverse(logsAllowedItemActions()), $actionLabels));
        }

        $page = file_get_contents(__DIR__ . '/../../app/pages/utilities.logs.php');
        self::assertStringContainsString('$logSortedActions', $page);
        self::assertStringContainsString('$logSortedTypes', $page);
    }

    /**
     * The facet pickers are not DataTables sources and send no order parameter.
     *
     * strtoupper(null) is deprecated, and the notice lands in front of the JSON body, which the
     * picker reports as "the results could not be loaded" with nothing in the server log.
     */
    public function testSharedRequestPreambleToleratesANonDatatablesRequest(): void
    {
        $dataTable = file_get_contents(__DIR__ . '/../../app/sources/logs.datatables.php');

        self::assertStringContainsString(
            "\$order = strtoupper((string) (\$params['order'][0]['dir'] ?? ''));",
            $dataTable
        );
        self::assertStringNotContainsString("strtoupper(\$params['order'][0]['dir'] ?? null)", $dataTable);
        foreach (['user_options', 'folder_options'] as $action) {
            self::assertStringContainsString("\$params['action'] === '{$action}'", $dataTable);
        }
    }

    /**
     * The knowledge base answers the same facet questions as the SQL sources, in PHP.
     */
    public function testKnowledgeBaseRowsAnswerTheSameFacets(): void
    {
        $row = ['date' => 1_757_000_000, 'user_id' => 42, 'action' => 'at_modification'];
        $base = logsNormalizeFilters(['source' => 'kb']);
        self::assertTrue(kbLogRowMatchesFilters($row, $base));

        // The end boundary is the exclusive start of the next day, as in the SQL sources, so a row
        // logged during the last selected day is kept.
        $day = date('Y-m-d', $row['date']);
        self::assertTrue(kbLogRowMatchesFilters($row, logsNormalizeFilters(
            ['source' => 'kb', 'date_from' => $day, 'date_to' => $day]
        )));
        self::assertFalse(kbLogRowMatchesFilters($row, logsNormalizeFilters(
            ['source' => 'kb', 'date_from' => '2000-01-01', 'date_to' => '2000-01-02']
        )));

        self::assertTrue(kbLogRowMatchesFilters($row, logsNormalizeFilters(['source' => 'kb', 'user_id' => 42])));
        self::assertFalse(kbLogRowMatchesFilters($row, logsNormalizeFilters(['source' => 'kb', 'user_id' => 43])));

        self::assertTrue(kbLogRowMatchesFilters($row, logsNormalizeFilters(
            ['source' => 'kb', 'actions' => ['at_modification']]
        )));
        self::assertFalse(kbLogRowMatchesFilters($row, logsNormalizeFilters(
            ['source' => 'kb', 'actions' => ['at_creation']]
        )));

        // An action the knowledge base does not know is dropped by the allow-list, which must not
        // turn into "match nothing": the selection simply does not apply to this source.
        self::assertSame([], logsNormalizeFilters(['source' => 'kb', 'actions' => ['at_export']])['actions']);
        self::assertTrue(kbLogRowMatchesFilters($row, logsNormalizeFilters(
            ['source' => 'kb', 'actions' => ['at_export']]
        )));
    }

    /**
     * The knowledge base is never served by the SQL endpoint, and never purged unbounded.
     */
    public function testKnowledgeBaseKeepsItsOwnRoutes(): void
    {
        self::assertNull($this->purge(['source' => 'kb']));

        $dataTable = file_get_contents(__DIR__ . '/../../app/sources/logs.datatables.php');
        self::assertStringContainsString("if (\$filters['source'] === 'kb') {", $dataTable);

        $kb = file_get_contents(__DIR__ . '/../../app/sources/kb.queries.php');
        self::assertStringContainsString('kbLogRowMatchesFilters(', $kb);
        self::assertStringContainsString('logsNormalizeFilters(', $kb);
        // Its rows must be keyed like the column contract, or DataTables binds the wrong cells.
        foreach (logsVisibleColumns('kb', []) as $column) {
            self::assertStringContainsString("'{$column}' => ", $kb);
        }
        // A purge is bounded in time here too; the handler used to delete everything matching the
        // user and the action when both dates were left empty.
        self::assertStringContainsString("\$logFilters['date_from'] === null", $kb);
        self::assertStringContainsString('logsPurgeBlockingFacets($logFilters)', $kb);
    }

    /**
     * The knowledge base actions are a subset of the item ones, so the interface offers a single
     * list gated per option. Two lists repeated five identical labels under the same heading.
     */
    public function testActionFacetIsOneListGatedPerOption(): void
    {
        self::assertSame([], array_diff(logsAllowedKbActions(), logsAllowedItemActions()));

        $page = file_get_contents(__DIR__ . '/../../app/pages/utilities.logs.php');
        self::assertSame(1, substr_count($page, "\$lang->get('logs_facet_action')"));
        self::assertStringContainsString('logs-facet-option', $page);
        self::assertStringContainsString("in_array(\$action, logsAllowedKbActions(), true)", $page);

        // No two selectable actions may translate to the same label, or the single list shows a
        // duplicate the administrator cannot tell apart.
        $lang = new Language('english', __DIR__ . '/../../app/includes/language');
        $labels = array_map(
            static fn (string $action): string => (string) $lang->get($action),
            logsAllowedItemActions()
        );
        self::assertSame(count($labels), count(array_unique($labels)), 'Duplicate action labels: '
            . implode(', ', array_diff_assoc($labels, array_unique($labels))));
    }

    /**
     * A facet hidden by the selected source is skipped while collecting, never collected and then
     * deleted: both action-bearing sources feed the same facet key.
     */
    public function testClientSkipsHiddenFacetsInsteadOfDroppingTheKey(): void
    {
        $javascript = file_get_contents(__DIR__ . '/../../app/pages/utilities.logs.js.php');

        self::assertStringContainsString(
            "return \$(this).closest('.logs-facet-group.hidden, .logs-facet-option.hidden').length === 0",
            $javascript
        );
        self::assertStringNotContainsString('delete filters[$(this).data(', $javascript);
        // The source is part of the table signature, or a rebuild could keep the old endpoint.
        self::assertStringContainsString("const signature = source + ':' + columns.join(',')", $javascript);
    }

    /** Source assertions check wiring; the helpers' behavior is tested directly. */
    public function testHandlersUseTheTestedFilteringHelpers(): void
    {
        $dataTable = file_get_contents(__DIR__ . '/../../app/sources/logs.datatables.php');
        self::assertStringContainsString("require_once __DIR__ . '/logs_filter_logic.php';", $dataTable);
        // One normalized payload feeds the read predicates, so no branch builds its own WHERE.
        self::assertStringContainsString('logsNormalizeFilters(', $dataTable);
        self::assertStringContainsString('buildSystemLogFilter(', $dataTable);
        self::assertStringContainsString('buildItemLogFilter(', $dataTable);

        // The purge consumes that same payload: that is what makes it delete what was displayed.
        $purge = file_get_contents(__DIR__ . '/../../app/sources/utilities.queries.php');
        self::assertStringContainsString('logsNormalizeFilters(', $purge);
        self::assertStringContainsString('buildLogsPurgeFilter(', $purge);
        self::assertStringContainsString('$purgeLogin,', $purge);
        // The item purge needs the view's own tables to stay inside the displayed scope.
        foreach (['items', 'users', 'nested_tree'] as $table) {
            self::assertStringContainsString("'{$table}' => prefixTable('{$table}')", $purge);
        }

        // The client must not carry a second copy of the column mapping.
        $javascript = file_get_contents(__DIR__ . '/../../app/pages/utilities.logs.js.php');
        self::assertStringContainsString('logsSystemColumnRule()', $javascript);
    }
}
