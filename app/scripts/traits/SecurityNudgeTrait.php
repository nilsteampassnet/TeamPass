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
 * @file      SecurityNudgeTrait.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Language\Language;

/**
 * Proactive Health Nudges (F8) — email digest.
 *
 * Sends each opted-in user a periodic summary of their actionable security posture
 * (breached / weak / reused / overdue credential COUNTS only — never any item label
 * or plaintext, preserving zero-knowledge). Cadence is enforced per user through the
 * companion `user_nudges` table. Builds on the Security Posture Dashboard data (F1):
 * the counts come from `securityNudgeComputeCounts()`, which is metadata-only and runs
 * fine in this CLI worker (no private key required).
 */
trait SecurityNudgeTrait
{
    /**
     * Drain the per-user digest cycle: for every active user due by cadence, queue a
     * counts-only email when they have at least one actionable finding.
     *
     * @return void
     */
    private function handleSecurityNudgeDigest(): void
    {
        if (function_exists('loadClasses') && !class_exists('DB')) {
            loadClasses('DB');
        }

        // Gated by the dashboard + nudges + email toggles (admin settings via ConfigManager).
        $dashboardEnabled = (int) ($this->settings['security_dashboard_enabled'] ?? 0);
        $nudgesEnabled = (int) ($this->settings['security_nudges_enabled'] ?? 0);
        $emailEnabled = (int) ($this->settings['security_nudges_email_enabled'] ?? 0);
        if ($dashboardEnabled !== 1 || $nudgesEnabled !== 1 || $emailEnabled !== 1) {
            return;
        }

        $frequencyDays = (int) ($this->settings['security_nudges_email_frequency_days'] ?? 7);
        if ($frequencyDays < 0) {
            $frequencyDays = 7;
        }
        $now = time();
        $dueBefore = $now - ($frequencyDays * 86400);

        $cpassmanUrl = (string) ($this->settings['cpassman_url'] ?? '');
        $dashboardUrl = rtrim($cpassmanUrl, '/') . '/index.php?page=dashboard';

        // Exclude system accounts.
        $excludeIds = [];
        foreach (['TP_USER_ID', 'API_USER_ID', 'OTV_USER_ID', 'SSH_USER_ID'] as $c) {
            if (defined($c) && is_numeric(constant($c))) {
                $excludeIds[] = (int) constant($c);
            }
        }
        $excludeIds = array_values(array_unique($excludeIds));

        $sql = 'SELECT u.id, u.login, u.email, u.name, u.lastname, u.user_language,
                    COALESCE(un.last_digest_at, 0) AS last_digest_at
                FROM ' . prefixTable('users') . ' AS u
                LEFT JOIN ' . prefixTable('user_nudges') . ' AS un ON (un.user_id = u.id)
                WHERE u.email IS NOT NULL AND u.email <> ""
                AND u.disabled = 0
                AND (u.deleted_at IS NULL OR u.deleted_at = "" OR u.deleted_at = 0)';
        if (count($excludeIds) > 0) {
            $sql .= ' AND u.id NOT IN %li';
            $users = DB::query($sql, $excludeIds);
        } else {
            $users = DB::query($sql);
        }

        $sent = 0;
        $skipped = 0;
        foreach ($users as $u) {
            $userId = (int) ($u['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            // Per-user cadence.
            if ((int) $u['last_digest_at'] > $dueBefore) {
                $skipped++;
                continue;
            }

            try {
                $counts = securityNudgeComputeCounts($userId);
                $actionable = $counts['breached'] + $counts['weak'] + $counts['reused'] + $counts['overdue'];
                if ($actionable <= 0) {
                    // Nothing to nudge — still record the run so we skip until next cycle.
                    $this->recordDigestSent($userId, $now);
                    $skipped++;
                    continue;
                }

                $userLang = (string) ($u['user_language'] ?? '');
                if ($userLang === '') {
                    $userLang = (string) ($this->settings['default_language'] ?? 'english');
                }
                $lang = new Language($userLang);

                $subject = $lang->get('security_nudges_email_subject');
                $body = str_replace(
                    ['#breached#', '#weak#', '#reused#', '#overdue#', '#total#', '#url#'],
                    [
                        (string) $counts['breached'],
                        (string) $counts['weak'],
                        (string) $counts['reused'],
                        (string) $counts['overdue'],
                        (string) $counts['total_flagged'],
                        $dashboardUrl,
                    ],
                    $lang->get('security_nudges_email_body')
                );

                $receiverName = trim((string) ($u['name'] ?? '') . ' ' . (string) ($u['lastname'] ?? ''));
                if ($receiverName === '') {
                    $receiverName = (string) ($u['login'] ?? '');
                }

                prepareSendingEmail($subject, $body, (string) $u['email'], $receiverName);
                $this->recordDigestSent($userId, $now);
                $sent++;
            } catch (\Throwable $e) {
                if (LOG_TASKS === true) {
                    $this->logger->log('security_nudge_digest user_id=' . $userId . ' error: ' . $e->getMessage(), 'ERROR');
                }
            }
        }

        if (LOG_TASKS === true) {
            $this->logger->log('security_nudge_digest: sent=' . $sent . ' skipped=' . $skipped, 'INFO');
        }
    }

    /**
     * Record (upsert) the last digest timestamp for a user.
     *
     * @param int $userId User id.
     * @param int $ts     Unix timestamp of the run.
     *
     * @return void
     */
    private function recordDigestSent(int $userId, int $ts): void
    {
        DB::query(
            'INSERT INTO ' . prefixTable('user_nudges') . ' (user_id, last_digest_at)
            VALUES (%i, %i)
            ON DUPLICATE KEY UPDATE last_digest_at = VALUES(last_digest_at)',
            $userId,
            $ts
        );
    }
}
