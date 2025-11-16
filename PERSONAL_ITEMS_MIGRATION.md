# Personal Items Migration to Unified Encryption System

## Overview

Starting from version 3.1.5, Teampass is migrating from a dual encryption system to a unified encryption approach for all items (both shared and personal). This migration is **automatic** and **transparent** for users.

### Why This Migration?

Previously, Teampass used two different encryption methods:
- **Shared items**: Used the sharekeys system (item encrypted with a unique key, then key encrypted with each user's public RSA key)
- **Personal items**: Used a simpler encryption method

This dual system created:
- ❌ Code complexity and maintenance burden
- ❌ Difficulty converting personal items to shared items
- ❌ Potential security inconsistencies
- ❌ Confusion for users and administrators

The **unified sharekeys system** provides:
- ✅ Single, proven encryption mechanism for all items
- ✅ Seamless conversion between personal and shared items
- ✅ Simplified codebase and easier maintenance
- ✅ Consistent security across all items
- ✅ Better scalability for large deployments

---

## How It Works

### Technical Architecture

The unified system works as follows:

1. **Each item** gets a unique 256-bit encryption key (sharekey)
2. **Item password** is encrypted with this sharekey using AES-256
3. **Sharekey** is encrypted with user's RSA 4096-bit public key
4. **Encrypted sharekey** is stored in `teampass_sharekeys_items` table

**For personal items**: A personal item is simply a shared item with sharekeys for only one user (the owner).

### Migration Process

The migration happens **automatically at user login**:

```
┌─────────────────────────────────────────────────────────────┐
│                    USER LOGIN                               │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
        ┌────────────────────────────┐
        │ Check user migration flag  │
        │ personal_items_migrated=0? │
        └────────┬───────────────────┘
                 │
        ┌────────┴────────┐
        │ YES             │ NO
        ▼                 ▼
┌───────────────┐   ┌──────────────┐
│ Start         │   │ Nothing      │
│ Migration     │   │ to do        │
└───────┬───────┘   └──────────────┘
        │
        ▼
┌─────────────────────────────────┐
│ Create background migration     │
│ task with encrypted private key │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│ Process batches                 │
│ in background via cron          │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│ For each personal item:          │
│ 1. Decrypt old password          │
│ 2. Generate new sharekey         │
│ 3. Encrypt password with sharekey│
│ 4. Encrypt sharekey with pub key │
│ 5. Store in sharekeys_items      │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│ Mark user as migrated           │
│ personal_items_migrated = 1     │
└─────────────────────────────────┘
```

---

## User Experience

### What Users Will See

#### First Login After Migration

When a user logs in for the first time after the migration is deployed:

1. **Notification appears**: "Account is being migrated, please wait a couple of minutes."
2. **Completion**: Requires the user to refresh the page

### Important: No Action Required

✅ **Users don't need to do anything**  
✅ **Users can continue working during migration**  
✅ **Personal items remain accessible during migration** (nevertheless prefer not to edit them)
✅ **No data loss risk**  
✅ **No password changes required**

---

## Administrator Guide

### Monitoring Migration Progress

Administrators can monitor the migration status from the **Administration** panel:

1. Navigate to **Administration homepage**
2. Click **"Get personal items migration status"** button
3. View statistics:
   - Total users
   - Migrated users
   - Pending users
   - Overall progress percentage
   - List of users by status

### Migration States

Each user can be in one of these states:

| State | Description | Flag Value |
|-------|-------------|------------|
| **Not Started** | User hasn't logged in since migration deployment | `personal_items_migrated = 0` |
| **Completed** | All personal items migrated | `personal_items_migrated = 1` |

### Background Tasks

Migration uses Teampass's background task system:

- **Task type**: `migrate_user_personal_items`

### Troubleshooting

#### User Migration Not Starting

**Problem**: User logs in but migration doesn't start

**Possible causes**:
1. User has no personal items → Automatically marked as migrated
2. User already migrated → Check `teampass_users.personal_items_migrated`
3. Migration task already exists → Check `teampass_background_tasks`

**Solution**:
```sql
-- Check user status
SELECT id, login, personal_items_migrated 
FROM teampass_users 
WHERE login = 'username';

-- Check active tasks
SELECT * FROM teampass_background_tasks 
WHERE process_type = 'migrate_user_personal_items' 
AND item_id = [user_id];
```

#### Migration Stuck

**Problem**: Migration shows progress but doesn't complete

**Possible causes**:
1. User logged out → Migration paused (normal behavior)
2. Cron not running → Background tasks not processed
3. Error in specific item → Check subtask error_message

**Solution**:
```sql
-- Check subtasks status
SELECT status, error_message, COUNT(*) 
FROM teampass_background_subtasks st
JOIN teampass_background_tasks t ON st.task_id = t.increment_id
WHERE t.process_type = 'migrate_user_personal_items'
AND t.item_id = [user_id]
GROUP BY status, error_message;
```

#### Force Re-migration

If you need to force a user to re-migrate:

```sql
-- Reset migration flag
UPDATE teampass_users 
SET personal_items_migrated = 0 
WHERE id = [user_id];

-- Cancel pending tasks
UPDATE teampass_background_tasks 
SET status = 'cancelled' 
WHERE process_type = 'migrate_user_personal_items' 
AND item_id = [user_id]
AND status IN ('pending', 'in_progress');

-- User will be prompted to migrate on next login
```

---

## Technical Details

### Database Changes

#### New Column in `teampass_users`

```sql
ALTER TABLE `teampass_users` 
ADD COLUMN `personal_items_migrated` TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Personal items migrated to sharekeys system (0=not migrated, 1=migrated)';

ALTER TABLE `teampass_users` 
ADD INDEX `idx_personal_items_migrated` (`personal_items_migrated`);
```

### Security Considerations

#### Private Key Handling

During migration, the user's private key must be temporarily stored to encrypt sharekeys:

1. **At login**: Private key is decrypted with user's password
2. **Encryption**: Private key is encrypted with Defuse Crypto using a unique migration session key
3. **Storage**: Encrypted private key stored in subtask JSON (temporary)
4. **Processing**: Background worker decrypts private key only when user session is active
5. **Cleanup**: Encrypted private key removed from subtasks once done

**Important**: Private key is never stored in plain text and is only accessible when user is logged in.

#### Session Validation

Background tasks verify user session is active before processing:

```php
function checkUserSessionActive($userId, $migrationSessionKey) {
    // Verify user session hasn't expired
    $sessionActive = DB::queryFirstField(
        "SELECT COUNT(*) FROM teampass_users 
         WHERE id = %i AND session_end > %i",
        $userId, time()
    );
    return $sessionActive > 0;
}
```

If session expires:
- Migration is **paused** (not cancelled)
- Resumes automatically on next login
- No data loss or security risk

### Performance Impact

#### Login Performance

- **Additional check**: 1 SQL query (`SELECT personal_items_migrated`)
- **Impact**: Negligible (~1-2ms)
- **Optimization**: Indexed column for fast lookup

#### Database Impact

- **New rows**: 1 row per personal item in `teampass_sharekeys_items`
- **Example**: User with 200 personal items → +200 rows
- **Storage increase**: ~100 bytes per sharekey × number of items

---

## Migration Phases

### Phase 1: Preparation (Before Deployment)

**Admin tasks**:
1. ✅ Backup database
2. ✅ Test migration on staging environment
3. ✅ Verify cron is running correctly
4. ✅ Review migration documentation

### Phase 2: Deployment

**Actions**:
1. Deploy new Teampass version
2. Run update process
3. Verify migration column exists

### Phase 3: Progressive Migration (User-Triggered)

**Process**:
- Users migrate as they log in
- No specific timeline
- Monitor progress via admin dashboard

**Users who never log in**: Will be migrated when they eventually log in (no rush, no risk)

### Phase 4: Verification

**Admin tasks**:
1. Check migration statistics
2. Verify no errors in background_tasks
3. Review system logs for issues

**SQL queries**:
```sql
-- Overall progress
SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN personal_items_migrated = 1 THEN 1 ELSE 0 END) as migrated,
    SUM(CASE WHEN personal_items_migrated = 0 THEN 1 ELSE 0 END) as pending
FROM teampass_users
WHERE disabled = 0;

-- Check for errors
SELECT t.item_id, u.login, st.error_message
FROM teampass_background_subtasks st
JOIN teampass_background_tasks t ON st.task_id = t.increment_id
JOIN teampass_users u ON t.item_id = u.id
WHERE t.process_type = 'migrate_user_personal_items'
AND st.status = 'error';
```

---

## Frequently Asked Questions

### Will my personal items be accessible during migration?

Prefer not to edit the personal items.

### What happens if I log out during migration?

Migration is **continuing**. No data is lost.

### Can I convert personal items to shared items after migration?

**Yes**

### What if migration fails for an item?

- The item remains accessible with the old encryption
- Error is logged in background_tasks
- Admin can check error details and retry if needed
- Migration continues for other items

### Will this affect API access?

**No**. API users (including TP_USER, OTV, API accounts) are automatically marked as migrated since they don't have personal items.

### Do I need to change my password?

**No**. Your password remains the same. The migration only changes how personal item passwords are encrypted.

### Can I rollback the migration?

**Not recommended**, but technically possible using a backup.

⚠️ **Warning**: Only rollback if absolutely necessary and with proper database backup.
