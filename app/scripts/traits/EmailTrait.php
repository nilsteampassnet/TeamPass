<?php
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
 * @file      EmailTrait.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\EmailService\EmailService;
use TeampassClasses\EmailService\EmailSettings;

require_once __DIR__ . '/../../sources/emails_queue_logic.php';

trait EmailTrait {
    /**
     * Envoie un email
     * @param array $arguments Arguments nécessaires pour l'envoi d'email
     */
    private function sendEmail($arguments) {
        $arguments = emailQueueOpen($arguments);
        // Prepare email properties
        $emailSettings = new EmailSettings($this->settings);
        $emailService = new EmailService();
        
        // if email.encryptedUserPassword is set, decrypt it
        if (empty($arguments['encryptedUserPassword']) === false) {
            $decrypted = cryption($arguments['encryptedUserPassword'], '', 'decrypt', $this->settings);
            if (($decrypted['error'] ?? false) !== false) {
                throw new RuntimeException('Email credential could not be decrypted.');
            }
            $arguments['body'] = str_replace('#password#', $decrypted['string'], $arguments['body']);
        }
        
        // send email
        $result = $emailService->sendMail(
            $arguments['subject'],
            $arguments['body'],
            $arguments['receivers'],
            $emailSettings,
            null,
            true,
            true
        );
        // The worker catches this exception and marks the task failed. Do not include
        // the provider response: it can contain recipient or message data.
        if ($result !== '') {
            throw new RuntimeException('Email delivery failed.');
        }
    }
}
