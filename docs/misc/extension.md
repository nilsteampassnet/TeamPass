# TeamPass Browser Extension

The official TeamPass extension for **Google Chrome**, **Microsoft Edge** and **Mozilla Firefox**. It finds the credentials for the site you are on, fills login forms, saves new logins to TeamPass and can keep your passkeys, without leaving the browser.

The extension is published on the official store of each browser and is updated automatically through them.

> 🎫 **A commercial licence is required.** An administrator can request a free 30-day trial directly from TeamPass: see [Requesting a free trial](#requesting-a-free-trial). For a subscription, write to `contact@teampass.net`.

---

## Features

**Setup**
- One-click configuration from TeamPass, or from a configuration file, with no data entry. Your password is never sent to the extension; a revocable access token is used instead
- A three-step setup wizard for manual configuration
- Licence details fetched from TeamPass automatically

**Finding and using credentials**
- Credentials matching the current site shown as soon as the popup opens, with a counter on the toolbar icon
- Search by label across every folder you can access
- One-click copy of the login, password, e-mail or TOTP code; the clipboard is cleared 20 seconds after a password or code is copied
- Login form autofill, including TOTP fields
- Item details with the password hidden until you reveal it

**Saving and editing**
- Detects a successful login and offers to save it, or to update the existing item. The folder last used for the site is suggested
- Create, edit and delete items: label, login, password, e-mail, URL, description, tags, icon and TOTP secret
- Create, rename, move and delete folders, within your TeamPass rights
- Password generator in the popup and inside sign-up forms on web pages: passwords or passphrases, with a history of the last generated values

**Security**
- Optional **vault lock**: a PIN, biometrics or a security key, with auto-lock after inactivity
- **Passkeys** kept in TeamPass and used on the sites that support them (TeamPass 3.2.3 or later)
- Secrets are never filled into, or captured from, `http://` pages unless you allow the site
- Saved page addresses are stripped of their query string and embedded credentials, so one-time sign-in codes never end up in an item
- Clear error messages that tell a certificate problem, a CORS problem and an unreachable server apart

**Interface**
- Dark mode
- English, French, German and Spanish
- A maintenance screen, retried automatically, while TeamPass is in maintenance mode

### Current limitations

- Custom fields are not displayed or edited by the extension.
- Login forms inside frames (`iframe`) are not filled.
- Passkeys are not offered in the browser's autofill suggestions, nor inside frames.

---

## Requirements

| | |
|---|---|
| **TeamPass server** | 3.2.1.0 or later. Passkeys require 3.2.3 or later |
| **API** | Enabled on the server, and API access enabled for each user of the extension |
| **HTTPS** | Required, with a certificate the browser trusts and that matches the server name (see [Troubleshooting](#cannot-reach-the-teampass-server)) |
| **Browser** | A current version of Chrome or Edge; Firefox 140 or later |
| **Licence** | A subscription or a trial. Browsers must be able to reach `https://licence.teampass.net` |

---

## Installation

Install the extension from the store of your browser:

| Browser | Store |
|---|---|
| Google Chrome | [Chrome Web Store](https://chromewebstore.google.com/detail/cnlomomlocpdfojipnpkhhndpdbcolfn) |
| Microsoft Edge | [Edge Add-ons](https://microsoftedge.microsoft.com/addons/detail/teampass-password-manager/adgkighfbpgjgoldhcdjjjhceicdemem) |
| Mozilla Firefox | [Firefox Add-ons](https://addons.mozilla.org/firefox/addon/teampass-password-manager/) |

Click **Add to Chrome** (or **Get**, **Add to Firefox**) and confirm. Updates are installed automatically by the browser.

Then pin the TeamPass icon to the toolbar: the extension is used from there.

### Deploying to a fleet

Managed browsers can install the extension through their usual policies, using the store identifiers:

| Browser | Identifier | Policy |
|---|---|---|
| Chrome | `cnlomomlocpdfojipnpkhhndpdbcolfn` | `ExtensionInstallForcelist`: `cnlomomlocpdfojipnpkhhndpdbcolfn;https://clients2.google.com/service/update2/crx` |
| Edge | `adgkighfbpgjgoldhcdjjjhceicdemem` | `ExtensionInstallForcelist`: `adgkighfbpgjgoldhcdjjjhceicdemem;https://edge.microsoft.com/extensionwebstorebase/v1/crx` |
| Firefox | `contact@teampass.net` | `ExtensionSettings` in `policies.json`, with `installation_mode` set to `force_installed` and `install_url` set to `https://addons.mozilla.org/firefox/downloads/latest/teampass-password-manager/latest.xpi` |

Each user then configures their own extension, ideally with the one-click setup described in [Setting up the extension](#setting-up-the-extension).

### Moving from a ZIP installation

Earlier versions were distributed as ZIP files loaded in developer mode. These packages are no longer provided. Remove the manually loaded copy from the browser's extensions page, install the extension from the store, and configure it again: the store version is a separate extension and does not see the settings of the old copy.

---

## Server configuration (administrators)

Everything happens in **Settings → API**.

### 1. Enable the API

On the main part of the page:

| Setting | Recommendation |
|---|---|
| **API access enabled** | On |
| **JWT token expiration delay (in minutes)** | Default `60`. The extension renews its session in the background, so there is no need to raise it |
| **Allowed CORS origins** | Leave empty (see [CORS](#cors-origins)) |
| **Require HTTPS for API requests** | On |
| **API rate limit (requests per minute)** | Default `120` |

### 2. Give users access to the API

Open the **Users** tab. For each user of the extension, turn API access on and choose the operations the extension may perform on their behalf: create, read, update, delete. These rights apply on top of the user's TeamPass rights, never beyond them.

> ⚠️ New accounts, including those created from LDAP or OAuth2, have **no API access by default**. It is the most common reason for a refused sign-in.

**Build missing API keys for users** creates an API key for every account that has none. Each user finds their key in **Profile → Information → API token**.

### 3. Identify the instance for the licence

Open the **Browser Extension** tab.

- **FQDN** — the domain name of your TeamPass server, for example `teampass.example.com`. It identifies the owner of the licence.
- **Browser Extension Key** — click the generate button (🔄) to create it, and the copy button to copy it. This key is the secret of your licence.

> ⚠️ **Never regenerate the key once a licence is registered.** The licence server cannot update it, and a new key would leave every extension unable to validate the licence. For this reason the generate button is only shown while no key exists. If the key has leaked, write to `contact@teampass.net`. Never share it publicly.

Users rarely need to type these two values: the extension fetches them from TeamPass once signed in.

### 4. Allow the one-click setup

The one-click setup uses a **Personal Access Token** instead of the user's password. Who can use it depends on two switches:

| Switch | Effect |
|---|---|
| **Settings → API → Browser Extension → Allow extension auto-configuration for all users** | Every user (local, LDAP or SSO) can configure the extension in one click |
| **Settings → OAuth2 → Allow OAuth2 users to access the API** | OAuth2/SSO users can generate tokens, even when the switch above is off |

Both are off by default. OAuth2/SSO users have no password the API can check, so for them one of the two is **required** to use the extension at all. Local and LDAP users can always configure it manually with their password and API key.

### 5. Licence

The **Licence** tab shows the state of your licence and lets you request a free trial. See [Licence](#licence).

### CORS origins

The extension calls the TeamPass API from the browser, so the API — and any reverse proxy in front of it — must return CORS headers that allow it.

| Field value | Behaviour |
|---|---|
| **Empty** (recommended) | Every origin is accepted. The JWT token remains the security boundary of the API: CORS only stops other websites from calling it through a user's browser |
| **Comma-separated list** | Only the listed origins are accepted |

If you restrict the list, add the origin of each browser, without a trailing slash:

- Chrome: `chrome-extension://cnlomomlocpdfojipnpkhhndpdbcolfn`
- Edge: `chrome-extension://adgkighfbpgjgoldhcdjjjhceicdemem`
- Firefox: `moz-extension://<Internal UUID>`, shown in `about:debugging#/runtime/this-firefox`

> ⚠️ Firefox gives every installation a **different** internal UUID, which changes again when the extension is reinstalled or the profile reset. A restricted list therefore does not suit Firefox users: leave the field empty if you have any.

---

## Setting up the extension

There are three ways to set up the extension. The first one is the fastest.

### One click from TeamPass (recommended)

Available when your administrator has allowed it (see [Allow the one-click setup](#_4-allow-the-one-click-setup)).

1. Sign in to TeamPass in the browser where the extension is installed.
2. When the extension is not configured yet, TeamPass offers to configure it. Otherwise open **Profile → Information** and click **Configure my extension**.
3. A window of the extension opens and shows the server and the account about to be configured. Click **Confirm**.

The server, your account and the licence are set up. Your password is not transmitted: TeamPass creates a Personal Access Token, listed in **Profile → Browser extension tokens**, where you can revoke it at any time.

### Configuration file

When TeamPass does not detect the extension, **Configure my extension** says so and shows a **Download configuration file** button.

1. Download the file.
2. Open the extension settings (right-click the TeamPass icon → **Options**). On first use, the setup wizard asks **Did you receive a configuration file?** — choose **Yes, I have a file** and drop the file. Once configured, use **Connection → Import configuration file** instead.
3. Confirm in the window that opens.

> ⚠️ The file contains an access token that can unlock your account in the extension. **Delete it** once imported. It can be imported within 24 hours of its creation; after that, download a new one.

### Setup wizard

Open the extension settings (right-click the TeamPass icon → **Options**) and choose **No, I'll enter the details**.

1. **Your server** — the address you open TeamPass with, for example `https://teampass.example.com`.
2. **Your account** — how you sign in to TeamPass:
   - **Username and password** (TeamPass or LDAP account): your username, your password, and the API key shown in **Profile → Information → API token**.
   - **Company account** (SSO): your username and an extension token, generated in **Profile → Browser extension tokens**. The token is shown only once: copy it straight away.
3. **Your licence** — usually automatic. If the extension could not fetch it from TeamPass, enter the licence e-mail, the server domain name and the licence key given by your administrator, then click **Verify my licence**.

> 💡 LDAP users: after a password change in the directory, sign in once to the TeamPass web interface before using the extension. Until then, the extension is refused with the new password.

The wizard then suggests protecting the vault with a PIN — recommended when other people use the computer.

---

## The settings page

Right-click the TeamPass icon → **Options**. Once the extension is configured, the page is organised in sections:

| Section | Contents |
|---|---|
| **Overview** | Server, account and licence at a glance, with what needs attention and the common settings |
| **Connection** | Server address and sign-in details, **Keep me signed in**, **Test Connection**, **Sign in again**, import of a configuration file |
| **Licence** | Licence details and status, **Save and verify**, **Get it from TeamPass** |
| **Autofill and sites** | The `http://` sites where autofill is allowed |
| **Passkeys** | **Use TeamPass for passkeys** |
| **Vault lock** | PIN, quick unlock, auto-lock delay |
| **Help and troubleshooting** | Diagnostic mode, technical information for support, reset of the extension |

The page uses the language chosen in the popup. **Keep the session open** (in the overview) lets the extension renew its connection in the background; leave it on.

---

## Using the extension

### Finding a credential

Click the TeamPass icon. The popup lists the items whose URL matches the current site, by domain name. Type at least three characters in the search field to search by label in every folder you can access.

From an item you can copy the login, password, e-mail or TOTP code, fill the form of the page, open the details or edit the item.

### Saving a new login

After a successful sign-in on a site, the extension offers to save the credentials:

- **Quick save**: a name and a folder, directly in the page. The folder last used for the site is suggested.
- **More options**: the full form, with tags and the other fields.
- When the site already has an item for this login, the extension offers to **update** it instead, and shows what changed.

The account screen of the popup also lets you define a **favourite folder**, offered first when creating items.

### Generating a password

Use the generator button in the popup, or the generator shown next to the password fields of sign-up forms. It produces passwords (length, character types, ambiguous characters excluded on demand) or passphrases (number of words, separator). The last generated values are kept in a history, encrypted when a valid licence is present.

### Sites served over `http://`

On an `http://` page the password travels in clear text, so the extension neither fills it nor offers to save it. For an internal application you trust, open it, click the TeamPass icon, then **Allow on this site**. The exceptions are listed, and can be removed, in **Settings → Autofill and sites**. Local addresses (`localhost`) are always allowed.

A switch in the same section allows every `http://` site. It is not recommended: it also covers public sites.

---

## Vault lock

The vault lock stops someone else from opening your credentials on this computer. It works with every sign-in method, SSO included. Configure it in **Settings → Vault lock**; the popup only unlocks and locks.

| | |
|---|---|
| **PIN** | At least 6 digits. Asked for to open the vault once it has locked. After **5 wrong attempts**, the credentials stored by the extension are erased and you sign in again |
| **Quick unlock** | Windows Hello, Touch ID or a FIDO2 security key. The PIN always remains available as a fallback. Unlock the vault before turning it on |
| **Auto-lock** | After 1, 5, 15 (default), 30 or 60 minutes of inactivity, or only when the browser closes |

Quick unlock requires an authenticator supporting the WebAuthn PRF extension. Some Windows Hello configurations do not: the extension says so, and the PIN or a compatible security key can be used instead.

> A PIN is an unlock convenience, not strong protection against someone with full control of the computer. Prefer biometrics where available.

The vault lock is also what confirms your identity for [passkeys](#passkeys).

---

## Passkeys

> Requires **TeamPass 3.2.3 or later**, with passkeys enabled by the administrator.

A passkey replaces the password on the sites that support it. The extension can keep yours in TeamPass, so they follow you on every computer where the extension is installed.

- **Creating a passkey**: when a site offers to create one, a TeamPass window opens. Choose the item to store it in, or create a new item in a folder of your choice, then click **Save in TeamPass**.
- **Signing in**: when a site asks for a passkey that TeamPass holds, choose the account and click **Sign in**.
- **Identity verification**: some sites require you to confirm your identity. The extension does it with the vault lock (PIN or biometrics). Without a vault lock, use another device for those sites.
- **Everything else is left to the browser**: sites for which TeamPass holds no passkey, and **Use another device**, go to the browser or your security key as usual.

The feature can be turned off with **Use TeamPass for passkeys** in **Settings → Passkeys**.

---

## Licence

### How the licence is checked

The extension checks the licence with `licence.teampass.net`, **from the browser**, using three values:

- **Licence e-mail** — the e-mail address of your TeamPass account. The licence counts its users by this address.
- **Server domain name** — the FQDN defined in **Settings → API → Browser Extension**.
- **Licence key** — the Browser Extension Key.

They are filled in automatically when you sign in, and can be fetched again with **Get it from TeamPass** in **Settings → Licence**. Only these three values are sent; no usage data is collected. The answers of the licence server are signed and verified by the extension.

The check runs in the background, at most once every ten minutes. **Save and verify** forces one.

| Status | Meaning | What happens |
|---|---|---|
| **Valid** / **Trial** | The licence is active | The extension works |
| **Expired** | The subscription has ended | A subscription keeps working for up to 15 days after its end date, to cover a renewal. A trial stops on its end date |
| **Invalid** | The licence is not recognised | Check the three values in **Settings → Licence** |
| **User limit exceeded** | More users than the licence allows | Contact the person who manages your subscription |
| **Not verified yet** | No answer from the licence server so far | See below |
| **Too many verifications** | The licence server limits the number of checks per hour | The extension waits, then checks again |

If the licence server cannot be reached, the extension keeps working for **5 days** after the last successful check, then stops until the server answers again. Only a definite answer (expired, invalid, limit exceeded) blocks it straight away.

### Requesting a free trial

An administrator can request an evaluation licence directly from TeamPass, without contacting anyone.

Go to **Settings → API → Licence**. The tab shows the identity this instance would be licensed under — its FQDN and its extension key — followed by the state of the licence.

#### Before you request

Two values are sent as the identity of your instance and cannot be changed afterwards:

- **The FQDN** must be a public host name such as `teampass.example.com`. Values like `localhost`, an IP address, or a subfolder name are refused by TeamPass before anything is sent. A trial is granted **once per FQDN and per product, forever**, so an incorrect FQDN would spend the only trial this instance will ever get.
- **The extension key** becomes the secret of the licence. Once a licence is registered it must never be regenerated: the licence server has no update route, and a new key would leave your instance unable to prove it owns its own licence.

The contact e-mail must belong to the domain of the instance. If your mailboxes are hosted elsewhere, ask for the trial by writing to `contact@teampass.net`.

#### The confirmation e-mail

Requesting the trial sends a confirmation message to the address you provided. **Nothing is activated until you open the link it contains** — as long as you have not confirmed, your instance remains eligible for a trial.

Two points cause most support requests:

- **The link works only once**, and it expires after 48 hours. Past that, ask for a new message.
- **Asking for a new message invalidates the previous link.** If you request a resend, open the most recent e-mail; clicking the link in the older one will report an invalid link.

Once you have opened the link, click **"I have confirmed — check"** in the Licence tab. TeamPass then asks the licence server whether a licence now exists.

#### After activation

A trial licence is an ordinary licence: same endpoints, same rules. The one difference matters:

> ⚠️ **A trial has no grace period.** Access stops on the day the trial expires. A subscription keeps 15 days of tolerance to cover a renewal in progress; a trial does not. TeamPass warns you in the Licence tab and on the dashboard from seven days before the deadline.

#### If the licence server cannot be reached

The trial request is made by the TeamPass server, not by your browser. If your network requires a proxy, set the `proxy_ip` and `proxy_port` settings in the `teampass_misc` table: they are honoured for every call to the licence server.

On an instance with **no outbound Internet access at all**, the Licence tab reports that the licence server is unreachable and offers a request link instead. That link opens a page hosted on the licence server which, once you confirm it there, performs the request your server could not make. Open it from any machine that does have Internet access.

Three ways to get the link out of an isolated server, pick whichever fits:

| | |
|---|---|
| **Send me the link** | E-mails it, using this instance's own mail settings — those usually keep working without Internet access. You can change the destination address. |
| **Copy the link** | Puts it in the clipboard. |
| **Show a QR code** | Generated locally, nothing leaves the browser. Scan it with a phone when the console has neither mail nor clipboard. |

> ⚠️ **The link contains the licence key of your instance.** Treat it as a secret and do not forward the e-mail: the page it opens is the only place that key should ever be pasted.

From there the flow is the usual one — the page confirms the request, a confirmation message reaches the address you gave, and opening its link activates the trial.

**Your TeamPass server will keep reporting the licence server as unreachable, and that is expected.** It has no way to observe the activation. It changes nothing: the extension checks the licence directly from the browser, so it picks up the trial on its own.

That last point cuts both ways. Provisioning can happen without the TeamPass server ever reaching `licence.teampass.net` — but a *browser* that cannot reach it will not be able to use the extension at all, whatever your server can reach.

#### Trials not offered

If self-service trials are closed on the licence server, the Licence tab says so and offers no form. This is a server-side switch; write to `contact@teampass.net`.

---

## Troubleshooting

### Cannot reach the TeamPass server

The extension tells the possible causes apart and names the one it found:

- **Certificate** — the most common cause on an `https://` address. The browser refuses a self-signed or expired certificate, or one issued for another name, without showing any warning to the extension, and the request never reaches TeamPass (so nothing appears in its logs). Open the server address in a tab: if the browser warns about the certificate, install one it trusts, issued for the exact server name.
- **CORS** — the server answered, but the API or a proxy in front of it does not send the CORS headers the extension needs. See [CORS origins](#cors-origins).
- **Unreachable** — wrong address, DNS or network problem.

### Sign-in refused

The extension reports that TeamPass refused the credentials. TeamPass deliberately gives the same answer whatever the cause; administrators find the exact cause in **Logs**, filtered on the *Failed logins* type and the *API / Extension* channel. The most frequent are API access not enabled for the user, a wrong API key, and an LDAP password changed since the last web sign-in. The full list is in [Troubleshooting a refused authentication](../api/api-basic.md#authorize-troubleshooting).

After a refusal, the extension stops retrying on its own for 30 minutes, so that repeated attempts do not lock the account. Fix the cause, then click **Sign in again** in **Settings → Connection**.

### Autofill does nothing

- On an `http://` page, autofill is blocked on purpose: use **Allow on this site** in the popup.
- Login forms inside frames are not supported.
- If the popup asks you to reload the page, do so: it happens on tabs opened before the extension was updated.

### Licence problems

- **Not verified yet**: wait a few minutes, then click **Save and verify** in **Settings → Licence**. Check that the browser can reach `https://licence.teampass.net`.
- **Invalid**: check the licence e-mail, the server domain name and the licence key, or click **Get it from TeamPass**.
- **Expired** or **User limit exceeded**: contact the person who manages your subscription.

### Information for support

In **Settings → Help and troubleshooting**:

- **Enable diagnostic mode** only when support asks for it.
- **Copy** the technical information and send it to support; it contains no password.
- **Reset the extension** removes every setting and stored credential from this browser.

---

## Licence terms

This TeamPass Extension, including all proprietary code, images, and documentation, is licensed under a **Commercial, Non-Public License**.

**Ownership and distribution**
- This extension is proprietary software and the property of **LogCarré**.
- It is provided to you solely for use with your licensed TeamPass instance, identified by your registered FQDN.

**Restrictions**
- You are strictly prohibited from copying, redistributing, modifying, or using the code for any purpose outside the scope of your commercial subscription.
- **Reverse engineering and unauthorized access:** decompilation, disassembly, or reverse engineering of the distributed code is strictly forbidden.
- **API restriction:** unauthorized access or abuse of the external licensing API endpoint is prohibited and will result in the immediate termination of your service.

**For full terms and conditions, please refer to the End-User License Agreement (EULA) provided at the time of purchase.**
