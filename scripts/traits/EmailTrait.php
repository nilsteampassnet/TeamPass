<?php

use TeampassClasses\EmailService\EmailService;
use TeampassClasses\EmailService\EmailSettings;

trait EmailTrait {
    /**
     * Envoie un email
     * @param array $arguments Arguments nécessaires pour l'envoi d'email
     */
    private function sendEmail($arguments) {
        // Prepare email properties
        $emailSettings = new EmailSettings($this->settings);
        $emailService = new EmailService();
        
        // if email.encryptedUserPassword is set, decrypt it
        if (isset($arguments['encryptedUserPassword']) === true) {
            $userPassword = cryption($arguments['encryptedUserPassword'], '', 'decrypt', $this->settings)['string'];
            $arguments['body'] = str_replace('#password#', $userPassword, $arguments['body']);
        }
        
        // send email
        $emailService->sendMail(
            $arguments['subject'],
            $arguments['body'],
            $arguments['receivers'],
            $emailSettings,
            null,
            true,
            true
        );

        // Clear body content and encryptedUserPassword
        $email['body'] = '<cleared>';
        $email['encryptedUserPassword'] = '<cleared>';
    }
}