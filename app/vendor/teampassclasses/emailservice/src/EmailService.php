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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use voku\helper\AntiXSS;

class EmailService
{
    protected $mailer;
    protected $antiXSS;

    /**
     * Message returned by the mail server on the last failed send, '' otherwise.
     *
     * @var string
     */
    protected $lastError = '';

    /**
     * SMTP conversation captured on the last send when a debug level is set.
     *
     * @var array<int, string>
     */
    protected $debugOutput = [];

    public function __construct()
    {
        // Initialise PHPMailer et AntiXSS
        $this->mailer = new PHPMailer(true);
        $this->antiXSS = new AntiXSS();
    }

    /**
     * Returns the mail server message of the last failed send.
     *
     * Callers that only need to know whether the send worked can rely on the
     * return value of sendMail(); this accessor gives the raw reason without
     * having to decode it.
     *
     * @return string Empty string when the last send succeeded.
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Returns the SMTP conversation captured during the last send.
     *
     * Only filled when the administrator set a debug level other than "None";
     * empty otherwise. PHPMailer would print that trace straight to the output
     * buffer, which corrupts an AJAX answer, so it is captured instead.
     *
     * @return string Empty string when no debug level is active.
     */
    public function getDebugOutput(): string
    {
        return implode("\n", $this->debugOutput);
    }

    // Fonction pour configurer PHPMailer avec les paramètres de l'application
    public function configureMailer(EmailSettings $emailSettings, $silent, $cron)
    {
        $this->mailer->setLanguage('en', $emailSettings->dir . '/vendor/phpmailer/phpmailer/language/');
        $this->mailer->SMTPDebug = ($cron || $silent) ? 0 : (int) $emailSettings->debugLevel;
        // Capture the SMTP conversation rather than letting PHPMailer echo it:
        // it would land in the middle of the JSON answer and make it unparsable.
        $this->mailer->Debugoutput = function ($str) {
            $line = trim((string) $str);
            if ($line !== '') {
                $this->debugOutput[] = $line;
            }
        };
        $this->mailer->isSMTP();
        $this->mailer->Host = $emailSettings->smtpServer;
        $this->mailer->SMTPAuth = $emailSettings->smtpAuth;
        $this->mailer->Username = $emailSettings->authUsername;
        $this->mailer->Password = $emailSettings->authPassword;
        $this->mailer->Port = $emailSettings->port;
        // Bound the connection and every read, otherwise an unreachable relay
        // keeps the worker blocked for PHPMailer's 300s default and the browser
        // only ever sees the reverse proxy's gateway timeout.
        $this->mailer->Timeout = (int) ($emailSettings->timeout ?? EmailSettings::DEFAULT_TIMEOUT);
        $this->mailer->SMTPSecure = $emailSettings->security !== 'none' ? $emailSettings->security : '';
        $this->mailer->SMTPAutoTLS = $emailSettings->security !== 'none';
        $this->mailer->CharSet = 'utf-8';
        $this->mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $this->mailer->From = $emailSettings->from;
        $this->mailer->FromName = $emailSettings->fromName;
    }

    // Fonction pour ajouter des destinataires
    public function addRecipients($email)
    {
        foreach (array_filter(explode(',', $email)) as $dest) {
            $dest = trim($dest);
            if (filter_var($dest, FILTER_VALIDATE_EMAIL)) {
                $this->mailer->addAddress($dest);
            } else {
                error_log("Teampass - Error - Invalid email ignored : $dest");
            }
        }
    }

    // Fonction pour nettoyer le contenu de l'email et prévenir les attaques XSS
    public function sanitizeEmailBody($textMail)
    {
        $textMailClean = $this->antiXSS->xss_clean($textMail);
        if ($this->antiXSS->isXssFound()) {
            return htmlspecialchars($textMailClean, ENT_QUOTES, 'UTF-8');
        }

        return $textMail;
    }

    // Fonction pour envoyer l'email
    public function sendMail(
        $subject,
        $textMail,
        $email,
        EmailSettings $emailSettings,
        $textMailAlt = null,
        $silent = true,
        $cron = false
    ) {
        $this->lastError = '';
        $this->debugOutput = [];

        try {
            // Configurer le mailer
            $this->configureMailer($emailSettings, $silent, $cron);

            // Ajouter les destinataires
            $this->addRecipients($email);

            // Nettoyer le contenu de l'email
            $textMail = $this->sanitizeEmailBody($textMail);

            // Préparer l'email
            $this->mailer->isHtml(true);
            $this->mailer->WordWrap = 80;
            $this->mailer->Subject = $subject;
            $this->mailer->Body = emailBody($textMail);  // Assurez-vous que cette fonction existe
            $this->mailer->AltBody = $textMailAlt ?? '';

            // Envoyer l'email
            $this->mailer->send();
            $this->mailer->smtpClose();

            return '';

        } catch (Exception $e) {
            error_log('Error sending email: ' . $e->getMessage());

            // The reason is always returned: every caller already decodes it and
            // checks 'error', but it used to be silenced by default, so a refused
            // authentication or an unreachable relay was reported as a success.
            $this->lastError = str_replace(
                ["\n", "\t", "\r"],
                '',
                $this->mailer->ErrorInfo !== '' ? $this->mailer->ErrorInfo : $e->getMessage()
            );

            return (string) json_encode([
                'error' => true,
                'errorInfo' => $this->lastError,
                // Kept for the callers that read 'message'.
                'message' => $this->lastError,
            ]);
        }
    }
}
