<?php

declare(strict_types=1);

/**
 * Add the opt-in item renewal period. Called by the 3.2.2 upgrade chain so
 * web upgrades and Docker schema-floor replays execute the same migration.
 */
function upgradeItemRenewalPolicy(): bool
{
    return addColumnIfNotExist(prefixTable('items'), 'renewal_period', 'INT UNSIGNED NOT NULL DEFAULT 0') !== false;
}
