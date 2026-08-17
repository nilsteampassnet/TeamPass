# PHP-FPM Optimization

TeamPass runs under any PHP SAPI (Apache `mod_php`, `php-fpm`/`fpm-fcgi`, CLI). PHP-FPM is the
recommended production setup. The code does **not** require mod_php — `apache_request_headers()`
is only a guarded fallback in `app/api/inc/jwt_utils.php`. These notes cover the FPM-specific code paths.

## CLI binary resolution — `getPHPBinary()`

`app/sources/main.functions.php` → `getPHPBinary(): string`.

Background tasks are spawned as separate **CLI** processes. Under `fpm-fcgi`, naive detection
(`PhpExecutableFinder::find()`) may return the `php-fpm` binary (non-interactive) or fail.
Resolution order:

1. Admin override `cli_php_binary_path` (if set and `is_executable`).
2. `PhpExecutableFinder::find(false)`.
3. If running under FPM / nothing found / a `*fpm*` path was found: derive and probe CLI
   candidates (`preg_replace('/php-?fpm/i','php', …)`, `PHP_BINDIR/php{,maj.min}`,
   `/usr/bin/php{,maj.min}`, `/usr/local/bin/php{,maj.min}`), excluding any `*fpm*` path.
4. Fallback `'php'` (PATH). **Never** returns the literal string `'false'` (former bug).

Consumers: `triggerBackgroundHandler()`, `tasks.queries.php` (`performTask`),
`utilities.queries.php` (cron setup), `background_tasks___handler.php:launchTask()`
(prefers `getPHPBinary()`, falls back to `PHP_BINARY` — fine there since the handler is
already a CLI process).

## Early response flush — `tpFinishRequestEarly()`

`app/sources/main.functions.php`, next to `triggerBackgroundHandler()`.

Wraps `fastcgi_finish_request()`: sends the response and frees the FPM worker before PHP
shutdown work. **No-op** under mod_php (function absent) and when
`enable_fastcgi_finish_request` is `0`.

**Rule: call it only AFTER the full response is echoed** — output produced after the call is
not delivered to the client. Currently called at the end of the `create_item` success path in
`items.queries.php`. Background task triggering (`exec(... &)`) is already detached, so the
gain is shutdown/session-write latency, not task offload.

## Admin settings (`teampass_misc`, type `admin`)

| Key | Default | Purpose |
|---|---|---|
| `cli_php_binary_path` | `''` | Force the PHP CLI binary for background tasks; empty = auto-detect. |
| `enable_fastcgi_finish_request` | `1` | Toggle the early flush; no-op under mod_php. |

UI: **Tasks** page, "Performance / PHP-FPM" block (`app/pages/tasks.php`). Saved by the generic
`save_option_change` handler via `admin.js.php` (loaded on all admin pages). Seeded in
`public/install/install-steps/run.step5.php` and `public/install/upgrade_run_3.2.0.php`.

## Upload limits

The web server caps body size before PHP. `docker/nginx/teampass.conf` sets
`client_max_body_size 100M`. For standalone installs the admin must align nginx
`client_max_body_size` / Apache `LimitRequestBody` with PHP `post_max_size`/`upload_max_filesize`.
The health check (`utilities.queries.php` → `tpGetSystemChecks`) surfaces the PHP limits as an
informational reminder (`health_check_upload_limits_websrv`).

## Health check / tuning

`PHP_SAPI === 'fpm-fcgi'` reported in `tpGetSystemChecks` (success vs info). The installer FPM
check stays `optional: true` (non-blocking). Tuning guidance (`pm.max_children`,
`request_terminate_timeout` vs long tasks) lives in `docs/install/performance.md`.

> `set_time_limit(0)` does **not** override FPM `request_terminate_timeout` — long imports/exports
> and item moves are killed if the pool timeout is too low.
