<!-- docs/features/profile.md -->

## Overview

The **My Profile** page lets every user consult their account information, manage how they sign in, set up the browser extension and adjust personal preferences. It is accessible from the top-right user menu.

The left column shows your picture, name, email and role, followed by a menu. Each entry opens one section of the page:

| Section | What it holds |
|---------|---------------|
| **Overview** | Your account at a glance |
| **Sign-in and security** | Your password and your sign-in passkeys |
| **Browser extension** | One-click set-up of the extension and the access it was given |
| **External devices** | Experimental QR pairing and individually revocable device access |
| **Preferences** | Name, email, picture, language and display options |
| **Activity** | Your recent actions |
| **Help and advanced** | Repair tool, API key and technical details |

A section with nothing to show for your account is left out of the menu. **Browser extension**, for instance, only appears when your administrator allowed extension tokens for your account.

**External devices** appears when experimental device pairing is enabled or you
already have an associated device. Pairing requires a compatible client, HTTPS
and your existing API/token permissions. Its short-lived QR only starts a request:
compare the code on both screens and approve the displayed device. The profile
confirms completion after the device's first successful authentication.

The device list shows each credential's expiry and remains available for
revocation if pairing or API access is later disabled. New credentials expire
after the administrator's configured duration (90 days by default).
Administrators can also inspect and revoke device credentials in Settings > API >
Mobile application.
Revocation stops the device's API sessions; it does not erase offline data.
See [the pairing protocol and test recipe](../api/device-pairing.md).

> 🔔 Some fields may be locked by the administrator (e.g. name, timezone, language). When an option is grayed out or absent, it means the administrator has disabled self-editing for that field.

> 💡 A section can be opened directly with the `tab` parameter: `index.php?page=profile&tab=security` (`security`, `extension`, `settings` for Preferences, `timeline` for Activity, `advanced`).

---

## Overview section

The default section gives a read-only summary of the account.

### At a glance

One line per topic, with its current state: when your password was last changed, the passkeys you registered, and whether the browser extension is set up in this browser. Click a line to open the matching section.

### My account

- **Groups** — The roles you are currently a member of.
- **Last connection** — Date and time of your last login.

### My activity

| Counter | What it measures |
|---------|-----------------|
| **Created items** | Total number of items you have created |
| **Modifications** | Total number of items you have edited |
| **Items opened** | Total number of times you have opened an item |
| **Passwords seen** | Total number of times you have revealed a password |

---

## Sign-in and security section

### Password

Shown for accounts whose password is managed by TeamPass. It gives the date of your last password change and, if your administrator has set a password expiration policy, how many days remain before it must be changed. **Change password** opens the same panel as the user menu (see [Changing your password](#changing-your-password)).

> 🔔 **LDAP and OAuth2 accounts** do not have this block: their password is changed in the directory or the identity provider.

### Sign-in passkeys

See [Sign-in passkeys](#sign-in-passkeys) below.

---

## Browser extension section

### Set up the extension in this browser

A badge tells what TeamPass found in the browser you are using: **Set up in this browser**, **Not set up in this browser yet**, or **Not detected in this browser**. Click **Configure my extension** to set it up in one click; your password is never sent to the extension. When the extension is not detected, a **Download configuration file** button appears instead. See [Browser extension](../misc/extension.md).

### Access given to the extension

Each line is a Personal Access Token: it lets the extension, or a script, connect to TeamPass without your password. **Revoke** the ones you no longer use. **Generate a new token** creates one by hand; it is shown only once.

---

## Preferences section

### About me

| Field | Description |
|-------|-------------|
| **Name** | Your first name as displayed in the interface |
| **Last name** | Your last name |
| **Email** | Your email address, used for notifications and for receiving MFA setup codes |
| **Picture** | Click **Select profile image file** to replace your profile picture. Supported formats are JPEG and PNG. The image is resized and stored server-side |

### Language and display

| Option | Effect |
|--------|--------|
| **Timezone** | Adjusts how dates and times are displayed throughout the interface |
| **Language** | Changes the interface language. Takes effect on the next page load |
| **Split view mode** | When enabled, the item list and item details are shown side by side instead of a single expanding list |
| **Show sub-folders in main view** | When enabled, items from sub-folders are listed inline below the parent folder's items. See [Show sub-directories](items.md#show-sub-directories-with-items-list) |

Click **Save** to apply every change of the section.

---

## Activity section

A chronological log of your own recent actions (sign-ins, items opened, etc.), limited to the last 40. It is a personal audit trail — only your actions are shown, not other users'.

---

## Help and advanced section

Most users never need this section. Open it when your administrator or the support team asks you to.

### Check my personal items

Shown when personal folders are enabled. **Run the check** makes sure your personal items can still be recovered if you ever lose your own key, and rebuilds what is missing. It is safe to run at any time and never changes what your items contain.

### API key

If the API is enabled, your personal API key is shown here. You can:

- **Copy** the key to the clipboard.
- **Generate a new key** (this invalidates the previous key immediately).

> 🔔 Treat your API key like a password. Anyone who has it can access Teampass on your behalf via the API.

### Open API connections

Each script, browser extension or mobile app that signed in with your account appears here, with the dates it was opened, last used and expires. **Revoke** a line to cut its access immediately.

### Technical details

- **Upload feature** — Indicates whether your browser supports the file upload feature used for item attachments.
- **Tree load strategy** — Shows whether the folder tree is loaded lazily (on demand) or fully at login.

---

## Changing your password

The password change panel is accessible from the **top-right user menu → Change password**, or from **Profile → Sign-in and security → Change password**.

1. Enter your **current password**.
2. Enter the **new password**. The strength meter updates in real time — make sure the complexity meets your administrator's requirements.
3. Confirm the new password.
4. Click **Save**.

> 🔔 Changing your login password also re-encrypts your private key. If Teampass cannot decrypt your key with the new password, item access may be lost. The operation is safe as long as you enter your current password correctly.

> 🔔 **LDAP and OAuth2 accounts** authenticate against an external directory. The password change form is not available for these accounts — password changes must be done in Active Directory or the identity provider.

---

## Two-factor authentication (MFA)

MFA is configured globally by the administrator and may be **required** for your account depending on your role.

### Enrolling with Google Authenticator (TOTP)

When TOTP is enabled by the administrator, you will be prompted to scan a QR code on your next login. To complete enrollment:

1. Open an authenticator app on your phone (Google Authenticator, Aegis, Authy, etc.).
2. Tap **Add account** → **Scan QR code**.
3. Scan the QR code shown on screen.
4. Enter the 6-digit code generated by the app to confirm.

From that point on, you will be asked for a code at every login.

> 💡 If you lose access to your authenticator app, contact your administrator. They can reset your TOTP enrollment from the **Users** page (action menu → **Email Google Auth QR**), which sends a new QR code to your registered email address.

### Sign-in passkeys

When the administrator enabled passkeys, the **Sign-in and security** section holds a **Sign-in passkeys** block. Add one per device: the browser asks for your fingerprint, face, PIN or security key, and from then on TeamPass asks for that passkey after your password.

If passwordless sign-in is enabled and your account is a local one, the same list lets you turn a passkey into a full sign-in — no password at all — and turn it off again. A passkey can also be renamed or deleted at any time.

> 💡 Lost the device? Ask an administrator to revoke the passkey: your password, and any other method, keep working. See [Passkeys](passkeys.md#signing-in-to-teampass-with-a-passkey).

### Duo Security

If your organization uses Duo, you will be redirected to the Duo authentication screen after entering your Teampass credentials. Follow the Duo prompt (push notification, phone call, or passcode) to complete login.

### Resetting MFA

Users cannot reset their own MFA. Contact your administrator to:

- Receive a new TOTP QR code by email.
- Be exempted temporarily from MFA if you are locked out.
