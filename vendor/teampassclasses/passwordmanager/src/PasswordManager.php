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
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

 use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
 use PasswordLib\PasswordLib;
 Use DB;

class PasswordManager
{
    private $hasherFactory;

    /**
     * PasswordManager constructor.
     * Initializes the password hasher factory with the default configuration.
     */
    public function __construct()
    {
        // Hasher configuration
        $this->hasherFactory = new PasswordHasherFactory([
            'common' => ['algorithm' => 'auto'],
        ]);
    }

    /**
     * Hash a password using the configured password hasher.
     *
     * @param string $password The plain text password to hash.
     * @return string The hashed password.
     */
    public function hashPassword(string $password): string
    {
        $hasher = $this->hasherFactory->getPasswordHasher('common');
        return $hasher->hash($password);
    }

    /**
     * Verify a password against a hashed password.
     *
     * @param string $hashedPassword The hashed password to verify against.
     * @param string $plainPassword The plain text password to verify.
     * @return bool True if the password matches, false otherwise.
     */
    public function verifyPassword(string $hashedPassword, string $plainPassword): bool
    {
        $hasher = $this->hasherFactory->getPasswordHasher('common');
        return $hasher->verify($hashedPassword, $plainPassword);
    }

    // --- Handle migration from PasswordLib to Symfony PasswordHasher
    public function migratePassword(string $hashedPassword, string $plainPassword, int $userId): array
    {
        $result = [
            'status' => false,
            'hashedPassword' => $hashedPassword,
            'migratedUser' => false,
        ];

        // If the password has been hashed with PasswordLib
        if ($this->isPasswordLibHash($hashedPassword)) {
            // Utilisez la vérification de passwordlib ici
            if (
                $this->passwordLibVerify($hashedPassword, html_entity_decode($plainPassword))
                || $this->verifyPasswordWithbCrypt(html_entity_decode($plainPassword), $hashedPassword)
            ) {
                // Password is valid, hash it with new system
                $newHashedPassword = $this->hashPassword($plainPassword);
                $this->updateInDatabase($newHashedPassword, $userId);
                $this->launchUserTasks($userId, $plainPassword);

                if (WIP === true) {
                    error_log("migratePassword performed for user " . $userId . " | Old hash: " . $hashedPassword . " | New hash: " . $newHashedPassword);
                }

                $result = [
                    'status' => true,
                    'hashedPassword' => $newHashedPassword,
                    'migratedUser' => true,
                ];
            } else {
                $result['status'] = false;
            }
        }

        return $result;
    }


    /**
     * Check if the password has been hashed with PasswordLib.
     *
     * @param string $hashedPassword The hashed password to check.
     * @return bool True if the password is a PasswordLib hash, false otherwise.
     */
    private function isPasswordLibHash(string $hashedPassword): bool
    {
        // Check if the password has been hashed with passwordlib
        return str_starts_with($hashedPassword, '$2y$10$');
    }
    
    /**
     * Verify a password using PasswordLib.
     *
     * @param string $hashedPassword The hashed password to verify against.
     * @param string $plainPassword The plain text password to verify.
     * @return bool True if the password matches, false otherwise.
     */
    private function passwordLibVerify(string $hashedPassword, string $plainPassword): bool
    {
        $pwdlib = new PasswordLib();
        try {
            return $pwdlib->verifyPasswordHash($plainPassword, $hashedPassword);
        } catch (\Exception $e) {
            if (WIP === true) error_log("PasswordLib setCost exception: ".$e->getMessage());
        }
        return false;
    }

    /**
     * Verify a password using PHP's built-in password_verify function for bcrypt hashes.
     *
     * @param string $plainPassword The plain text password to verify.
     * @param string $hash The hashed password to verify against.
     * @return bool True if the password matches, false otherwise.
     */
    function verifyPasswordWithbCrypt(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }


    /**
     * Updates the user password in the database.
     *
     * @param string $hashedPassword The hashed password to store.
     * @param int $userId The ID of the user whose password is being updated.
     */
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

    /**
     * Launches tasks related to user keys and configurations.
     *
     * @param int $userId The ID of the user.
     * @param string $plainPassword The plain text password of the user.
     */
    private function launchUserTasks($userId, $plainPassword): void
    {
        handleUserKeys(
            (int) $userId,
            (string) $plainPassword,
            (int) NUMBER_ITEMS_IN_BATCH,
            '',
            true,
            true,
            true,
            false,
            'email_body_user_config_4',
            true,
            '',
            '',
        );
    }
}