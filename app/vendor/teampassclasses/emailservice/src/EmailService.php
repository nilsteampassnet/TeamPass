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
use TeampassClasses\Language\Language;
use voku\helper\AntiXSS;

class EmailService
{
    protected $mailer;
    protected $antiXSS;
    private ?EmailTransportInterface $transport;
    private EmailSendResult $lastResult;
    /** @var \Closure(): PHPMailer */
    private \Closure $mailerFactory;

    /**
     * Safe library reason and SMTP codes of the last failed send, '' otherwise.
     *
     * @var string
     */
    protected $lastError = '';

    /**
     * SMTP stages and response codes captured when a debug level is set.
     *
     * @var array<int, string>
     */
    protected $debugOutput = [];

    /** Create the message builder; an injected transport can supply an isolated delivery boundary. */
    public function __construct(?EmailTransportInterface $transport = null, ?callable $mailerFactory = null)
    {
        // Initialise PHPMailer et AntiXSS
        $this->mailerFactory = $mailerFactory === null ? static fn(): PHPMailer => new PHPMailer(true) : \Closure::fromCallable($mailerFactory);
        $this->mailer = ($this->mailerFactory)();
        $this->antiXSS = new AntiXSS();
        $this->transport = $transport;
        $this->lastResult = EmailSendResult::failure('not_attempted');
    }

    /** Return the structured outcome without changing the legacy sendMail() contract. */
    public function getLastResult(): EmailSendResult
    {
        return $this->lastResult;
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
     * Returns SMTP stages and response codes captured during the last send.
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
        // Keep only protocol stages and numeric replies. Raw SMTP debug output can
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
        if ($emailSettings->transport === 'smtp' && $emailSettings->smtpAuthMode === 'microsoft_oauth') {
            if (strcasecmp($emailSettings->from, $emailSettings->microsoftMailbox) !== 0) {
                throw new EmailTransportException(EmailSendResult::failure('configuration'));
            }
            $this->mailer->Host = 'smtp.office365.com';
            $this->mailer->Port = 587;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->SMTPAutoTLS = true;
            $this->mailer->SMTPOptions = self::tlsOptions(true);
            $this->mailer->SMTPAuth = true;
            $this->mailer->AuthType = 'XOAUTH2';
            $this->mailer->Username = $emailSettings->microsoftMailbox;
            $this->mailer->Password = '';
            $this->mailer->setOAuth(new MicrosoftSmtpOAuthProvider($emailSettings->microsoftMailbox, $this->tokenManager($emailSettings, 'microsoft_smtp')));
        }
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
        $this->lastResult = EmailSendResult::failure('not_attempted');
        // A service can send several messages in a backlog or an administrator loop.
        // PHPMailer keeps its recipients after send(), including after a failure.
        try {
            // Never carry OAuth objects, attachments, headers or auth settings between messages.
            $this->mailer = ($this->mailerFactory)();
            $this->mailer->clearAllRecipients();
            $transport = $this->resolveTransport($emailSettings);
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
            $this->mailer->preSend();
            $this->lastResult = $transport->send($this->mailer);
            if ($this->lastResult->accepted === false) {
                $this->lastError = $this->transportErrorMessage($emailSettings);
                return $this->errorResponse();
            }

            return '';

        } catch (EmailTransportException $e) {
            $this->lastResult = $e->result;
            $this->lastError = $this->transportErrorMessage($emailSettings);
            return $this->errorResponse();
        } catch (Exception $e) {
            // The reason is always returned: every caller already decodes it and
            // checks 'error', but it used to be silenced by default, so a refused
            // authentication or an unreachable relay was reported as a success.
            $this->lastError = $this->safeErrorMessage(
                $this->mailer->ErrorInfo !== '' ? $this->mailer->ErrorInfo : $e->getMessage()
            );
            $this->lastResult = $this->smtpFailureResult();
            error_log('Error sending email: ' . $this->lastError);

            return $this->errorResponse();
        } catch (\InvalidArgumentException $e) {
            $this->lastResult = EmailSendResult::failure('configuration');
            $this->lastError = $this->transportErrorMessage($emailSettings);
            return $this->errorResponse();
        } catch (\Throwable $e) {
            // A transport failure may carry credentials in its exception. Never expose it.
            $this->lastResult = EmailSendResult::indeterminate();
            $this->lastError = $this->transportErrorMessage($emailSettings);
            return $this->errorResponse();
        } finally {
            $this->mailer->smtpClose();
        }
    }

    /** Resolve the active transport without falling back from an unsupported selection. */
    private function resolveTransport(EmailSettings $settings): EmailTransportInterface
    {
        if ($this->transport !== null) {
            return $this->transport;
        }
        if ($settings->transport === 'smtp') {
            if (!in_array($settings->smtpAuthMode, ['none', 'password', 'microsoft_oauth'], true)) {
                throw new \InvalidArgumentException('Unsupported SMTP authentication.');
            }
            return new SmtpTransport();
        }
        if (in_array($settings->transport, ['microsoft_graph', 'gmail_api'], true)) {
            return new ApiTransport($settings, $this->tokenManager($settings, $settings->transport), EmailHttp::client());
        }

        throw new \InvalidArgumentException('Unsupported email transport.');
    }

    /** Token audiences are isolated from each other and from TeamPass login OAuth. */
    private function tokenManager(EmailSettings $settings, string $purpose): EmailTokenManager
    {
        return new EmailTokenManager($settings, $purpose, new DatabaseEmailTokenStore($settings->revision), EmailHttp::client());
    }

    /** Translate caller-facing errors independently of any user session. */
    private function transportErrorMessage(EmailSettings $settings): string
    {
        $code = $this->lastResult->indeterminate ? 'indeterminate' : $this->lastResult->errorCode;
        return (string) (new Language($settings->language))->get('email_transport_error_' . $code);
    }

    /** Preserve the historical JSON error fields and append structured metadata. */
    private function errorResponse(): string
    {
        return (string) json_encode(array_merge($this->lastResult->toArray(), [
            'error' => true,
            'errorInfo' => $this->lastError,
            'message' => $this->lastError,
        ]));
    }

    /** Classify SMTP errors conservatively, especially partially accepted recipient lists. */
    private function smtpFailureResult(): EmailSendResult
    {
        $translations = $this->mailer->getTranslations();
        $reason = $this->mailer->ErrorInfo;
        if (str_starts_with($reason, $translations['recipients_failed'])) {
            return EmailSendResult::indeterminate('partial_acceptance');
        }
        if (str_starts_with($reason, $translations['authenticate'])) {
            return EmailSendResult::failure('authentication');
        }
        if (str_starts_with($reason, $translations['connect_host']) || str_starts_with($reason, $translations['smtp_connect_failed'])) {
            return EmailSendResult::failure('network', true);
        }
        $smtpError = $this->mailer->getSMTPInstance()->getError();
        $code = (string) ($smtpError['smtp_code'] ?? '');
        if (preg_match('/\A4[0-9]{2}\z/', $code) === 1) {
            return EmailSendResult::failure('temporary', true);
        }
        if (preg_match('/\A5[0-9]{2}\z/', $code) === 1) {
            return EmailSendResult::failure('rejected');
        }
        if (str_starts_with($reason, $translations['data_not_accepted'])) {
            return EmailSendResult::indeterminate();
        }

        return EmailSendResult::failure('message');
    }

    /** Return only known protocol stages, never command arguments or reply text. */
    private function safeDebugLine(string $line): string
    {
        if (preg_match('/\ACLIENT -> SERVER: (EHLO|HELO|STARTTLS|AUTH|MAIL|RCPT|DATA|RSET|QUIT|NOOP)(?:\s|$)/', $line, $matches) === 1) {
            return 'CLIENT -> SERVER: ' . $matches[1];
        }
        if (preg_match('/\ASERVER -> CLIENT: ([1-5][0-9]{2})(?:[ -]([245]\.[0-9]{1,3}\.[0-9]{1,3})(?=\s|$))?/', $line, $matches) === 1) {
            return 'SERVER -> CLIENT: ' . $matches[1] . (isset($matches[2]) ? ' ' . $matches[2] : '');
        }
        if (preg_match('/\AConnection: (opening|opened|closed|failed)\b/', $line, $matches) === 1) {
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
