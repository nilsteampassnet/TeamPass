# Password Sanitization Fix - Migration Guide

**Version:** 3.1.5.10
**Date:** 2025-12-07
**Issue:** Password sanitization breaking authentication

## Context

TeamPass had an architectural design flaw since its early versions: user authentication passwords were being sanitized using `FILTER_SANITIZE_FULL_SPECIAL_CHARS` before being hashed and verified.

### Why this is a problem

**Security Best Practice:** Passwords should NEVER be sanitized. They must be treated as **opaque binary data** and passed directly to the password hashing/verification functions.

**Impact of sanitization:**
- Characters like `<`, `>`, `&`, `"`, `'` were being HTML-encoded
- Example: Password `P@ssw0rd<123>` was transformed to `P@ssw0rd&lt;123&gt;`
- Users who chose passwords with these characters couldn't authenticate
- API authentication failed for users with special characters in passwords
- LDAP/AD authentication was passing sanitized passwords to external systems
- Workarounds were needed in multiple places

## Migration Strategy

We implemented **Strategy: Transparent Migration with Dual Verification + Immediate Hash Update** to ensure zero downtime and automatic migration.

### Migration happens in ONE login

When a legacy user logs in:
1. Password verified with sanitized version (old hash in DB)
2. **Immediately**: Re-hash the raw password and save to DB
3. User is migrated in real-time during login
4. Next login: Works directly with raw password

### How it works

1. **Password input no longer sanitized** - New authentication attempts use raw passwords
2. **Dual verification on login:**
   - First attempt: Verify with raw password (new behavior) ✅
   - If fails: Try with sanitized password (legacy behavior) ⚠️
   - If legacy succeeds: **Immediate migration happens**
3. **Automatic migration on first login:**
   - Re-hash the raw password entered by user
   - Save new hash to database
   - Mark migration as complete (`needs_password_migration = 0`)
4. **Next login:** User authenticates directly with raw password (no more dual verification needed)
5. **No user action required** - Migration is completely transparent

## Database Changes

### New column added to `teampass_users`

```sql
ALTER TABLE `teampass_users`
ADD COLUMN `needs_password_migration` TINYINT(1) DEFAULT 0
AFTER `pw`;
```

### Users marked for migration

All local authentication users (not LDAP/OAuth2) are initially marked as needing migration:

```sql
UPDATE `teampass_users`
SET `needs_password_migration` = 1
WHERE (`auth_type` = 'local' OR `auth_type` IS NULL OR `auth_type` = '');
```

**Important:** The upgrade script is **idempotent**:
- First run: Creates column and marks users
- Subsequent runs: Detects existing column and skips marking
- This ensures already migrated users are never reset

## Impact on Authentication Methods

### Local Authentication (Teampass Database)
✅ **Fully supported** - Dual verification ensures existing users work, new passwords work correctly

### LDAP / Active Directory
✅ **Critical fix** - Passwords are now passed to LDAP servers without modification
- Previously, sanitized passwords were failing LDAP bind operations
- Now works correctly for all password characters

### OAuth2 / SSO
✅ **No impact** - OAuth2 doesn't use passwords for authentication

### API Authentication
✅ **Fixed** - JWT generation now works with unsanitized passwords

## Rollback Plan

If issues occur, you can rollback by:

1. **Restore previous version:**
   ```bash
   git checkout HEAD~1 includes/config/include.php
   # (or restore to 3.1.5.9)
   ```

2. **Remove migration column (optional):**
   ```sql
   ALTER TABLE `teampass_users` DROP COLUMN `needs_password_migration`;
   ```

Note: This will restore the sanitization bug, but existing users will continue to work.

## Monitoring

### Check migration progress

```sql
-- Count users needing migration
SELECT COUNT(*) as needs_migration
FROM teampass_users
WHERE needs_password_migration = 1
AND (auth_type = 'local' OR auth_type IS NULL);

-- Count migrated users
SELECT COUNT(*) as migrated
FROM teampass_users
WHERE needs_password_migration = 0
AND (auth_type = 'local' OR auth_type IS NULL);
```

### Check migration events in logs

```sql
SELECT *
FROM teampass_log_system
WHERE label = 'legacy_sanitized_password_detected'
ORDER BY date DESC;
```

## Support

### Common Issues

**Q: User can't login after upgrade**
- Check if password contains special characters
- Verify dual verification is working
- Check application logs for errors

**Q: LDAP authentication failing**
- Ensure LDAP credentials don't contain HTML entities
- Test with simple password first
- Check LDAP server logs

**Q: API authentication broken**
- Regenerate API key
- Ensure password doesn't have trailing spaces
- Check JWT token generation logs

## Security Considerations

### Why dual verification is safe

1. Both password hashes are compared using `password_verify()`
2. No plaintext passwords are logged
3. Timing attacks prevented by password_verify's constant-time comparison
4. Migration happens server-side only
5. Users are marked for migration in audit logs

### Password handling best practices now enforced

✅ No sanitization before hashing
✅ No sanitization before verification
✅ No sanitization during password change
✅ Passwords treated as opaque binary data
✅ Direct pass-through to bcrypt/argon2

## References

- OWASP Password Storage Cheat Sheet
- PHP password_hash() documentation
- NIST Digital Identity Guidelines (SP 800-63B)