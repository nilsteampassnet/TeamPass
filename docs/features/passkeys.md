<!-- docs/features/passkeys.md -->

## Overview

> Requires **TeamPass 3.2.3 or later**.

TeamPass uses passkeys for two independent things, each with its own settings:

| Feature | What it does | Where it is configured |
|---|---|---|
| **[Passkeys of other sites](#passkeys-of-other-sites)** | TeamPass keeps the passkeys of third-party sites in its items, and the browser extension signs in with them | Settings → API → Browser Extension |
| **[Signing in to TeamPass](#signing-in-to-teampass-with-a-passkey)** | A passkey confirms, or replaces, the password of a TeamPass account | Settings → MFA → Passkeys |

Turning one on does not turn the other on, and a passkey of one is never usable by the other.

---

## Passkeys of other sites

> Requires a version of the [browser extension](../misc/extension.md#passkeys) that supports passkeys.

A **passkey** replaces the password on the sites that support it. With this feature, TeamPass keeps the passkeys of third-party sites in its items, like it keeps their passwords: when a site offers to create a passkey, the browser extension saves it in an item, and when the site asks for it later, the extension signs in with it.

The private key of a passkey **never leaves the TeamPass server**. It is generated there, encrypted like an item password, and used there: the extension only relays the request of the site and receives a signature in return.

---

### Enabling passkeys

Passkeys are disabled by default. In **Settings → API → Browser Extension**:

| Setting | Description |
|---------|-------------|
| **Allow the browser extension to save passkeys** (`webauthn_provider_enabled`) | Turns the feature on for the whole instance. Default: off |
| **Notify users when a passkey is saved** (`webauthn_email_on_add`) | Emails the user who saved a passkey. Default: on. Requires an email server |

Once the switch is on, the extension of every user uses TeamPass for passkeys. Each user can still turn it off for themselves in the extension settings (**Settings → Passkeys → Use TeamPass for passkeys**).

The API rights of each user (**Settings → API → Users**) decide what the extension may do on their behalf:

| Operation | API right needed |
|---|---|
| Sign in with a passkey | Read |
| Save a passkey on an existing item, delete a passkey | Update |
| Save a passkey on a new item | Create, then Update |

These rights come on top of the TeamPass rights of the user, never beyond them: saving or deleting a passkey is a modification of its item, so it also needs the right to edit the item.

> ⚠️ **Serve TeamPass over HTTPS and enable *Require HTTPS for API requests*.** Whoever intercepts the session of the extension can ask TeamPass to sign in to third-party sites. **Monitoring → System Health** warns when passkeys are enabled while the API still accepts plain HTTP.

---

### Who can use a passkey

**Everyone who can open an item can use its passkeys.** This is the point of keeping them in TeamPass: a passkey saved in a shared folder signs in for every member of that folder, exactly like its password.

| Location of the item | Who can use the passkey |
|---|---|
| Shared folder | Every user with access to the folder, minus those excluded by an item restriction |
| Personal folder | Its owner only |

Folder rights, item restrictions and the recycle bin apply to passkeys as they do to passwords. A read-only user of the folder can sign in with a passkey, but cannot save or delete one.

---

### Audit

Every use of a passkey is attributable, because it is signed by the server on behalf of a named user.

- **Item history**: *Passkey added*, *Passkey deleted* and *Passkey used*, each with the site concerned (for example `github.com`).
- **Monitoring → Logs**: the item logs can be filtered on the *Passkey used* action.
- **Notification email**: when the corresponding setting is on, the user who saved a passkey receives an email naming the site, the account and the item. A passkey saved without their knowledge — with a stolen session, for example — is noticed this way. The text can be customized in [Email templates](../manage/email-templates.md) (*Passkey saved*).

---

### Managing the passkeys of an item

The item card shows a **Passkeys** section listing, for each passkey, the site, the account, the creation date and the last use. The fingerprint icon next to an item title in the list shows that the item holds a passkey.

A user allowed to edit the item can delete a passkey from there. TeamPass then no longer signs in with it, but **the site keeps it registered**: remove it from the account settings on the site as well, after making sure the account has another way to sign in.

An item created by the extension to hold a passkey may have no password: its card then shows *Passkey — see below* instead of an empty password line.

---

### Lifecycle

| Operation on the item | Effect on its passkeys |
|---|---|
| Move | The passkeys follow. Moving from a personal folder to a shared one gives them to the members of the target folder; the reverse keeps them for the owner only |
| Copy | **Not copied.** A passkey belongs to one account on one site, and must not be bound to two items |
| Delete (recycle bin) | Unusable while the item is in the recycle bin, usable again once it is restored |
| Purge from the recycle bin | Deleted for good |
| Export (CSV, PDF, offline HTML) | Never exported |

New users receive the keys of the passkeys they can reach while their encryption keys are generated, like for any other item. If a user cannot decrypt a passkey, **Monitoring → Tools → Restore missing sharekeys** covers passkeys too.

---

### Security model

- The key pair is **ECDSA P-256** (`ES256`), generated on the server. Its private key is encrypted with its own object key, and that object key is encrypted for each user with their public key — the same scheme as item passwords, see [Encryption](../install/encryption.md).
- **No endpoint returns the private key.** The API signs on the server and sends back the signature only.
- The credential identifier is random and generated by TeamPass, never supplied by the client.
- A passkey signs only for the site it was created for.
- A signature counter grows at each use, which lets a site detect a cloned passkey. It follows the clock, so it keeps growing after TeamPass is restored from a backup.
- Registrations use the `none` attestation format. TeamPass identifies itself to sites with the AAGUID `7c30bcef-f035-4e21-9175-5c985b4b239c`.
- When a site requires user verification, the extension asks for its vault lock (PIN or biometrics) before relaying the request.

---

### Limitations

- **No TeamPass, no passkey.** The private key never leaves the server: a passkey kept in TeamPass cannot be used while the server is down or unreachable, and it is not part of the offline HTML export. Keep another way to sign in — a password, recovery codes — on every account it protects.
- **Backups.** Restoring a backup loses the passkeys saved since that backup: the site still expects them, TeamPass no longer holds them. Back up after saving passkeys, and keep the fallback above.
- **TeamPass does not keep the passkeys of its own sign-in page.** The extension leaves the pages of its TeamPass server to the browser, and the server refuses to save or to use such a passkey: an item holding it would let everyone who can open it sign in to TeamPass as its owner. Register the passkeys that sign in to TeamPass on another authenticator — Windows Hello, a phone, a security key.
- **API credentials give access to passkeys.** TeamPass signs for any API client authenticated as the user, not only for the extension. Protect API keys and extension tokens like the passwords they unlock. The *user verified* flag a site receives is stated by the extension, after its vault lock.
- **Sites that require user verification** need the vault lock of the extension (PIN or biometrics): without it, the extension cannot use TeamPass passkeys on them.
- **Sites that require attestation or device-bound passkeys** (some enterprise identity providers) do not accept TeamPass passkeys: choose *Use another device* in the extension window.
- **A passkey that stays *Never used*** right after it was saved was probably not accepted by the site: delete it from the item.
- When empty passwords are not allowed, an item created to hold a passkey receives a random password, which is not the password of the site.
- When the server takes more than 5 seconds to answer, or cannot be reached, the browser's own passkey window opens instead, and TeamPass is skipped for a few minutes.
- Passkeys already stored elsewhere (browser, phone, another password manager) cannot be imported, and the passkeys kept in TeamPass cannot be exported.
- Only `ES256` passkeys are created. A site that does not accept `ES256` is left to the browser.
- The extension does not offer passkeys in the browser's autofill suggestions, nor inside frames: those requests go to the browser as usual.

---

### Troubleshooting

| Symptom | Cause and solution |
|---|---|
| The browser's own passkey window opens instead of TeamPass | Passkeys are disabled on the server, the extension setting is off, or — when signing in — TeamPass holds no passkey for this site. The extension silently hands the request to the browser in all these cases |
| *The passkey cannot be decrypted with your keys yet* | The keys of the passkey have not reached the user yet — typically just after the item was created or moved by someone else, while the background task distributes the keys. Retry after a moment; if it persists, run **Monitoring → Tools → Restore missing sharekeys** |
| A user cannot save a passkey | They need the **Update** API right and the right to edit the item in its folder |

---

## Signing in to TeamPass with a passkey

A passkey — a fingerprint, a face, a PIN or a security key — confirms a TeamPass sign-in instead of a code, and can replace the password entirely. It is registered by the user, on the device they sign in from, and it never leaves that device: TeamPass only stores its public key.

> ⚠️ **HTTPS is required.** Browsers only offer passkeys in a secure context, which means HTTPS or `localhost`. On plain HTTP, TeamPass hides the *Sign in with a passkey* and *Add a passkey* buttons, and **Settings → MFA → Passkeys** shows a warning.

---

### Enabling sign-in passkeys

In **Settings → MFA → Passkeys**:

| Setting | Description |
|---|---|
| **Sign in with a passkey** (`webauthn_login_mode`) | *Disabled* · *As a second factor* · *Passwordless and as a second factor*. Default: disabled |
| **Require PRF for passwordless sign-in** (`webauthn_login_require_prf`) | Refuses the server-held copy of the encryption key described in [What a passwordless passkey holds](#what-a-passwordless-passkey-holds). Turning it on also deletes the copies already registered, after a confirmation that gives their number: those passkeys become second factors, and turning the setting off again does not bring the copies back — their owners enable passwordless sign-in again from their profile. Default: off |
| **Passwordless sign-in counts as MFA** (`webauthn_passwordless_satisfies_mfa`) | Default: on. When off, an account on which Google or Duo is imposed must sign in with its password |
| **Notify users when a passkey is saved** (`webauthn_email_on_add`) | The same setting as on the Browser Extension tab: it covers both kinds of passkey |
| **Relying party ID** (`webauthn_rp_id`) | Domain the passkeys are bound to. Empty means the host of the TeamPass URL; a parent domain is also accepted, any other value is refused |
| **Name shown when creating a passkey** (`webauthn_rp_name`) | Shown by the browser and the authenticator. Default: `TeamPass` |

> ⚠️ **Changing the relying party ID makes every registered passkey unusable.** Authenticators bind a passkey to that domain. Users would have to register theirs again. When sign-in passkeys exist, TeamPass asks for a confirmation before saving the new value.

---

### Nothing is imposed

Enabling another MFA method makes MFA mandatory for every account it applies to. **Passkeys do not work that way**: they are offered, never imposed.

| The account | At the next sign-in |
|---|---|
| Has no passkey | Signs in exactly as before |
| Has a passkey | Must present it after the password |
| Has a passkey, but **MFA enabled** is unchecked on its user form | Is not asked for it |

Where Google Authenticator or Duo is already required, a passkey is simply offered next to them, and the user picks one. This is why there is no enrolment during sign-in: a user registers a passkey from their profile, whenever they choose to.

> 💡 **A lost authenticator never locks anyone out for good.** An administrator revokes the passkey from the Users page (see [Revoking a user’s passkeys](#revoking-a-users-passkeys)) and the account goes back to its password, plus any other method it had.

---

### Registering a passkey

A user registers their own passkeys in **Profile → Information → Sign-in passkeys**:

1. Give the device a name (optional) and click **Add a passkey**.
2. TeamPass asks for the password of the account (see [Confirming the password](#confirming-the-password)).
3. The browser asks for the fingerprint, the face, the PIN or the security key.
4. The passkey appears in the list, with its creation date and its last use.

From the same list a passkey can be **renamed**, **deleted**, and — in passwordless mode — turned on or off for signing in without a password. An account may hold up to **20** passkeys.

#### Confirming the password

Adding a passkey, or letting one sign in without a password, asks for the password of the account first. An open session is not enough: whoever finds it unattended, or steals it, could otherwise add a passkey of their own and keep signing in after the owner changed their password.

| Account | What is asked |
|---|---|
| Local or directory (LDAP) | The password used to sign in to TeamPass |
| OAuth2 | Nothing if the user signed in less than 10 minutes ago; otherwise to sign in again |

A confirmed password stays valid for 5 minutes, so a registration followed by the passwordless step asks for it once. A wrong password counts towards the account lockout, like a failed sign-in.

> 💡 Whether a passkey follows the user from one device to another depends on where it lives. iCloud Keychain, Google Password Manager and most password managers **sync** it — the list marks those *Synced*. A security key or a Windows Hello passkey stays on its device, so the user registers one per device.

---

### Signing in

**As a second factor.** After the login and the password, TeamPass shows **Use my passkey** (or the passkey button among the other methods). The browser asks for the passkey, and the sign-in completes.

**Without a password.** In passwordless mode, the login page also shows **Sign in with a passkey**. The browser offers the passkeys registered for this TeamPass, and the account is the one the chosen passkey belongs to: nothing has to be typed.

> 💡 Some browsers only run a passkey request started by a click. When that happens the page says so, and a second click on the same button completes the sign-in.

---

### What a passwordless passkey holds

Every item, field and file is encrypted with the user's key, and that key is itself protected by their password. A sign-in that never sees the password would therefore open a session that can decrypt nothing. So a passkey used for passwordless sign-in carries **a second, encrypted copy of the user's encryption key**, opened in one of two ways:

| Copy | Opened by | What a stolen database gives |
|---|---|---|
| **Authenticator (PRF)** | A secret only that authenticator can produce, and only after verifying its user | Nothing: the copy cannot be opened without the authenticator |
| **Server** | A key derived from the instance secret file, outside the database | Nothing on its own — the file is needed too — but the server can open it by itself |

The second copy exists because several authenticators (some Windows Hello configurations) do not support PRF. Turn **Require PRF for passwordless sign-in** on to refuse it: those passkeys then stay second factors, which the user is told when registering. The copies registered before the setting was turned on are deleted at that moment, and are not restored when it is turned off.

As a **second factor**, a passkey holds no copy at all: the password still unlocks the key.

Whoever holds both the database and the instance secret file can open the server copies — the same exposure as the transparent recovery backup of the encryption keys, which relies on that file too. And like any server, a compromised TeamPass sees what reaches it at sign-in: the PRF output, as it sees passwords.

---

### Restrictions

| Case | Passwordless sign-in |
|---|---|
| Local account | Allowed |
| Directory (LDAP) or OAuth2 account | **Refused** — signing in without the directory password would bypass a directory that may have disabled the account. These accounts use their passkey as a second factor |
| Account waiting for its keys, for a re-encryption or for a one-time code | **Refused** until that step is done with the password |
| Another second factor imposed, with *Passwordless sign-in counts as MFA* off | **Refused**: the password path goes through that factor |

**After the encryption keys of a user are regenerated** — a new encryption code, a password initialized by an administrator, *Generate new keys* — the copies held by their passkeys are obsolete and are deleted. The passkeys still work as second factors, and the user turns passwordless back on from their profile: **Use for passwordless sign-in**, then their password and one gesture on the authenticator. While the keys are being regenerated, the account cannot sign in at all, with or without a passkey. A copy that escaped that cleanup is refused and deleted at the next sign-in.

---

### Audit of sign-ins

| Where | What is recorded |
|---|---|
| **Monitoring → Logs**, administration entries | *Sign-in passkey added*, *Sign-in passkey deleted*, *Passwordless sign-in enabled on a passkey* and *Passwordless sign-in disabled on a passkey*, each naming the account |
| **Monitoring → Logs**, failed authentications | *Passkey sign-in refused* — a wrong or unknown passkey; *Password not confirmed for a passkey* — a wrong password when adding a passkey. Both count towards the account lockout like a wrong password |
| **Email** | The owner is told when a passkey is added to their account, unless the notification setting is off. The text is customizable in [Email templates](../manage/email-templates.md) (*Sign-in passkey added*) |
| **Profile** | Each passkey shows its creation date and its last use |

---

### Revoking a user’s passkeys

From **Users → action menu → Sign-in passkeys**, an administrator (or a manager of that account) lists the passkeys of a user and revokes any of them. A revocation takes effect at once, even though the passkey remains on the user's device.

---

### Limitations of sign-in passkeys

- **One address.** Passkeys work only through the exact address of the TeamPass URL setting — scheme, host name and port. Another host name, an IP address or another port is refused by the browser.
- **Moving TeamPass to another domain** makes the sign-in passkeys unusable, unless the **Relying party ID** is a parent domain kept across the move: set it before users register. Passkeys registered for a previous ID are no longer asked for at sign-in, and their owners see them marked *no longer usable* in their profile.
- **Changing your password does not remove your passkeys**, nor the copies they hold. After a suspected compromise, review **Profile → Sign-in passkeys**; an administrator can revoke them from the Users page.
- **A password to change is still changed with the current password.** Signing in with a passkey does not skip the form shown when the password has expired or was generated by an administrator, and that form asks for the current password. A user who no longer knows it asks an administrator to reset it; the reset regenerates the encryption keys, so passwordless sign-in has to be enabled again afterwards.
- **Device-bound passkeys** (Windows Hello, security keys) do not follow the user to another device: register one per device, or a second passkey on a phone or a key.
- **Security keys** hold a limited number of passkeys that sign in without a username, and passwordless sign-in needs one of those slots.
- **PRF support varies** with the browser, the system and the authenticator. A passkey without PRF falls back to the server copy, or stays a second factor when PRF is required.
- **Instances whose accounts are all LDAP or OAuth2** gain nothing from the passwordless mode: choose *As a second factor*.
- By default, a passkey **can replace an imposed Google Authenticator or Duo factor** (*Passwordless sign-in counts as MFA*). Turn that setting off to keep them mandatory.
- Switching from passwordless back to second factor keeps the copies held by the passkeys: they work again when passwordless is turned back on.
- Keep at least one administrator able to sign in with a password and another factor.

---

### Troubleshooting a sign-in

| Symptom | Cause and solution |
|---|---|
| No passkey button on the login page or in the profile | The feature is off, or the page is served over plain HTTP. Passkeys need HTTPS (or `localhost`) |
| *This browser cannot use passkeys here* | Same causes, or a browser too old for WebAuthn |
| The browser asks, then nothing happens | Some browsers refuse a request that no click started: click the button again |
| *This passkey is not set up to sign in without a password* | It was registered as a second factor, or the encryption keys of the account were regenerated since — typically after an administrator reset the password. Sign in with the password, then use **Use for passwordless sign-in** in the profile |
| *Passwordless sign-in is not available for your account* | The account is a directory (LDAP) or OAuth2 account, whose passkeys are second factors only, or it is in a state that needs the password first: private key to re-encrypt, one-time code to enter. Sign in with the password |
| *Your account is currently being created* | The encryption keys of the account are being regenerated, typically after an administrator reset the password. No sign-in works until the operation ends, with or without a passkey: try again a little later |
| *This passkey can no longer sign you in without a password* | The account's encryption keys were regenerated. Sign in with the password and enable passwordless again |
| *This browser cannot unlock this passkey* | The passkey uses a PRF copy and this browser cannot evaluate it. Use the browser it was registered from, or sign in with the password |
| *… your administrator now requires PRF …* | **Require PRF for passwordless sign-in** was turned on, and this passkey held a server copy. It still confirms a password sign-in |
| *For your security, sign in again before adding a passkey …* | OAuth2 account whose sign-in is more than 10 minutes old: sign out, sign in, and add the passkey right away |
| *Your account requires another second factor* | *Passwordless sign-in counts as MFA* is off and Google or Duo is imposed on this account: sign in with the password |
| A user lost their authenticator | Revoke the passkey from the Users page; the account keeps its password and its other methods |
| Every passkey stopped working at once | The **Relying party ID** — or, when it is empty, the host of the TeamPass URL — changed. **Settings → MFA → Passkeys** counts the passkeys registered for another ID; they are no longer asked for at sign-in, and their owners see them marked in their profile. Restore the previous value, or have the users register new passkeys |
