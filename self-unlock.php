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
 * @file      2fa.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

// Load functions
require_once __DIR__. '/includes/config/include.php';
require_once __DIR__.'/sources/main.functions.php';

// init
loadClasses();

// Get username and OTP from GET parameters
$request = SymfonyRequest::createFromGlobals();
$username = $request->query->get('login', '');
$otp = $request->query->get('otp', '');

// Redirect user to teampass if username or otp is not provided
if (empty($username) || empty($otp)) {
    header('Location: ./index.php');
    exit;
}

// Check for existing lock
$result = DB::queryFirstField(
    'SELECT 1
     FROM ' . prefixTable('auth_failures') . '
     WHERE unlock_at = (
        SELECT MAX(unlock_at)
        FROM ' . prefixTable('auth_failures') . '
        WHERE unlock_at > %s
        AND source = %s AND value = %s)
     AND unlock_code = %s',
    date('Y-m-d H:i:s', time()),
    'login',
    $username,
    $otp
);

// Delete all logs for this user if provided OTP is correct
if ($result) {
    DB::delete(
        prefixTable('auth_failures'),
        'source = %s AND value = %s',
        'login',
        $username
    );
}

// Redirect user to teampass
header('Location: ./index.php');
exit;
