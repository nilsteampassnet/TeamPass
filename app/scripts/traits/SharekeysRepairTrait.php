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
 * @file      SharekeysRepairTrait.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

trait SharekeysRepairTrait {

    /**
     * Restore missing or broken sharekeys for all eligible users, using TP_USER
     * as the reference key holder. Personal items are excluded. Missing, empty
     * and legacy v1 sharekeys are (re)built from the TP_USER object key; valid
     * v3 keys are never overwritten, so the operation stays idempotent and safe
     * to relaunch. Rebuilding leftover v1 keys also clears the "inconsistent
     * user" state that leaves custom fields unreadable after an upgrade (#5252).
     *
     * Launched from the Tools page ('restore_missing_sharekeys' background task).
     *
     * @param array $arguments Task arguments ('author' = admin user id)
     * @return void
     * @throws Exception When the TP_USER private key cannot be decrypted
     */
    private function handleRestoreMissingSharekeys(array $arguments): void {
        $authorId = (int) ($arguments['author'] ?? 0);

        // Get TP_USER keys (decryptable server-side, no human password needed)
        $userTpInfo = DB::queryFirstRow(
            'SELECT u.pw, u.public_key, pk.private_key
            FROM ' . prefixTable('users') . ' AS u
            LEFT JOIN ' . prefixTable('user_private_keys') . ' AS pk ON (u.id = pk.user_id AND pk.is_current = 1)
            WHERE u.id = %i',
            TP_USER_ID
        );
        $decryptedData = cryption((string) ($userTpInfo['pw'] ?? ''), '', 'decrypt', $this->settings);
        $tpPrivateKey = decryptPrivateKey($decryptedData['string'] ?? '', (string) ($userTpInfo['private_key'] ?? ''));
        if (empty($tpPrivateKey)) {
            throw new Exception('restore_missing_sharekeys: cannot decrypt TP_USER private key');
        }

        // Eligible users - same rule as storeUsersShareKey()
        $users = DB::query(
            'SELECT id, public_key
            FROM ' . prefixTable('users') . '
            WHERE id NOT IN %li AND public_key != ""',
            [OTV_USER_ID, SSH_USER_ID, API_USER_ID]
        );
        if (count($users) === 0) {
            return;
        }

        // One pass per object type sharing the same sharekeys schema. The scopes - including the
        // exclusion of personal objects - come from restoreSharekeysScopeDefs() so that what the
        // Tools page analyses and what this task repairs can never describe different object sets.
        $summary = [];
        foreach (restoreSharekeysScopeDefs() as $scopeName => $def) {
            $result = $this->restoreScopeMissingSharekeys(
                'SELECT o.id AS id FROM ' . $def['from'] . '
                WHERE ' . $def['where'] . ' AND o.id > %i
                ORDER BY o.id ASC LIMIT %i',
                $def['table'],
                $tpPrivateKey,
                (string) $userTpInfo['public_key'],
                $users
            );
            $summary[] = $scopeName . ': ' . $result['created'] . ' key(s) created/rebuilt, '
                . $result['unrecoverable'] . ' object(s) without reference key';
        }

        // Personal objects: one key, for the owner alone. Never the fan-out above - that is the
        // SEC-8 leak. The owner is resolved from the personal tree, cross-checked against the
        // item creator, and anything unresolved is reported rather than guessed.
        $folderOwners = personalFolderOwnersMap();
        foreach (restoreSharekeysScopeDefs(true) as $scopeName => $def) {
            $result = $this->restorePersonalScopeSharekeys(
                $def,
                $tpPrivateKey,
                (string) $userTpInfo['public_key'],
                $folderOwners
            );
            $summary[] = 'personal ' . $scopeName . ': ' . $result['owner_keys'] . ' owner key(s) restored, '
                . $result['foreign_removed'] . ' foreign key(s) removed, '
                . $result['no_reference'] . ' object(s) without reference key, '
                . $result['owner_unresolved'] . ' object(s) with no resolvable owner';
        }

        $summaryText = implode(' | ', $summary);

        $this->logger->log('restore_missing_sharekeys: ' . $summaryText, 'INFO');
        logEvents($this->settings, 'admin_action', 'restore_missing_sharekeys: ' . $summaryText, (string) $authorId);

        if ($authorId > 0) {
            emitTaskProgress($authorId, (string) $this->taskId, 'restore_missing_sharekeys', 1, 1, 'completed', $summaryText);
        }
    }

    /**
     * Restore the owner sharekey of one personal object type (items, fields or files).
     *
     * A personal object must carry sharekeys for its owner and the system accounts only - invariant
     * I1, the SEC-8 fix. So this pass never distributes: it decrypts the object key with the TP_USER
     * reference sharekey and writes back exactly one row, for the owner, when the owner's own key is
     * missing or still in legacy v1 encryption. Foreign sharekeys left by a pre-SEC-8 install are
     * removed at the same time, which is what EnsurePersonalItemHasOnlyKeysForOwner() does lazily
     * and remediate_personal_sharekeys.php does in bulk.
     *
     * Two deliberate refusals, both conservative:
     *  - an owner that cannot be resolved, or that disagrees with the item creator, is reported and
     *    the object is left untouched - the same rule as the remediation script;
     *  - an object with no usable TP_USER reference key keeps its foreign sharekeys. They are the
     *    only remaining way to recover it: a holder can still open the item and save it again, which
     *    is precisely what the Tools page tells the administrator to arrange. Deleting them here
     *    would turn a repairable object into a lost one.
     *
     * @param array{table: string, from: string, where: string, itemAlias: string} $def Scope definition
     * @param string             $tpPrivateKey TP_USER decrypted private key
     * @param string             $tpPublicKey  TP_USER public key (for v1->v3 sharekey migration)
     * @param array<int, int>    $folderOwners Folder id => owner user id
     *
     * @return array{owner_keys: int, foreign_removed: int, no_reference: int, owner_unresolved: int}
     */
    private function restorePersonalScopeSharekeys(
        array $def,
        string $tpPrivateKey,
        string $tpPublicKey,
        array $folderOwners
    ): array {
        $sharekeysTable = $def['table'];
        $itemAlias = $def['itemAlias'];
        $systemUserIds = [(int) TP_USER_ID, (int) API_USER_ID, (int) OTV_USER_ID, (int) SSH_USER_ID];

        $stats = ['owner_keys' => 0, 'foreign_removed' => 0, 'no_reference' => 0, 'owner_unresolved' => 0];
        $ownerPublicKeys = [];
        $lastId = 0;
        $batchSize = 100;

        while (true) {
            $objects = DB::query(
                'SELECT o.id AS id, ' . $itemAlias . '.id AS item_id, ' . $itemAlias . '.id_tree AS folder_id
                FROM ' . $def['from'] . '
                WHERE ' . $def['where'] . ' AND o.id > %i
                ORDER BY o.id ASC LIMIT %i',
                $lastId,
                $batchSize
            );
            if (count($objects) === 0) {
                break;
            }

            $objectIds = [];
            $itemIds = [];
            foreach ($objects as $object) {
                $objectIds[] = (int) $object['id'];
                $itemIds[] = (int) $object['item_id'];
            }
            $lastId = max($objectIds);
            $itemIds = array_values(array_unique($itemIds));

            // Creators of the batch's items, to cross-check the owner resolved from the folder.
            $creators = [];
            $logs = DB::query(
                'SELECT id_item, id_user FROM ' . prefixTable('log_items') . '
                WHERE id_item IN %li AND action = %s',
                $itemIds,
                'at_creation'
            );
            foreach ($logs as $log) {
                $creators[(int) $log['id_item']] = $log['id_user'];
            }

            // Every sharekey of the batch, read once: current holders, valid v3 keys, TP_USER refs.
            $holders = [];
            $validV3 = [];
            $tpRefs = [];
            $pairs = DB::query(
                'SELECT object_id, user_id, share_key, increment_id, encryption_version
                FROM ' . prefixTable($sharekeysTable) . '
                WHERE object_id IN %li AND share_key != ""',
                $objectIds
            );
            foreach ($pairs as $pair) {
                $objectId = (int) $pair['object_id'];
                $userId = (int) $pair['user_id'];
                $holders[$objectId][] = $userId;
                if ((int) $pair['encryption_version'] === 3) {
                    $validV3[$objectId][$userId] = true;
                }
                if ($userId === (int) TP_USER_ID) {
                    $tpRefs[$objectId] = $pair;
                }
            }

            foreach ($objects as $object) {
                $objectId = (int) $object['id'];
                $ownerId = $folderOwners[(int) $object['folder_id']] ?? null;
                if ($ownerId === null
                    || personalOwnerConflictsWithCreator($ownerId, $creators[(int) $object['item_id']] ?? null) === true
                ) {
                    ++$stats['owner_unresolved'];
                    continue;
                }

                if (isset($ownerPublicKeys[$ownerId]) === false) {
                    $ownerPublicKeys[$ownerId] = (string) DB::queryFirstField(
                        'SELECT public_key FROM ' . prefixTable('users') . '
                        WHERE id = %i AND deleted_at IS NULL',
                        $ownerId
                    );
                }
                if ($ownerPublicKeys[$ownerId] === '') {
                    ++$stats['owner_unresolved'];
                    continue;
                }

                // No reference key: leave the object exactly as it is, foreign keys included.
                if (isset($tpRefs[$objectId]) === false) {
                    ++$stats['no_reference'];
                    continue;
                }
                $objectKey = decryptUserObjectKeyWithMigration(
                    (string) $tpRefs[$objectId]['share_key'],
                    $tpPrivateKey,
                    $tpPublicKey,
                    (int) $tpRefs[$objectId]['increment_id'],
                    $sharekeysTable
                );
                if (empty($objectKey) === true) {
                    ++$stats['no_reference'];
                    $this->logger->log('restore_missing_sharekeys: cannot decrypt TP_USER sharekey for personal ' . $sharekeysTable . ' object #' . $objectId, 'WARNING');
                    continue;
                }

                if (isset($validV3[$objectId][$ownerId]) === false) {
                    try {
                        if (insertOrUpdateSharekey(
                            prefixTable($sharekeysTable),
                            $objectId,
                            $ownerId,
                            encryptUserObjectKey($objectKey, $ownerPublicKeys[$ownerId])
                        ) === true) {
                            ++$stats['owner_keys'];
                        }
                    } catch (Exception $e) {
                        $this->logger->log('restore_missing_sharekeys: cannot encrypt for owner #' . $ownerId . ' (personal ' . $sharekeysTable . ' object #' . $objectId . '): ' . $e->getMessage(), 'WARNING');
                        continue;
                    }
                }

                // The object is recoverable from now on, so the foreign keys can go (invariant I1).
                $foreignUserIds = foreignSharekeyUserIds(
                    $holders[$objectId] ?? [],
                    personalSharekeyKeepList($ownerId, $systemUserIds)
                );
                if (count($foreignUserIds) > 0) {
                    DB::delete(
                        prefixTable($sharekeysTable),
                        'object_id = %i AND user_id IN %li',
                        $objectId,
                        $foreignUserIds
                    );
                    $stats['foreign_removed'] += count($foreignUserIds);
                }
            }

            // Heartbeat so the task is not considered stalled
            DB::update(
                prefixTable('background_tasks'),
                ['updated_at' => time()],
                'increment_id = %i',
                $this->taskId
            );
        }

        return $stats;
    }

    /**
     * Restore missing or broken sharekeys for one object type (items, fields or
     * files). Objects are walked in id-ascending batches; for each object with
     * at least one user key missing or still in legacy v1 encryption, the object
     * key is decrypted with the TP_USER sharekey and re-encrypted (as v3) for
     * every such user. A legacy v1 row is overwritten in place via the
     * (object_id, user_id) unique key.
     *
     * @param string $objectsQuery Paginated query returning object ids (placeholders: lastId, limit)
     * @param string $sharekeysTable Sharekeys table name (without prefix)
     * @param string $tpPrivateKey TP_USER decrypted private key
     * @param string $tpPublicKey TP_USER public key (for v1->v3 sharekey migration)
     * @param array $users Eligible users (id + public_key)
     * @return array ['created' => int, 'unrecoverable' => int]
     */
    private function restoreScopeMissingSharekeys(
        string $objectsQuery,
        string $sharekeysTable,
        string $tpPrivateKey,
        string $tpPublicKey,
        array $users
    ): array {
        $eligibleIds = [];
        $publicKeys = [];
        foreach ($users as $user) {
            $eligibleIds[] = (int) $user['id'];
            $publicKeys[(int) $user['id']] = (string) $user['public_key'];
        }

        $lastId = 0;
        $created = 0;
        $unrecoverable = 0;
        $batchSize = 100;

        while (true) {
            $objects = DB::query($objectsQuery, $lastId, $batchSize);
            if (count($objects) === 0) {
                break;
            }

            $objectIds = [];
            foreach ($objects as $object) {
                $objectIds[] = (int) $object['id'];
            }
            $lastId = max($objectIds);

            // Map of existing VALID sharekeys for the batch (object -> users).
            // Only non-empty v3 keys count as valid: a legacy v1 key that
            // survived a "completed" migration is very likely broken (it never
            // self-migrated on access), which leaves custom fields unreadable
            // (issue #5252). Missing, empty and v1 keys are all rebuilt in place
            // from the authoritative TP_USER object key below.
            $existing = [];
            $pairs = DB::query(
                'SELECT object_id, user_id
                FROM ' . prefixTable($sharekeysTable) . '
                WHERE object_id IN %li AND share_key != "" AND encryption_version = 3',
                $objectIds
            );
            foreach ($pairs as $pair) {
                $existing[(int) $pair['object_id']][(int) $pair['user_id']] = true;
            }

            // TP_USER reference keys for the batch
            $tpRefs = [];
            $refs = DB::query(
                'SELECT object_id, share_key, increment_id
                FROM ' . prefixTable($sharekeysTable) . '
                WHERE object_id IN %li AND user_id = %i AND share_key != ""',
                $objectIds,
                TP_USER_ID
            );
            foreach ($refs as $ref) {
                $tpRefs[(int) $ref['object_id']] = $ref;
            }

            $newRows = [];
            foreach ($objectIds as $objectId) {
                // Users with no valid v3 key: either missing entirely or still
                // holding a broken/legacy v1 key that must be rebuilt.
                $rebuildUserIds = [];
                foreach ($eligibleIds as $userId) {
                    if (isset($existing[$objectId][$userId]) === false) {
                        $rebuildUserIds[] = $userId;
                    }
                }
                if (count($rebuildUserIds) === 0) {
                    continue;
                }
                if (isset($tpRefs[$objectId]) === false) {
                    // No reference key: a user who can still open the object must re-save it
                    $unrecoverable++;
                    continue;
                }

                $objectKey = decryptUserObjectKeyWithMigration(
                    (string) $tpRefs[$objectId]['share_key'],
                    $tpPrivateKey,
                    $tpPublicKey,
                    (int) $tpRefs[$objectId]['increment_id'],
                    $sharekeysTable
                );
                if (empty($objectKey)) {
                    $unrecoverable++;
                    $this->logger->log('restore_missing_sharekeys: cannot decrypt TP_USER sharekey for ' . $sharekeysTable . ' object #' . $objectId, 'WARNING');
                    continue;
                }

                foreach ($rebuildUserIds as $userId) {
                    try {
                        $newRows[] = [
                            'object_id'          => $objectId,
                            'user_id'            => $userId,
                            'share_key'          => encryptUserObjectKey($objectKey, $publicKeys[$userId]),
                            'encryption_version' => 3,
                        ];
                        $created++;
                    } catch (Exception $e) {
                        $this->logger->log('restore_missing_sharekeys: cannot encrypt for user #' . $userId . ' (' . $sharekeysTable . ' object #' . $objectId . '): ' . $e->getMessage(), 'WARNING');
                    }
                }

                // Flush periodically to bound memory usage
                if (count($newRows) >= 500) {
                    batchUpsertSharekeys(prefixTable($sharekeysTable), $newRows);
                    $newRows = [];
                }
            }

            if (count($newRows) > 0) {
                batchUpsertSharekeys(prefixTable($sharekeysTable), $newRows);
            }

            // Heartbeat so the task is not considered stalled
            DB::update(
                prefixTable('background_tasks'),
                ['updated_at' => time()],
                'increment_id = %i',
                $this->taskId
            );
        }

        return ['created' => $created, 'unrecoverable' => $unrecoverable];
    }
}
