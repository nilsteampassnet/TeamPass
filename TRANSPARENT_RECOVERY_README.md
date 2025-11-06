# Transparent Key Recovery Feature

## Overview

The **Transparent Key Recovery** feature provides automatic re-encryption of user private keys when external password changes are detected (LDAP/OAuth2). This eliminates the need for manual intervention and ensures seamless user experience.

## Problem Solved

Previously, when users changed their passwords externally (in Active Directory or OAuth2 provider), their encrypted private keys in TeamPass could not be decrypted. This required:
- Manual intervention from administrators
- Users to provide their old password
- Service interruption until the issue was resolved

With 500+ users changing passwords annually, this resulted in approximately 1.4 support tickets per day.

## Solution

The system now maintains a **backup copy of the private key** encrypted with a **derived key** that is independent of the user's password. When a password change is detected:

1. TeamPass attempts to decrypt the private key with the new password
2. If that fails, it uses the derived key to decrypt the backup
3. The private key is automatically re-encrypted with the new password
4. The user experiences no interruption

## Architecture

### Cryptographic Design

```
User Private Key (RSA 4096)
    ├─> Encrypted with user password (standard) → private_key
    └─> Encrypted with derived key (backup)     → private_key_backup

Derived Key = PBKDF2(user_seed + hash(public_key), 100k iterations)
```

### Database Schema

New columns added to `teampass_users`:

| Column | Type | Description |
|--------|------|-------------|
| `user_derivation_seed` | VARCHAR(64) | Unique random seed (256 bits) |
| `private_key_backup` | TEXT | Private key encrypted with derived key |
| `key_integrity_hash` | VARCHAR(64) | HMAC integrity check |
| `last_pw_change` | INT(12) | Timestamp of last password change |

### Security Features

1. **PBKDF2 Key Derivation** - 100,000 iterations make brute-force attacks infeasible
2. **Unique Per-User Seed** - 256-bit random seed ensures uniqueness
3. **Public Key Binding** - Derived key is tied to user's public key
4. **Integrity Checking** - HMAC verification detects tampering
5. **No Master Key** - No single point of failure

## Installation

### 1. Run Database Migration

During upgrade process, it will:
- Add new columns to the users table
- Generate seeds for existing users
- Create configuration setting

### 2. Configure Settings

The following setting is available in the admin panel:

| Setting | Default | Description |
|---------|---------|-------------|
| `transparent_key_recovery_pbkdf2_iterations` | 100000 | PBKDF2 iterations (adjust for performance) |

### 3. Server Secret Generation

The server secret used is the existing SECUREFILE

**IMPORTANT**: Back up this file! If lost, transparent recovery will fail.

## Usage

### For End Users

**Nothing changes!** The feature is completely transparent:
- Users authenticate with their current password (LDAP/OAuth2)
- If password was changed externally, re-encryption happens automatically
- No interruption or additional steps required

### For Administrators

#### Monitoring

Check recovery statistics from admin home page.

#### Logs

Key events are logged in `teampass_log_system`:

| Event | Description |
|-------|-------------|
| `auto_reencryption_success` | Successful automatic re-encryption |
| `auto_reencryption_failed` | Re-encryption failed (non-critical) |
| `auto_reencryption_critical_failure` | Critical failure, user disabled |
| `key_integrity_check_failed` | Integrity verification failed |

#### Alerts

If failure rate exceeds 5%, review:
- Server secret file integrity
- Database integrity
- Recent system changes

## Migration Process

### Existing Users

1. **Phase 1 (Upgrade process)**: Generates `user_derivation_seed` for all users
2. **Phase 2 (First Login)**: Creates `private_key_backup` when user logs in
3. **Complete**: User has full transparent recovery capability

### New Users

New users created after deployment automatically receive all recovery data.

### High Failure Rate

**Symptoms**: More than 5% recoveries failing

**Actions**:
1. Verify server secret integrity
2. Check database for tampering (integrity hashes)
3. Review recent system changes
4. Inspect logs for patterns

### Performance Issues

**Symptoms**: Slow logins after deployment

**Actions**:
1. Reduce PBKDF2 iterations (e.g., 50,000)
2. Check server resources (CPU)
3. Verify database indexes created

## Credits

Developed for TeamPass 3.1.5+
License: GPL-3.0

## Support

For issues or questions:
- GitHub: https://github.com/nilsteampassnet/TeamPass
- Documentation: https://teampass.net

---

**Last Updated**: 2025-11-06
