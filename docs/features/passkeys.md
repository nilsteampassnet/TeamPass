<!-- docs/features/passkeys.md -->

## Overview

> Requires **TeamPass 3.2.3 or later** and a version of the [browser extension](../misc/extension.md#passkeys) that supports passkeys.

A **passkey** replaces the password on the sites that support it. With this feature, TeamPass keeps the passkeys of third-party sites in its items, like it keeps their passwords: when a site offers to create a passkey, the browser extension saves it in an item, and when the site asks for it later, the extension signs in with it.

The private key of a passkey **never leaves the TeamPass server**. It is generated there, encrypted like an item password, and used there: the extension only relays the request of the site and receives a signature in return.

---

## Enabling passkeys

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

## Who can use a passkey

**Everyone who can open an item can use its passkeys.** This is the point of keeping them in TeamPass: a passkey saved in a shared folder signs in for every member of that folder, exactly like its password.

| Location of the item | Who can use the passkey |
|---|---|
| Shared folder | Every user with access to the folder, minus those excluded by an item restriction |
| Personal folder | Its owner only |

Folder rights, item restrictions and the recycle bin apply to passkeys as they do to passwords. A read-only user of the folder can sign in with a passkey, but cannot save or delete one.

---

## Audit

Every use of a passkey is attributable, because it is signed by the server on behalf of a named user.

- **Item history**: *Passkey added*, *Passkey deleted* and *Passkey used*, each with the site concerned (for example `github.com`).
- **Monitoring → Logs**: the item logs can be filtered on the *Passkey used* action.
- **Notification email**: when the corresponding setting is on, the user who saved a passkey receives an email naming the site, the account and the item. A passkey saved without their knowledge — with a stolen session, for example — is noticed this way. The text can be customized in [Email templates](../manage/email-templates.md) (*Passkey saved*).

---

## Managing the passkeys of an item

The item card shows a **Passkeys** section listing, for each passkey, the site, the account, the creation date and the last use. The fingerprint icon next to an item title in the list shows that the item holds a passkey.

A user allowed to edit the item can delete a passkey from there. TeamPass then no longer signs in with it, but **the site keeps it registered**: remove it from the account settings on the site as well, after making sure the account has another way to sign in.

An item created by the extension to hold a passkey may have no password: its card then shows *Passkey — see below* instead of an empty password line.

---

## Lifecycle

| Operation on the item | Effect on its passkeys |
|---|---|
| Move | The passkeys follow. Moving from a personal folder to a shared one gives them to the members of the target folder; the reverse keeps them for the owner only |
| Copy | **Not copied.** A passkey belongs to one account on one site, and must not be bound to two items |
| Delete (recycle bin) | Unusable while the item is in the recycle bin, usable again once it is restored |
| Purge from the recycle bin | Deleted for good |
| Export (CSV, PDF, offline HTML) | Never exported |

New users receive the keys of the passkeys they can reach while their encryption keys are generated, like for any other item. If a user cannot decrypt a passkey, **Monitoring → Tools → Restore missing sharekeys** covers passkeys too.

---

## Security model

- The key pair is **ECDSA P-256** (`ES256`), generated on the server. Its private key is encrypted with its own object key, and that object key is encrypted for each user with their public key — the same scheme as item passwords, see [Encryption](../install/encryption.md).
- **No endpoint returns the private key.** The API signs on the server and sends back the signature only.
- The credential identifier is random and generated by TeamPass, never supplied by the client.
- A passkey signs only for the site it was created for.
- A signature counter is incremented on each use, which lets a site detect a cloned passkey.
- Registrations use the `none` attestation format. TeamPass identifies itself to sites with the AAGUID `7c30bcef-f035-4e21-9175-5c985b4b239c`.
- When a site requires user verification, the extension asks for its vault lock (PIN or biometrics) before relaying the request.

---

## Limitations

- Passkeys already stored elsewhere (browser, phone, another password manager) cannot be imported.
- Only `ES256` passkeys are created. A site that does not accept `ES256` is left to the browser.
- The extension does not offer passkeys in the browser's autofill suggestions, nor inside frames: those requests go to the browser as usual.
- Signing in to TeamPass itself with a passkey is a different feature, not covered here.

---

## Troubleshooting

| Symptom | Cause and solution |
|---|---|
| The browser's own passkey window opens instead of TeamPass | Passkeys are disabled on the server, the extension setting is off, or — when signing in — TeamPass holds no passkey for this site. The extension silently hands the request to the browser in all these cases |
| *The passkey cannot be decrypted with your keys yet* | The keys of the passkey have not reached the user yet — typically just after the item was created or moved by someone else, while the background task distributes the keys. Retry after a moment; if it persists, run **Monitoring → Tools → Restore missing sharekeys** |
| A user cannot save a passkey | They need the **Update** API right and the right to edit the item in its folder |
