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
 * DB-free check telling a missing installation from an unreadable configuration.
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
 * HTML page explaining an unreadable configuration.
 *
 * It is served to unauthenticated visitors, so it names repository-relative paths
 * only; the absolute path and the PHP account go to the server log instead.
 */
function teampassConfigAccessErrorPage(): string
{
    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TeamPass cannot read its configuration</title>
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
<h1>TeamPass cannot read its configuration</h1>
<p>The PHP process cannot read <code>app/config/settings.php</code> or <code>app/config/include.php</code>.
When <code>settings.php</code> exists, TeamPass is installed and this is a file permission problem.</p>
<p class="warn">Do not run the installer. It would generate a new encryption key and make every existing secret unreadable.</p>
<p>This usually happens after new code was copied over the installation as root (for instance with <code>rsync -a</code>):
the directories shipped with the code, such as <code>app/config/</code>, <code>storage/</code> and <code>secrets/</code>,
then belong to root instead of the web server user. If <code>app/config/include.php</code> is missing, the code upload
is incomplete: copy the release again.</p>
<p>From the TeamPass directory, give them back to the account PHP runs as (<code>www-data</code> on Debian and Ubuntu;
the server error log names it), then reload this page:</p>
<pre>WEB_USER=www-data
sudo chown ${WEB_USER}:${WEB_USER} app/config app/config/settings.php secrets \
    app/includes/libraries/csrfp/libs app/includes/libraries/csrfp/log public/assets/avatars
sudo chown -R ${WEB_USER}:${WEB_USER} storage app/websocket/logs</pre>
<p>See the <a href="https://documentation.teampass.net/#/install/file-permissions" target="_blank" rel="noopener">file permissions documentation</a>.</p>
</main>
</body>
</html>';
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

    if (headers_sent() === false) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo teampassConfigAccessErrorPage();
}
