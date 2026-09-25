<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * DB-free decisions about the installation state: a missing installation, an
 * unreadable configuration, or a database the installer must not touch.
 *
 * Loaded by the front controller, the installer and the upgrade wizard BEFORE
 * app/config/include.php, so it must not depend on anything: no constant, no
 * autoloader, no language file.
 *
 * @file      config_access_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Tell whether TeamPass is installed, not installed, or installed but unreadable.
 *
 * file_exists() answers false both when settings.php is missing and when the PHP
 * process may not traverse app/config/ — typically after new code was copied over
 * the installation as root, which gives the directory back to root while keeping
 * its 0750 mode (issue #5380). Treating the second case as "not installed" sends
 * the administrator to the installer, whose new encryption key would make every
 * existing secret unreadable. include.php ships with the code, so when PHP cannot
 * read it either, the directory is the problem, not a missing installation.
 *
 * @param string $configDir Absolute path of app/config.
 *
 * @return string 'installed' | 'not_installed' | 'unreadable'
 */
function teampassConfigState(string $configDir): string
{
    $settingsFile = $configDir . '/settings.php';
    if (is_readable($settingsFile) === true) {
        return 'installed';
    }

    if (file_exists($settingsFile) === true || is_readable($configDir . '/include.php') === false) {
        return 'unreadable';
    }

    return 'not_installed';
}

/**
 * Wrap an error message in a self-contained HTML page.
 *
 * No asset is loaded: the page is shown when TeamPass cannot load its own files.
 *
 * @param string $title Page title, plain text.
 * @param string $body  HTML body, already escaped by the caller.
 */
function teampassErrorPageShell(string $title, string $body): string
{
    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $title . '</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f4f6f9;color:#212529;margin:0;padding:16px}
main{max-width:760px;margin:40px auto;background:#fff;border-top:4px solid #dc3545;border-radius:4px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.1)}
h1{font-size:1.4rem;margin-top:0}
pre{background:#f1f3f5;padding:12px;overflow-x:auto;font-size:.85rem}
code{font-size:.9em}
.warn{color:#b02a37;font-weight:600}
</style>
</head>
<body>
<main>
<h1>' . $title . '</h1>
' . $body . '
</main>
</body>
</html>';
}

/**
 * HTML page explaining an unreadable configuration.
 *
 * It is served to unauthenticated visitors, so it names repository-relative paths
 * only; the absolute path and the PHP account go to the server log instead.
 */
function teampassConfigAccessErrorPage(): string
{
    return teampassErrorPageShell(
        'TeamPass cannot read its configuration',
        '<p>The PHP process cannot read <code>app/config/settings.php</code> or <code>app/config/include.php</code>.
When <code>settings.php</code> exists, TeamPass is installed and this is a file permission problem.</p>
<p class="warn">Do not run the installer. It would generate a new encryption key and make every existing secret unreadable.</p>
<p>This usually happens after new code was copied over the installation as root (for instance with <code>rsync -a</code>):
the directories shipped with the code, such as <code>app/config/</code>, <code>storage/</code> and <code>secrets/</code>,
then belong to root instead of the web server user. If <code>app/config/include.php</code> is missing, the code upload
is incomplete: copy the release again.</p>
<p>From the TeamPass directory, give them back to the account PHP runs as (<code>www-data</code> on Debian and Ubuntu;
the server error log names it), then reload this page:</p>
<pre>WEB_USER=www-data
sudo chown ${WEB_USER}:${WEB_USER} app/config app/config/settings.php secrets \\
    app/includes/libraries/csrfp/libs app/includes/libraries/csrfp/log public/assets/avatars
sudo chown -R ${WEB_USER}:${WEB_USER} storage app/websocket/logs</pre>
<p>See the <a href="https://documentation.teampass.net/#/install/file-permissions" target="_blank" rel="noopener">file permissions documentation</a>.</p>'
    );
}

/**
 * HTML page shown by the upgrade wizard when settings.php does not exist.
 *
 * Without it the wizard cannot even load its classes: loadClasses() requires the
 * file and the request died with a bare HTTP 500. In Docker the file lives on the
 * storage/config volume and disappears with the container when that path is not
 * mounted on a named volume, while the database it describes is still there.
 */
function teampassMissingSettingsPage(): string
{
    return teampassErrorPageShell(
        'TeamPass cannot find its configuration',
        '<p>The upgrade wizard needs <code>app/config/settings.php</code>, which holds the database connection of the
installed instance, and this file does not exist.</p>
<p>Upgrading from 3.1.x? Run <code>php migrate_3.2.x.php</code> from the TeamPass directory first: it moves
<code>settings.php</code> into <code>app/config/</code>. On a server where TeamPass was never installed, run the
installer (<code>install/install.php</code>) instead.</p>
<p class="warn">If TeamPass was installed here, do not run the installer. The configuration file was lost, not the data:
a new installation would generate a new encryption key and make every existing secret unreadable.</p>
<p>Restore <code>settings.php</code> from a backup, then reload this page. With Docker, it is kept on the
<code>/var/www/html/storage/config</code> volume and is lost when the container is recreated if that path is not
mounted on a named volume; the previous copy may still sit in an unused Docker volume. Do not run
<code>docker compose down -v</code> or <code>docker volume prune</code> before looking for it.</p>
<p>See <a href="https://documentation.teampass.net/#/install/docker?id=recovering-a-lost-configuration" target="_blank" rel="noopener">Recovering a lost configuration</a>.</p>'
    );
}

/**
 * Send an error page with HTTP 500. The caller must stop right after.
 */
function teampassSendErrorPage(string $html): void
{
    if (headers_sent() === false) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo $html;
}

/**
 * Answer the current request with the unreadable-configuration page.
 *
 * The caller must stop right after: nothing past this point can load.
 *
 * @param string $configDir Absolute path of app/config, written to the server log only.
 */
function teampassSendConfigAccessError(string $configDir): void
{
    $account = 'unknown';
    if (function_exists('posix_geteuid') === true) {
        $uid = posix_geteuid();
        $entry = function_exists('posix_getpwuid') === true ? posix_getpwuid($uid) : false;
        $account = is_array($entry) === true ? $entry['name'] . ' (uid ' . $uid . ')' : 'uid ' . $uid;
    }
    error_log(
        'TeamPass: the PHP process running as ' . $account . ' cannot read ' . $configDir
        . '/settings.php or include.php. Check the owner and mode of this directory; do not run the installer.'
    );

    teampassSendErrorPage(teampassConfigAccessErrorPage());
}

/**
 * Answer the current request with the missing-settings page of the upgrade wizard.
 *
 * The caller must stop right after.
 *
 * @param string $configDir Absolute path of app/config, written to the server log only.
 */
function teampassSendMissingSettingsError(string $configDir): void
{
    error_log(
        'TeamPass: the upgrade wizard cannot run, ' . $configDir . '/settings.php does not exist.'
        . (is_link($configDir . '/settings.php') === true
            ? ' It is a symbolic link to ' . (string) readlink($configDir . '/settings.php') . ', whose target is missing.'
            : '')
        . ' If TeamPass was installed, restore this file; do not run the installer.'
    );

    teampassSendErrorPage(teampassMissingSettingsPage());
}

/**
 * Decide whether the installer must refuse a database.
 *
 * The installer creates its tables with CREATE TABLE IF NOT EXISTS and fills them
 * with INSERT IGNORE, so it runs over an existing instance without complaint and
 * leaves a new encryption key behind: every secret of that instance becomes
 * unreadable. This is the one guard every path to the installer goes through — a
 * lost Docker volume, an unreadable app/config/, a mistyped URL (issue #5380).
 *
 * An interrupted installation must stay retryable, and it cannot be told apart by
 * the schema: its tables and its teampass_version row exist from step 5 on, and the
 * temporary _install table was never dropped by the installers older than 3.2.2.2.
 * What it never has is a sign of use: nobody ever signed in, nothing was stored.
 *
 * @param array{signed_in_users: int, items: int}|null $existing What the database holds
 *        under the chosen prefix, or null when it has no TeamPass schema.
 */
function teampassInstallerMustRefuseDatabase(?array $existing): bool
{
    if ($existing === null) {
        return false;
    }

    return $existing['signed_in_users'] > 0 || $existing['items'] > 0;
}

/**
 * Explanation returned by the installer when it refuses a database.
 *
 * @param string $version Version recorded by the existing instance, '' when unknown.
 * @param string $prefix  Table prefix the administrator entered.
 */
function teampassInstallerRefusalMessage(string $version, string $prefix): string
{
    $instance = $version === ''
        ? 'a TeamPass instance'
        : 'a TeamPass ' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . ' instance';

    return 'This database already holds ' . $instance . ' in use (table prefix <code>'
        . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . '</code>). Installing over it would generate a new '
        . 'encryption key and make its data unreadable, so the installer stops here.<br><br>'
        . 'If <code>app/config/settings.php</code> was lost (with Docker, when <code>storage/config</code> is not '
        . 'on a named volume), restore it and use <code>install/upgrade.php</code> instead: see '
        . '<a href="https://documentation.teampass.net/#/install/docker?id=recovering-a-lost-configuration" '
        . 'target="_blank" rel="noopener">Recovering a lost configuration</a>.<br>'
        . 'To install a new instance, use an empty database or another table prefix.';
}
