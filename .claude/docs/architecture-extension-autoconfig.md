# Browser-Extension Auto-Configuration

> Last updated: 2026-06-07 — Branch `improv/extension-auto-config` (TeamPass) +
> `main` commit `8d506a2` (teampass-extension).

Lets a logged-in TeamPass user set up the browser extension in **one click** instead of
filling the extension Options page by hand (server URL, auth mode, credentials, licence
triplet). The password is **never** transmitted — a Personal Access Token (PAT) carries a
token-unlockable copy of the user's private key. See `architecture-encryption.md` →
"Personal Access Tokens" for the PAT crypto.

Two delivery paths share **one canonical bundle**:

1. **Live bridge (primary)** — the extension content script already runs on TeamPass pages
   (`<all_urls>`), so a same-origin `window.postMessage` handshake detects it and pushes the
   bundle. No manifest change, no `externally_connectable`.
2. **Downloadable JSON file (fallback)** — when no extension answers, TeamPass offers the same
   bundle as a file the user imports from the extension Options page.

---

## Components

**TeamPass side**

| File | Role |
|---|---|
| `app/sources/users.queries.php` | `build_extension_autoconfig` handler — mints the PAT + assembles the bundle. Relaxed PAT **issuance** gate. Allow-listed in `$all_users_can_access`. |
| `app/api/Model/AuthModel.php` | `getUserAuthByToken()` — relaxed PAT **consumption** gate (`/api/authorizeToken`). |
| `app/core/extension-autoconfig.js.php` | Page-side JS: ping/pong detection, auto-prompt, `configure()` (live), `download()` (file). Exposes `window.tpExtAutoconfig`. |
| `public/index.php` | Includes the JS for authenticated users when `api == 1`. |
| `app/pages/api.php` | Admin toggle `extension_token_all_auth_types` on the **Browser extension** tab. |
| `app/pages/profile.php` | `#extension-autoconfig-configure` / `#extension-autoconfig-download` buttons. |
| `app/includes/language/{english,french}.php` | `extension_autoconfig_*` + `extension_token_all_auth_types*` strings. |
| `public/install/install-steps/run.step5.php`, `public/install/upgrade_run_3.2.0.php` | Seed `extension_token_all_auth_types = '0'`. |

**Extension side** (`/var/www/html/teampass-extension`)

| File | Role |
|---|---|
| `content/content-script.js` (~line 1402) | postMessage bridge: same-origin filter, `TP_EXT_PING`→`PONG`, relays `TP_EXT_APPLY_CONFIG` to the SW. |
| `background/service-worker.js` (~line 633) | Actions `applyAutoConfig` / `commitAutoConfig` / `getAutoConfigPending` / `cancelAutoConfig`; `validateAutoConfigBundle()`; `AUTOCONFIG_BUNDLE_VERSION = 1`. |
| `confirm/confirm.{html,js}` | Mandatory confirmation window (MV3 SWs cannot call `confirm()`). |
| `options/options.{html,js}` | "Quick setup" file import (`handleImportConfigFile`). |
| `i18n/{en,fr,de,es}.json` | `options.import.*` strings. |
| `build.js` | `confirm` added to the `toCopy` list so the window ships in builds. |

---

## The config bundle

Built server-side, consumed identically by the bridge and the file import.

```json
{
  "teampass_autoconfig": true,
  "version": 1,
  "issued_at": 1733500000,
  "expires_at": 1733586400,
  "server":   { "teampass_url": "https://teampass.example.com",
                "origin": "https://teampass.example.com",
                "fqdn": "teampass.example.com" },
  "account":  { "username": "jdoe", "display_name": "John Doe", "auth_mode": "token" },
  "credential": { "auth_mode": "token", "token": "<64-hex PAT>" },
  "licence":  { "email": "jdoe@example.com", "fqdn": "teampass.example.com", "key": "<browser_extension_key>" },
  "nonce": "<32-hex>"
}
```

- `credential.token` is a **durable** PAT (`teampass_api_tokens`, `expires_at = NULL`), identical
  to a manually generated one — it is the credential the extension reuses for silent re-auth after
  the JWT expires. It is **not** single-use/short-lived: that would break token mode at the first
  JWT expiry (~60 min). Revoke it from the profile if needed.
- `expires_at` is a **24h soft staleness window on the bundle**, not on the token — bounds how long
  a leaked file is "fresh"; the extension rejects a bundle past it.
- `server.origin` is stamped so the extension can verify the bundle came from the delivering page.
- `licence.*` mirrors `browser_extension_fqdn` / `browser_extension_key` / `cpassman_url`; email from session.

---

## postMessage protocol (page ⇄ content script ⇄ service worker)

The content script is the only trusted relay. It accepts a page message **only** when
`event.source === window && event.origin === window.location.origin && data.source === 'teampass-webapp'`,
and posts replies back with the explicit page origin (never `'*'`).

```
1. PING   page → CS    { source:'teampass-webapp',  type:'TP_EXT_PING',  requestId }
2. PONG   CS  → page    { source:'teampass-extension', type:'TP_EXT_PONG', requestId,
                          version, configured: <teampass_configured === true> }
3. APPLY  page → CS    { source:'teampass-webapp',  type:'TP_EXT_APPLY_CONFIG', requestId, bundle }
4.        CS  → SW      chrome.runtime.sendMessage({ action:'applyAutoConfig',
                          data:{ bundle, origin: location.origin } })
5. RESULT CS  → page    { source:'teampass-extension', type:'TP_EXT_APPLY_RESULT', requestId,
                          ok, pending, error }
```

`pending: true` means the SW opened the confirmation window; the final success/failure is shown
**there**, not relayed back to the page.

**Timing:** the content script injects at `document_idle`, so its listener may miss the first ping.
The page pings up to 6× at 400 ms on load; clicking **Configure** also fires a fresh on-demand probe
(`probeExtension`, ~1.2 s) so a one-off miss does not force the file path. **Configure never downloads
a file** — if the extension still does not answer it reveals the download button and tells the user.

---

## Universal PAT and the two gates

The PAT machinery (`teampass_api_tokens`, issuance in `users.queries.php`, consumption in
`AuthModel::getUserAuthByToken`) was originally double-gated to OAuth2. Auto-config for everyone
relaxes **both** gates behind one admin toggle:

| Gate | Location | Original | Relaxed when |
|---|---|---|---|
| Issuance | `users.queries.php` (`build_extension_autoconfig`, `generate_extension_token`, `list`, `revoke`) | `oauth2_api_enabled == 1 && auth_type == 'oauth2'` | `api == 1 && (`OAuth2 path `||` `extension_token_all_auth_types == 1`)` |
| Consumption | `AuthModel::getUserAuthByToken()` (`POST /api/authorizeToken`) | rejects `auth_type != 'oauth2'` | accepts any auth type when `extension_token_all_auth_types == 1` |

- **`extension_token_all_auth_types`** (`teampass_misc`, type `admin`, default `0`) — Settings → API
  → **Browser extension** tab. Off ⇒ current OAuth2-only behaviour preserved (fully opt-in).
- The real security boundary at issuance is unchanged: the **cleartext private key must be present in
  the web session** (`$session->get('user-private_key')`), which is true for all auth types after login.

---

## Security model

1. **A malicious page cannot configure the extension silently** — four independent barriers:
   (a) content script relays only same-window/same-origin messages; (b) the SW requires
   `data.origin === bundle.server.origin`; (c) a mandatory confirmation window names the server +
   account before any write; (d) `commitAutoConfig` verifies the token against the declared URL via
   `handleAuthenticate` and **rolls back** every written key on failure.
2. **Password never transmitted** — token mode only; the PAT only ever wraps the private key.
3. **DB dump alone cannot decrypt** — only `sha256(token)` + the token-wrapped key + salt are stored.
4. **Opt-in** — both gate relaxations require the admin toggle; default off.
5. **CSRF/session** — the bundle handler runs in the authenticated web session behind the existing
   `key` check + CSRF protector, so only the logged-in user's own browser can request their bundle.
6. **Audit** — `at_extension_autoconfig_built` on the server; PAT lifecycle as
   `extension_token_generated` / `extension_token_revoked`; failed token auth as `failed_auth`
   (`tp_src = api`).

---

## Deployment / troubleshooting

- **Reload the unpacked extension after any change** under `content/`, `background/`, or `confirm/`.
  Chrome does **not** hot-reload content scripts/service workers; the running instance keeps the old
  code (and a brand-new top-level directory such as `confirm/` may need a full **remove + Load
  unpacked**, not just the reload button).
- **`ERR_FILE_NOT_FOUND` on `confirm/confirm.html`** when clicking Configure ⇒ the loaded extension
  instance does not have the `confirm/` directory: it was added after load and the reload did not
  register it. Fully reload (remove + re-add) the unpacked extension, or rebuild so `build.js` copies
  `confirm/` into the package.
- **Diagnose detection** in the page console: `window.tpExtAutoconfig.state` — `detected:false` after a
  reload means the content-script bridge is not answering (injection/reload issue, not the web UI).
- The confirmation window is opened with `chrome.runtime.getURL('confirm/confirm.html') + '?id=' + confirmId`;
  the pending bundle lives in `chrome.storage.session` keyed by `autoconfig_pending_<confirmId>`.
