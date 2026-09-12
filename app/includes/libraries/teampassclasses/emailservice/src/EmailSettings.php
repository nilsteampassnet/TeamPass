<?php
namespace TeampassClasses\EmailService;

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
 * @file      EmailService.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

class EmailSettings
{
    /**
     * Default SMTP timeout in seconds.
     *
     * PHPMailer defaults to 300s, which is far too long: an unreachable relay
     * (dropped packets, wrong port/security combination) keeps the PHP worker
     * blocked in the socket until a reverse proxy in front of TeamPass gives up
     * and answers a 504 to the browser. Socket wait time is not counted by
     * max_execution_time, so nothing else bounds it.
     */
    public const DEFAULT_TIMEOUT = 30;

    /**
     * Shorter timeout for the interactive "test email configuration" button,
     * which must report a failure instead of hanging the request.
     */
    public const TEST_TIMEOUT = 10;

    public $smtpServer;
    public $smtpAuth;
    public $authUsername;
    public $authPassword;
    public $port;
    public $security;
    public $from;
    public $fromName;
    public $debugLevel;
    public $dir;
    public $timeout;

    // Constructeur pour initialiser les paramètres
    public function __construct(array $SETTINGS)
    {
        $this->smtpServer = $SETTINGS['email_smtp_server'] ?? '';
        $this->smtpAuth = isset($SETTINGS['email_smtp_auth']) ? ((int) $SETTINGS['email_smtp_auth']) === 1 : false;
        $this->authUsername = $SETTINGS['email_auth_username'] ?? '';
        $this->authPassword = $SETTINGS['email_auth_pwd'] ?? '';
        $this->port = isset($SETTINGS['email_port']) ? (int) $SETTINGS['email_port'] : 25;
        $this->security = $SETTINGS['email_security'] ?? 'none';
        $this->from = $SETTINGS['email_from'] ?? 'no-reply@example.com';
        $this->fromName = $SETTINGS['email_from_name'] ?? 'No Reply';
        $this->debugLevel = $SETTINGS['email_debug_level'] ?? 0;
        $this->dir = defined('TEAMPASS_APP') ? TEAMPASS_APP : __DIR__;
        $this->timeout = self::DEFAULT_TIMEOUT;
    }
}