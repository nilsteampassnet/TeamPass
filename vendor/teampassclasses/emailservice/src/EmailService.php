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
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use voku\helper\AntiXSS;

class EmailSettings
{
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

    // Constructeur pour initialiser les paramètres
    public function __construct(array $SETTINGS)
    {
        $this->smtpServer = $SETTINGS['email_smtp_server'];
        $this->smtpAuth = (int) $SETTINGS['email_smtp_auth'] === 1;
        $this->authUsername = $SETTINGS['email_auth_username'];
        $this->authPassword = $SETTINGS['email_auth_pwd'];
        $this->port = (int) $SETTINGS['email_port'];
        $this->security = $SETTINGS['email_security'];
        $this->from = $SETTINGS['email_from'];
        $this->fromName = $SETTINGS['email_from_name'];
        $this->debugLevel = $SETTINGS['email_debug_level'];
        $this->dir = $SETTINGS['cpassman_dir'];
    }
}

class EmailService
{
    protected $mailer;
    protected $antiXSS;

    public function __construct()
    {
        // Initialise PHPMailer et AntiXSS
        $this->mailer = new PHPMailer(true);
        $this->antiXSS = new AntiXSS();
    }

    // Fonction pour configurer PHPMailer avec les paramètres de l'application
    public function configureMailer(EmailSettings $emailSettings, $silent, $cron)
    {
        $this->mailer->setLanguage('en', $emailSettings->dir . '/vendor/phpmailer/phpmailer/language/');
        $this->mailer->SMTPDebug = ($cron || $silent) ? 0 : $emailSettings->debugLevel;
        $this->mailer->isSMTP();
        $this->mailer->Host = $emailSettings->smtpServer;
        $this->mailer->SMTPAuth = $emailSettings->smtpAuth;
        $this->mailer->Username = $emailSettings->authUsername;
        $this->mailer->Password = $emailSettings->authPassword;
        $this->mailer->Port = $emailSettings->port;
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
            $this->mailer->addAddress($dest);
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
            return ($silent || $emailSettings->debugLevel === 0) ? '' : json_encode([
                'error' => true,
                'errorInfo' => str_replace(["\n", "\t", "\r"], '', $this->mailer->ErrorInfo),
            ]);
        }
    }
}
