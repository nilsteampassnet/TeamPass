# Knowledge base article dates

Articles store nullable Unix timestamps in `kb.created_at` and `kb.updated_at` (with the
installation's configured table prefix). New articles receive a server-side creation
time. An effective change to article fields, linked items or attachments records the
modification time. Views, comments and saving identical values do not advance it.
The recycle bin preserves both dates, including when restoration allocates a new id.

Run the normal TeamPass installer/upgrader when installing this change. Fresh installs
include the columns; the replayable `upgrade_run_3.2.3.php` migration adds them to existing
databases. No setting or background task is required.

The migration recovers dates only from retained `kb_log` events carrying an article id
and a valid timestamp. It recognizes creation and explicit article/attachment change
reasons, excluding comments, views, restores and ambiguous saves without a change reason.
Unknown dates remain `NULL`; a missing creation event is never replaced with an edit or
upgrade time. An absent modification date on a legacy article does not prove that it
was never edited. Replaying the migration does not overwrite dates already recorded,
and purging the audit journal does not remove article dates.

Date labels follow the instance's date format, time format and timezone.

Regression checks:

```text
php _tools/phpunit.phar --filter 'KbArticleDatesTest|KbArticleDatePersistenceTest|KbCategoryIdentityTest'
```

`tests/Integration/kb_dates_database.php` exercises the actual installer schema and
migration against a disposable MariaDB/MySQL database. It requires `TEAMPASS_TEST_DB`
starting with `teampass_test_`, plus the usual `TEAMPASS_TEST_HOST`, `TEAMPASS_TEST_PORT`,
`TEAMPASS_TEST_USER` and `TEAMPASS_TEST_PASSWORD` environment variables. The Quality
workflow runs it with its existing MariaDB service.
