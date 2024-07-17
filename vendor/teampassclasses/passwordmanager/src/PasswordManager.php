<?php
namespace TeampassClasses\PasswordManager;

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
 * @file      PasswordManager.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

 use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
 use PasswordLib\PasswordLib;
 Use DB;

class PasswordManager
{
    private $hasherFactory;

    public function __construct()
    {
        // Hasher configuration
        $this->hasherFactory = new PasswordHasherFactory([
            'common' => ['algorithm' => 'auto'],
        ]);
    }

    public function hashPassword(string $password): string
    {
        $hasher = $this->hasherFactory->getPasswordHasher('common');
        return $hasher->hash($password);
    }

    public function verifyPassword(string $hashedPassword, string $plainPassword): bool
    {
        $hasher = $this->hasherFactory->getPasswordHasher('common');
        return $hasher->verify($hashedPassword, $plainPassword);
    }

    // --- Handle migration from PasswordLib to Symfony PasswordHasher
    public function migratePassword(string $hashedPassword, string $plainPassword, int $userId): string
    {
        // Vérifiez si le mot de passe a été haché avec passwordlib
        if ($this->isPasswordLibHash($hashedPassword)) {
            // Utilisez la vérification de passwordlib ici
            if ($this->passwordLibVerify($hashedPassword, html_entity_decode($plainPassword))) {
                // Password is valid, hash it with new system
                $newHashedPassword = $this->hashPassword($plainPassword);
                $userInfo['pw'] = $newHashedPassword;

                // Update user password in DB
                $this->updateInDatabase($newHashedPassword, $userId);

                if (WIP === true) error_log("migratePassword performed for user ".$userId." | Old hash: ".$hashedPassword." | New hash: ".$newHashedPassword);
                // Return new hashed password
                return $newHashedPassword;
            } else {
                //throw new \Exception("Password is not correct");
                return false;
            }
        }

        // Le mot de passe a déjà été haché avec le nouveau système
        return $hashedPassword;
    }

    private function isPasswordLibHash(string $hashedPassword): bool
    {
        // Check if the password has been hashed with passwordlib
        return strpos($hashedPassword, '$2y$10$') === 0;
    }

    // Vous devrez implémenter cette fonction pour utiliser la vérification de passwordlib
    private function passwordLibVerify(string $hashedPassword, string $plainPassword): bool
    {
        // Vérification avec passwordlib
        $pwdlib = new PasswordLib();
        return $pwdlib->verifyPasswordHash($plainPassword, $hashedPassword);
    }

    private function updateInDatabase(string $hashedPassword, int $userId): void
    {
        // Update user password in DB
        DB::update(
            prefixTable('users'),
            [
                'pw' => $hashedPassword,
            ],
            'id = %i',
            $userId
        );
    }
}