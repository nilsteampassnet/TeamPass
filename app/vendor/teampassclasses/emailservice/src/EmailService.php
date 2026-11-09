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
     * Safe library reason and SMTP codes of the last failed send, '' otherwise.
     *
     * @var string
     */
    protected $lastError = '';

    /**
     * SMTP stages, response codes and local connection errors captured in debug mode.
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
     * Returns the safe reason of the last failed send, without provider details.
     *
     * Callers that only need to know whether the send worked can rely on the
     * return value of sendMail(); this accessor gives the safe reason without
     * having to decode it.
     *
     * @return string Empty string when the last send succeeded.
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Returns SMTP stages, response codes and local connection errors from the last send.
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

    /**
     * Returns the stream context options of the TLS session opened with the SMTP server.
     *
     * Without verification the connection is still encrypted but no longer authenticated:
     * anyone on the network path can present their own certificate and read the SMTP
     * credentials and every email. Only an administrator whose relay presents a self-signed
     * or internal certificate should turn it off.
     *
     * @param bool $verifyCertificate True to check the certificate chain against the system
     *                                CA store and the certificate name against the SMTP host.
     *
     * @return array<string, array<string, bool>> Value for PHPMailer::$SMTPOptions.
     */
    public static function tlsOptions(bool $verifyCertificate): array
    {
        return [
            'ssl' => [
                'verify_peer' => $verifyCertificate,
                'verify_peer_name' => $verifyCertificate,
                'allow_self_signed' => $verifyCertificate === false,
            ],
        ];
    }

    // Fonction pour configurer PHPMailer avec les paramètres de l'application
    public function configureMailer(EmailSettings $emailSettings, $silent, $cron)
    {
        $this->mailer->setLanguage('en', $emailSettings->dir . '/vendor/phpmailer/phpmailer/language/');
        $this->mailer->SMTPDebug = ($cron || $silent) ? 0 : (int) $emailSettings->debugLevel;
        // Keep protocol stages, numeric replies and local connection errors. Raw debug output can
        // include AUTH credentials, message bodies and provider-echoed secrets.
        $this->mailer->Debugoutput = function ($str) {
            $line = $this->safeDebugLine(trim((string) $str));
            if ($line !== '' && count($this->debugOutput) < 200) {
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
        $this->mailer->SMTPOptions = self::tlsOptions($emailSettings->verifyCertificate === true);
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
                error_log('Teampass - Error - Invalid email address ignored');
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
        // A service can send several messages in a backlog or an administrator loop.
        // PHPMailer keeps its recipients after send(), including after a failure.
        $this->mailer->clearAllRecipients();

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

            return '';

        } catch (Exception $e) {
            // The reason is always returned: every caller already decodes it and
            // checks 'error', but it used to be silenced by default, so a refused
            // authentication or an unreachable relay was reported as a success.
            $this->lastError = $this->safeErrorMessage(
                $this->mailer->ErrorInfo !== '' ? $this->mailer->ErrorInfo : $e->getMessage()
            );
            error_log('Error sending email: ' . $this->lastError);

            return (string) json_encode([
                'error' => true,
                'errorInfo' => $this->lastError,
                // Kept for the callers that read 'message'.
                'message' => $this->lastError,
            ]);
        } finally {
            $this->mailer->smtpClose();
        }
    }

    /** Keep protocol stages and local connection errors, never command arguments or reply text. */
    private function safeDebugLine(string $line): string
    {
        if (preg_match('/\ACLIENT -> SERVER: (EHLO|HELO|STARTTLS|AUTH|MAIL|RCPT|DATA|RSET|QUIT|NOOP)(?:\s|$)/', $line, $matches) === 1) {
            return 'CLIENT -> SERVER: ' . $matches[1];
        }
        if (preg_match('/\ASERVER -> CLIENT: ([1-5][0-9]{2})(?:[ -]([245]\.[0-9]{1,3}\.[0-9]{1,3})(?=\s|$))?/', $line, $matches) === 1) {
            return 'SERVER -> CLIENT: ' . $matches[1] . (isset($matches[2]) ? ' ' . $matches[2] : '');
        }
        if (preg_match('/\A(Connection failed\. Error #[0-9]+: .*?)(?: \[[^\r\n]* line [0-9]+\])?\z/s', $line, $matches) === 1) {
            return $matches[1];
        }
        if (preg_match('/\ASMTP ERROR: Failed to connect to server: .* \([0-9]+\)\z/s', $line) === 1) {
            return $line;
        }
        if (preg_match('/\ASMTP ERROR: (EHLO|HELO|STARTTLS|AUTH(?: CRAM-MD5)?|Username|Password|User & Password|MAIL FROM|RCPT TO|DATA(?: END)?|RSET|QUIT|NOOP|XCLIENT|SAML|VRFY) command failed(?::|\z)/', $line, $matches) === 1) {
            return 'SMTP ERROR: ' . $matches[1] . ' command failed';
        }
        if ($line === 'Connection: closing due to error') {
            return $line;
        }
        if (preg_match('/\AConnection: (opening|opened|closed)\b/', $line, $matches) === 1) {
            return 'Connection: ' . $matches[1];
        }

        return '';
    }

    /** Resolve a shipped PHPMailer reason and validated codes without echoing server data. */
    private function safeErrorMessage(string $error): string
    {
        $translations = $this->mailer->getTranslations();
        $reason = rtrim($translations['smtp_error'], ": \t\r\n");
        foreach ([
            'authenticate', 'smtp_connect_failed', 'connect_host', 'data_not_accepted',
            'recipients_failed', 'from_failed', 'provide_address', 'invalid_address',
            'invalid_header', 'invalid_hostentry', 'invalid_host', 'empty_message',
            'extension_missing', 'no_smtputf8',
        ] as $key) {
            if (isset($translations[$key]) && strpos($error, $translations[$key]) !== false) {
                $reason = rtrim($translations[$key], ": \t\r\n");
                break;
            }
        }

        $smtpError = $this->mailer->getSMTPInstance()->getError();
        // PHPMailer puts a local socket errno in smtp_code when connecting fails.
        // It is not an SMTP reply; the local reason is available in the debug trace.
        if (in_array($smtpError['error'] ?? '', ['Failed to connect to server', 'Connection failed.'], true)) {
            return $reason;
        }
        $codes = [];
        if (preg_match('/\A[1-5][0-9]{2}\z/', (string) ($smtpError['smtp_code'] ?? '')) === 1) {
            $codes[] = (string) $smtpError['smtp_code'];
        }
        if (preg_match('/\A[245]\.[0-9]{1,3}\.[0-9]{1,3}\z/', (string) ($smtpError['smtp_code_ex'] ?? '')) === 1) {
            $codes[] = (string) $smtpError['smtp_code_ex'];
        }

        return $reason . ($codes === [] ? '' : ' [' . implode(' ', $codes) . ']');
    }
}
