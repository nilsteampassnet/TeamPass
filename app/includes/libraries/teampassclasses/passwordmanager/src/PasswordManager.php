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
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

 use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
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

    /**
     * Migrate a legacy bcrypt hash (produced by PasswordLib / PHP's native bcrypt) to
     * the current Symfony PasswordHasher scheme. Safe to call on every login: if the
     * hash is already in the new format the method returns immediately with status=true.
     *
     * @param string $hashedPassword The stored hash to check / migrate.
     * @param string $plainPassword  The plain-text password supplied at login.
     * @param int    $userId         The user's ID (for DB update and task launch).
     * @param bool   $isAdmin        Skip key-regeneration tasks for admin accounts.
     * @return array{status: bool, hashedPassword: string, migratedUser: bool}
     */
    public function migratePassword(string $hashedPassword, string $plainPassword, int $userId, bool $isAdmin = false): array
    {
        $result = [
            'status' => false,
            'hashedPassword' => $hashedPassword,
            'migratedUser' => false,
        ];

        // Legacy hashes were produced by PHP's native bcrypt (cost 10) — verifiable by password_verify().
        if ($this->isLegacyBcryptHash($hashedPassword)) {
            if ($this->verifyPasswordWithbCrypt(html_entity_decode($plainPassword), $hashedPassword)) {
                // Password is valid, hash it with new system
                $newHashedPassword = $this->hashPassword($plainPassword);
                $this->updateInDatabase($newHashedPassword, $userId);

                // Do not launch tasks for admin users
                // as they do not have personal items/fields/files
                if ($isAdmin === false) {
                    $this->launchUserTasks($userId, $plainPassword);
                }

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
        } else {
            // Password is valid, no migration needed
            $result['status'] = true;
        }

        return $result;
    }


    /**
     * Detect a legacy bcrypt hash (cost 10, produced by PHP's native bcrypt or the
     * now-removed PasswordLib library). These hashes must be re-hashed on first login.
     *
     * @param string $hashedPassword The stored hash to inspect.
     * @return bool True when the hash is a legacy bcrypt hash.
     */
    private function isLegacyBcryptHash(string $hashedPassword): bool
    {
        return str_starts_with($hashedPassword, '$2y$10$');
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