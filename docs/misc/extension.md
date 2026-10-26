# TeamPass Browser Extension

The official TeamPass extension for **Google Chrome**, **Microsoft Edge** and **Mozilla Firefox** finds the credentials of the site you are on, fills login forms and saves new logins to TeamPass, without leaving the browser.

> 🎫 **A commercial licence is required.** An administrator can request a free 30-day trial from TeamPass: see [Free trial](#free-trial). For a subscription, write to `contact@teampass.net`.

This page has two parts: [connecting the extension](#connect-the-extension) for every user, and [enabling it](#enable-the-extension-administrators) for administrators. The [reference](#reference) at the end covers the details.

---

## Connect the extension

### 1. Install it

Install the extension from the store of your browser, then pin the TeamPass icon to the toolbar.

| Browser | Store |
|---|---|
| Google Chrome | [Chrome Web Store](https://chromewebstore.google.com/detail/cnlomomlocpdfojipnpkhhndpdbcolfn) |
| Microsoft Edge | [Edge Add-ons](https://microsoftedge.microsoft.com/addons/detail/teampass-password-manager/adgkighfbpgjgoldhcdjjjhceicdemem) |
| Mozilla Firefox | [Firefox Add-ons](https://addons.mozilla.org/firefox/addon/teampass-password-manager/) |

Updates are installed automatically by the browser.

### 2. Configure it in one click

1. Sign in to TeamPass **in the same browser**.
2. TeamPass detects the extension and asks whether to configure it now. If it does not, open **Profile → Browser extension** and click **Configure my extension**.
3. A window of the extension shows the server and your account. Check that you recognise the server, then click **Confirm & configure**.

That's it: the server, your account and the licence are set up. Click the TeamPass icon to find your passwords.

Your password is never sent to the extension. TeamPass gives it an access token instead, listed in **Profile → Browser extension → Access given to the extension**, where you can revoke it at any time.

> 💡 **Protect the extension with a PIN** if other people use this computer: right-click the TeamPass icon → **Options** → **Vault lock**.

### If the one-click setup is not offered

- **TeamPass does not detect the extension**: **Profile → Browser extension** shows a **Download configuration file** button. Click the TeamPass icon, choose **I have a setup file** and import the file, then confirm. **Delete the file afterwards**: it holds an access token, and can only be imported within 24 hours.
- **Your administrator has not enabled the one-click setup**: click the TeamPass icon, choose **Set up manually**, and follow the three steps:
  1. **Your server** — the address you open TeamPass with, for example `https://teampass.example.com`.
  2. **Your account** — **Username and password** for a TeamPass or LDAP account, with the API key shown in **Profile → Help and advanced → API key**. **Company account** for an SSO account, with a token created in **Profile → Browser extension → Access given to the extension** (it is shown only once).
  3. **Your licence** — usually automatic. Otherwise, enter the details given by your administrator and click **Verify my licence**.

### Everyday use

- **Find a credential**: click the TeamPass icon. The credentials of the current site are listed first; type three characters to search every folder you can access. Each item offers a copy of its login, password and TOTP code, whichever it holds; click its icon to open its website.
- **Fill a form**: **Fill** enters the login, the password and the TOTP code, whichever the item holds and the page asks for. On the page itself, the **Teampass** label on the border of a login field lists the items of the site. Opening an item shows its current TOTP code, renewed when it expires.
- **Browse a folder**: the **Folder** button, right of the search field, lists the items stored in a folder; **This page** brings back the items of the site. In a folder, the search field filters its items.
- **Recent items**: the clock button lists the last 20 items you opened, filled, copied, created or edited, for 30 days. The list stays on this computer, encrypted; turn it off with **Remember recent items** in **Options → Overview**.
- **Use the keyboard**: **Alt+Shift+P** opens the extension from any page; you can change it in the shortcuts page of the browser. The search field has the focus as soon as the extension opens: **↑**/**↓** select an item, **Enter** fills the page with it, **Ctrl+C** (**⌘+C** on macOS) copies its password and **Ctrl+Shift+C** its login, **Alt+↓** opens the folder list, **Esc** clears the search, then leaves the folder, then closes. The menu entry **Keyboard shortcuts** shows the list.
- **Save a login**: after you sign in to a site, the extension offers to save it, or to update the existing item.
- **Generate a password or a passphrase**: from the popup, or from the **Teampass** label of the password field of sign-up forms.
- **Theme**: automatic, light or dark, from **Theme** in the popup menu or **Appearance** in **Options → Overview**.
- **`http://` sites**: secrets are never filled on an unencrypted page. For an internal application you trust, click the TeamPass icon, then **Allow on this site**.

---

## Enable the extension (administrators)

Everything happens in **Settings → API**.

1. **Enable the API** — on the main tab, turn **API access enabled** on. Keep **Require HTTPS for API requests** on, and leave **Allowed CORS origins** empty (see [CORS origins](#cors-origins)).
2. **Give users access to the API** — on the **Users** tab, turn API access on for each user of the extension and choose what it may do on their behalf (create, read, update, delete). These rights never go beyond the user's TeamPass rights. **Build missing API keys for users** creates the keys that are missing.
   > ⚠️ New accounts, LDAP and OAuth2 ones included, have **no API access by default**: it is the most common reason for a refused sign-in.
3. **Identify the instance** — on the **Browser Extension** tab, enter the **FQDN** of the server (for example `teampass.example.com`) and generate the **Browser Extension Key** with the 🔄 button.
   > ⚠️ **Never regenerate the key once a licence exists**: the licence server cannot update it, and every extension would lose its licence. The button disappears once the key exists. If the key has leaked, write to `contact@teampass.net`.
4. **Turn on the one-click setup** — on the same tab, turn **Allow extension auto-configuration for all users** on. Users then configure the extension in one click, whatever their sign-in method, and their password is never sent. When it is off, only SSO users allowed by **Settings → OAuth2 → Allow OAuth2 users to access the API** get the one-click setup; the others use [manual setup](#if-the-one-click-setup-is-not-offered). SSO users need one of the two switches to use the extension at all.
5. **Activate the licence** — the **Licence** tab shows the state of your licence, and requests a [free trial](#free-trial) when you have none.

Optional: **Allow the browser extension to save passkeys** on the **Browser Extension** tab lets users keep the passkeys of other sites in TeamPass. Read [Passkeys](../features/passkeys.md) before turning it on.

**Follow the seats** in **Statistics → Licence**: seats used this month against the quota, users refused because the quota was reached, active users today, licence expiry, and the seats of the last 31 days. The **Seat history** link of the *Browser Extension* tile of the dashboard opens it. A seat is taken by each user who uses the extension during the calendar month (UTC), and the count restarts on the 1st.

**Item history**: the API returns the passwords of the items a user finds by searching or by browsing a folder in the extension, so each of them is recorded as *shown* in its history. The items listed for the current site are not.

### Deploying to a fleet

Managed browsers install the extension through their usual policies; each user then [configures it](#_2-configure-it-in-one-click).

| Browser | Policy |
|---|---|
| Chrome | `ExtensionInstallForcelist`: `cnlomomlocpdfojipnpkhhndpdbcolfn;https://clients2.google.com/service/update2/crx` |
| Edge | `ExtensionInstallForcelist`: `adgkighfbpgjgoldhcdjjjhceicdemem;https://edge.microsoft.com/extensionwebstorebase/v1/crx` |
| Firefox | `ExtensionSettings` in `policies.json` for `contact@teampass.net`: `installation_mode` `force_installed`, `install_url` `https://addons.mozilla.org/firefox/downloads/latest/teampass-password-manager/latest.xpi` |

---

## Reference

### Requirements

| | |
|---|---|
| **TeamPass server** | 3.2.1.0 or later; passkeys require 3.2.3 or later |
| **HTTPS** | A certificate the browser trusts, issued for the server name |
| **Browser** | A current Chrome or Edge; Firefox 140 or later |
| **Licence** | A subscription or a trial; browsers must reach `https://licence.teampass.net` |

Not supported yet: custom fields, login forms inside frames (`iframe`), and passkeys in the browser's autofill suggestions.

### Extension settings

Right-click the TeamPass icon → **Options**.

| Section | Contents |
|---|---|
| **Overview** | Server, account and licence at a glance; **Keep the session open** (leave it on), **Use TeamPass for passkeys**, **Remember recent items**, **Appearance**, auto-lock delay |
| **Connection** | Server and sign-in details, **Test Connection**, **Sign in again**, **Import configuration file** |
| **Licence** | Licence details, **Save and verify**, **Get it from TeamPass** |
| **Autofill and sites** | The access the browser gives the extension to sites, and the `http://` sites where autofill is allowed |
| **Passkeys** | **Use TeamPass for passkeys**, and the **last request from a site**: whether TeamPass handled it, and why not |
| **Vault lock** | PIN, quick unlock, auto-lock delay |
| **Help and troubleshooting** | Diagnostic mode, information for support, reset of the extension |

### Vault lock

Stops someone else from opening your credentials on this computer, whatever your sign-in method.

- **PIN**: a 6-digit code. After **5 wrong attempts**, the credentials stored by the extension are erased and you sign in again.
- **Quick unlock**: Windows Hello, Touch ID or a FIDO2 security key, with the PIN as fallback. It needs an authenticator that supports the WebAuthn PRF extension.
- **Auto-lock**: after 1, 5, 15 (default), 30 or 60 minutes of inactivity, or when the browser closes.

### Passkeys

When your administrator allows it (TeamPass 3.2.3 or later), the extension keeps the passkeys of the sites you visit in TeamPass:

- When a site offers to create a passkey, choose the item to store it in, then click **Save in TeamPass**.
- When a site asks for a passkey TeamPass holds, choose the account and click **Sign in**.
- Sites that require identity verification use the vault lock: set one up to use passkeys there.

Turn it off with **Use TeamPass for passkeys** in **Options → Passkeys**. When the browser or another passkey manager answers a site instead of TeamPass, **Last request from a site** in the same view says why, and what to do. Signing in to TeamPass itself with a passkey is a separate feature: see [Passkeys](../features/passkeys.md#signing-in-to-teampass-with-a-passkey).

### Licence

The extension checks the licence with `licence.teampass.net` **from the browser**, at most every ten minutes, using the licence e-mail (your TeamPass e-mail), the server FQDN and the Browser Extension Key. They are filled in at sign-in; **Get it from TeamPass** fetches them again.

| Status | What happens |
|---|---|
| **Valid** / **Trial** | The extension works |
| **Expired** | A subscription keeps working 15 days after its end date; a trial stops on its end date |
| **Invalid** | Check the three values in **Options → Licence** |
| **User limit exceeded** | Contact the person who manages your subscription |
| **Not verified yet** | Wait a few minutes, then click **Save and verify** |
| **Too many verifications** | The licence server limits checks per hour; the extension waits, then checks again |
| **Licence server unreachable** | The browser got no usable answer from the licence server. **Options → Licence** names the cause: see [Troubleshooting](#troubleshooting). The licence details are not at fault |

If the licence server cannot be reached, the extension keeps working for **5 days** after the last successful check.

### Free trial

An administrator requests it in **Settings → API → Licence**.

- **Check the FQDN first**: it must be a public host name. A trial is granted **once per FQDN, forever**, and the FQDN and the key cannot be changed afterwards.
- **The contact e-mail** must belong to the domain of the instance; otherwise, write to `contact@teampass.net`.
- **Confirm by e-mail**: nothing is activated until you open the link of the confirmation message. The link works once, for 48 hours, and **asking for a new message invalidates the previous link**. Then click **"I have confirmed — check"**.
- **A trial has no grace period**: it stops on its end date. TeamPass warns you from seven days before.
- **No Internet access on the server?** The tab offers a request link to open from another machine, sent by e-mail, copied, or shown as a QR code. The link holds the licence key: do not forward it. The server keeps reporting the licence server as unreachable, which is expected — the extension checks the licence from the browser. A proxy is configured with the `proxy_ip` and `proxy_port` settings of the `teampass_misc` table.

### CORS origins

Leave **Allowed CORS origins** empty: the API token remains the security boundary. If you restrict it, add each browser's origin without a trailing slash — `chrome-extension://cnlomomlocpdfojipnpkhhndpdbcolfn` (Chrome), `chrome-extension://adgkighfbpgjgoldhcdjjjhceicdemem` (Edge). Firefox gives every installation a different origin, so a restricted list does not suit Firefox users. A reverse proxy in front of the API must let the CORS headers through.

The browser applies these rules only to users who restrict the access of the extension to sites (see [Troubleshooting](#troubleshooting)): a restricted list can work for one user and fail for another.

### Troubleshooting

- **Cannot reach the server** — the extension names the cause. **Certificate**: open the server address in a tab; if the browser warns, install a certificate it trusts for that exact name. **CORS**: see [CORS origins](#cors-origins). **Unreachable**: wrong address, DNS or network.
- **Sign-in refused** — the extension shows the reason given by the server. Administrators find the exact cause in **Logs**, type *Failed logins*, channel *API / Extension*: usually API access not enabled for the user, a wrong API key, or an LDAP password changed since the last web sign-in (sign in once to TeamPass). The extension then waits 30 minutes before retrying; fix the cause and click **Sign in again** in **Options → Connection**. Full list: [Troubleshooting a refused authentication](../api/api-basic.md#authorize-troubleshooting).
- **The *Teampass* labels only appear after a click on the TeamPass icon** — the browser restricts the access of the extension to sites. Until you click the icon, nothing runs on the page: no label, no offer to save a login, no passkey. The popup then offers **Allow on all sites** or **This site only**, and so does **Options → Autofill and sites**. In the browser: Firefox, `about:addons` → TeamPass → **Permissions and data** → **Access your data for all websites**; Chrome and Edge, the **Details** of the extension → **Site access** → **On all sites**.
- **Autofill does nothing** — on an `http://` page, use **Allow on this site**; forms inside frames are not supported; reload tabs opened before an update of the extension.
- **Licence server unreachable** — **Options → Licence** names the cause. **Site access restricted**: see above. **No answer**: a firewall, a proxy or TLS inspection stops the request; open `https://licence.teampass.net/` in a tab, it must load without a certificate warning. **Response refused**: a proxy or a security product alters the answer. Then click **Save and verify**. Do not leave it unsolved: the extension locks once the 5-day grace period is over.
- **The browser or another manager answers a passkey request** — see **Last request from a site** in **Options → Passkeys**, and [Passkeys](../features/passkeys.md#troubleshooting).
- **Information for support** — in **Options → Help and troubleshooting**, copy the technical information (it contains no password). **Reset the extension** removes every setting and stored credential from this browser.

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
