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
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

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
    }
}