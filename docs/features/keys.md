<!-- docs/features/keys.md -->

> 🚧 Under construction

## Generalities

In Teampass, all encrypted elements (such as passwords and encrypted fields) have a unique key for each user. 
This key is encrypted with his/hers login password.
Such a process ensures a high level of security for all data stored in the database through Teampass.

💡 [Read more](../install/encryption.md) about this encryption process.

## Legacy recovery keys download

The former profile workflow used to let users download a file containing their public and private recovery keys.

This workflow has been removed because it was obsolete and was no longer backed by an active account recovery process. Users should no longer be asked to download this file from their profile.

The historical `keys_recovery_time` metadata may still exist in older installations and can be kept for compatibility or for a future replacement notification workflow.

## Regenerate your keys

Key regeneration remains available from the personal menu when a user's encryption keys need to be renewed. This operation is independent from the removed profile recovery keys download.

1. Select entry `Generate new keys` in personal menu.
2. Fill in the required confirmation fields.
3. Click `Confirm`.
4. Once started, the process runs in background during several minutes. You can still use Teampass, but passwords may remain blank until the process finishes.

> 💡 During this process, you can change page and even leave Teampass.

---

## Local password recovery

> This feature must be enabled by an administrator (`enable_local_password_recovery` setting).

Local password recovery allows a user to regain access to their account through the **Forgot password** link on the login page, without contacting an administrator.

### How it works

When a standard local user requests a password reset, Teampass can send a temporary password by email and run the existing key regeneration workflow for that account.

> 🔔 Local password recovery is not the same feature as the removed profile recovery keys download.

### Enabling local password recovery (admin)

1. Go to **Admin → Settings → Security**.
2. Enable **Enable local password recovery**.
3. Click **Save**.

Once enabled, the **Forgot password?** link is shown on the login page for standard accounts. LDAP and OAuth2 accounts are excluded — password changes for those accounts must go through the identity provider.
