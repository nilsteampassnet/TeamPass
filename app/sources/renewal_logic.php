<?php

declare(strict_types=1);

const RENEWAL_DUE_SOON_DAYS = 14;

/** Validate an optional item policy; zero disables only the individual policy. */
function renewalValidatePeriod(mixed $value): int
{
    $days = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 36500]]);
    if ($days === false || is_bool($value) || $value === null) {
        throw new InvalidArgumentException('The renewal period must be a whole number from 0 to 36500 days.');
    }
    return $days;
}

/** The shortest active policy wins; disabling folder expiration never disables item policies. */
function renewalEffectiveDays(int $itemDays, int $folderDays, bool $folderEnabled): int
{
    $itemDays = max(0, $itemDays);
    $folderDays = $folderEnabled ? max(0, $folderDays) : 0;
    return $itemDays > 0 && $folderDays > 0 ? min($itemDays, $folderDays) : max($itemDays, $folderDays);
}

/** SQL equivalent of renewalEffectiveDays. Expressions are trusted source-code column references. */
function renewalPeriodSql(bool $folderEnabled, string $item = 'i.renewal_period', string $folder = 'n.renewal_period'): string
{
    $item = 'COALESCE(' . $item . ', 0)';
    if (!$folderEnabled) {
        return $item;
    }
    $folder = 'COALESCE(' . $folder . ', 0)';
    return '(CASE WHEN ' . $item . ' > 0 AND (' . $folder . ' <= 0 OR ' . $item . ' < ' . $folder . ')'
        . ' THEN ' . $item . ' ELSE ' . $folder . ' END)';
}

/** Password age comes from creation/password-change history, with the creation column as fallback. */
function renewalBaseDateSql(string $history = 'l.last_relevant_date', string $created = 'i.created_at'): string
{
    return 'COALESCE(NULLIF(CAST(' . $history . ' AS UNSIGNED), 0), NULLIF(CAST(' . $created . ' AS UNSIGNED), 0), 0)';
}

/** An unknown password age must never become an expiry calculated from the Unix epoch. */
function renewalDueAt(int $days, int $baseDate): ?int
{
    return $days > 0 && $baseDate > 0 ? $baseDate + $days * 86400 : null;
}

/** Describe the effective policy for display, without treating an unknown date as expired. */
function renewalStatus(int $days, ?int $dueAt, array $settings, ?int $now = null): array
{
    $now ??= time();
    $days = max(0, $days);
    $dueAt = $days > 0 && $dueAt !== null && $dueAt > 0 ? $dueAt : null;
    $state = 'none';
    if ($days > 0) {
        $state = $dueAt === null ? 'unknown' : ($dueAt <= $now ? 'expired'
            : ($dueAt <= $now + RENEWAL_DUE_SOON_DAYS * 86400 ? 'soon' : 'scheduled'));
    }
    return [
        'days' => $days,
        'due_at' => $dueAt,
        'due_date' => $dueAt === null ? '' : date($settings['date_format'] ?? 'Y-m-d', $dueAt),
        'state' => $state,
    ];
}
