<div align="center">

<img src="public/assets/images/teampass-logo2-login.png" width="110" alt="Teampass" />

# Teampass

### Self-hosted password management your whole team can trust

Folder-level access control · authenticated AES-256-GCM encryption · compliance evidence<br />
**Your secrets never leave your infrastructure.**

**🌐 [teampass.net](https://teampass.net)** ·
**📖 [Documentation](https://documentation.teampass.net)** ·
**💬 [Discussions](https://github.com/nilsteampassnet/TeamPass/discussions)** ·
**🐳 [Docker Hub](https://hub.docker.com/r/teampass/teampass)**

<br />

[![Release](https://img.shields.io/github/v/release/nilsteampassnet/TeamPass?style=for-the-badge&color=24c8ff&labelColor=0f2740)](https://github.com/nilsteampassnet/TeamPass/releases/latest)
[![License](https://img.shields.io/github/license/nilsteampassnet/TeamPass?style=for-the-badge&color=24c8ff&labelColor=0f2740)](LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-24c8ff?style=for-the-badge&labelColor=0f2740&logo=php&logoColor=white)](https://www.php.net/)
[![Docker Pulls](https://img.shields.io/docker/pulls/teampass/teampass?style=for-the-badge&color=24c8ff&labelColor=0f2740&logo=docker&logoColor=white)](https://hub.docker.com/r/teampass/teampass)
[![Stars](https://img.shields.io/github/stars/nilsteampassnet/TeamPass?style=for-the-badge&color=24c8ff&labelColor=0f2740)](https://github.com/nilsteampassnet/TeamPass)

[![CodeQL](https://img.shields.io/github/actions/workflow/status/nilsteampassnet/TeamPass/codeql.yml?branch=master&style=for-the-badge&label=CodeQL&labelColor=0f2740&color=3fb950&logo=github&logoColor=white)](https://github.com/nilsteampassnet/TeamPass/actions/workflows/codeql.yml)
[![Docker Build](https://img.shields.io/github/actions/workflow/status/nilsteampassnet/TeamPass/docker-publish.yml?branch=master&style=for-the-badge&label=Docker%20Build&labelColor=0f2740&color=3fb950&logo=docker&logoColor=white)](https://github.com/nilsteampassnet/TeamPass/actions/workflows/docker-publish.yml)
[![Security policy](https://img.shields.io/badge/Security-policy_%26_advisories-3fb950?style=for-the-badge&labelColor=0f2740&logo=shieldsdotio&logoColor=white)](https://github.com/nilsteampassnet/TeamPass/security/advisories)
[![Sponsor](https://img.shields.io/github/sponsors/nilsteampassnet?style=for-the-badge&label=Sponsors&labelColor=0f2740&color=ff4dda&logo=githubsponsors&logoColor=ff4dda)](https://github.com/sponsors/nilsteampassnet)

</div>

---

## Contents

- [About](#about)
- [Who it's for](#who-its-for)
- [Security](#security)
- [Features](#features)
- [Get started](#get-started)
- [Documentation](#documentation)
- [Languages](#languages)
- [Community](#community)
- [Support Teampass](#support-teampass)
- [License](#license)

---

## About

**Teampass is an open-source credential vault you run yourself.** No account to create, no company behind the curtain holding your data — just a PHP/MySQL application on your own server, with folder-level access control, per-user encryption keys and a full audit trail.

It has been built and maintained since 2009, driven by what real teams actually run into: who should see which credential, how to prove it to an auditor, and how to stop passwords living in chat threads and spreadsheets.

<div align="center">
  <img src="https://teampass.net/images/portfolio/tp3_sw_1.png" width="820" alt="Teampass interface" />
</div>

<details>
<summary><b>📸 More screenshots</b></summary>
<br />

**Items and secrets**

<img src="docs/_media/tp3_items_1.png" width="420" alt="Item list" />
<img src="docs/_media/tp3_items_2.png" width="420" alt="Item detail" />

**Folders and roles**

<img src="docs/_media/tp3_subfolders_1.png" width="420" alt="Folder tree" />
<img src="docs/_media/tp3_features_roles_1.png" width="420" alt="Roles" />
<img src="docs/_media/tp3_features_roles_3.png" width="420" alt="Role rights" />
<img src="docs/_media/tp3_features_roles_5.png" width="420" alt="Role assignment" />

**Authentication and MFA**

<img src="docs/_media/tp3_auth_mfa_1.png" width="420" alt="MFA setup" />
<img src="docs/_media/tp3_auth_oauth2_1.png" width="420" alt="OAuth2 settings" />

**Encryption keys**

<img src="docs/_media/tp3_keys_1.png" width="420" alt="Key management" />
<img src="docs/_media/tp3_keys_5.png" width="420" alt="Key regeneration" />

**Search, export, one-time view**

<img src="docs/_media/tp3_settings_keyword_search.png" width="420" alt="Keyword search" />
<img src="docs/_media/tp3_export_1.png" width="420" alt="Export" />
<img src="docs/_media/tp3_otv_1.png" width="420" alt="One-time view" />
<img src="docs/_media/tp3_otp_1.png" width="420" alt="TOTP" />

**Background tasks**

<img src="docs/_media/tp3_tasks_04.png" width="420" alt="Tasks" />
<img src="docs/_media/settings_tasks_options_01.png" width="420" alt="Task settings" />

</details>

---

## Who it's for

<table>
<tr>
<td width="33%" valign="top">

### 🏠 Individuals & Homelab

***Own your vault, literally.***

- Runs on a Raspberry Pi or a €5 VPS
- Personal folders encrypted with your own key
- Import from Bitwarden, LastPass, 1Password or KeePassXC
- Free forever, no sign-up required

</td>
<td width="33%" valign="top">

### 👥 Teams & SMB

***Stop sharing passwords in chat.***

- Folders and roles instead of a shared document
- A record of who accessed what, and when
- Secure Send for clients and contractors
- Browser extension for day-to-day autofill

</td>
<td width="33%" valign="top">

### 🏛️ Enterprise & Regulated

***Prove your access controls, don't just claim them.***

- Access recertification campaigns with immutable decisions
- Compliance reports and evidence export
- LDAP/AD with nested groups, OAuth2 SSO
- Data classification and ownership

</td>
</tr>
</table>

---

## Security

### Encryption you can describe to an auditor

Secrets are encrypted with **AES-256-GCM** using random nonces and per-secret salts, under **256-bit object keys**. The private key that unlocks them is derived from your password with **PBKDF2-SHA256 at 600 000 iterations**.

- **Authenticated encryption** — tampering is detected, not silently decrypted - **Per-user key distribution** — every user holds their own RSA-wrapped copy of each object key, so removing an account actually revokes access instead of just hiding a button
- **Lazy migration** — format upgrades happen on access, with no maintenance window

<div align="center">
  <img src="docs/_media/tp3_encryption_model.webp" width="700" alt="Teampass encryption model" />
</div>

### Transparency over silence

> A password manager that reports no vulnerabilities is not a password manager that has none.

Findings are triaged, fixed and published as GitHub Security Advisories with CVE identifiers.

- 🔒 [Security policy and how to report](SECURITY.md)
- 📋 [Published advisories](https://github.com/nilsteampassnet/TeamPass/security/advisories)
- 🛡️ [Security hardening guide](https://documentation.teampass.net/#/install/security-hardening)

**Found a vulnerability?** Please report it privately through [GitHub Security Advisories](https://github.com/nilsteampassnet/TeamPass/security/advisories/new) — never in a public issue.

---

## Features

<details>
<summary><b>🗂️ Folder and role access control</b></summary>
<br />

- [Folders](https://documentation.teampass.net/#/features/folders) — unlimited nesting, per-folder password complexity rules
- [Roles](https://documentation.teampass.net/#/features/roles) — grant access by role, not user by user
- [Rights](https://documentation.teampass.net/#/features/rights) — read, write, no-edit, no-delete, resolved least-permissive-wins
- [Users](https://documentation.teampass.net/#/features/users) — per-user overrides on top of roles
- Personal folders that nobody else can read, including administrators

</details>

<details>
<summary><b>🔐 Authenticated encryption</b></summary>
<br />

- [Encryption model](https://documentation.teampass.net/#/install/encryption) — how keys are derived, wrapped and distributed
- [Encryption improvements](https://documentation.teampass.net/#/install/encryption-improvements) — the AES-256-GCM format and its lazy migration
- [Key management](https://documentation.teampass.net/#/features/keys) — regeneration, recovery, per-user key repair

</details>

<details>
<summary><b>📊 Security posture</b></summary>
<br />

- Security Posture Dashboard scoring weak, reused and breached credentials
- [Breach detection](https://documentation.teampass.net/#/features/breach-detection) — Have I Been Pwned checks without sending your passwords
- [Password renewal](https://documentation.teampass.net/#/features/renewal) — expiry policies and reminders
- [Micro-learning](https://documentation.teampass.net/#/features/micro-learning) — in-app nudges instead of a yearly slideshow

</details>

<details>
<summary><b>🏛️ Governance and audit</b></summary>
<br />

- [Access reviews](https://documentation.teampass.net/#/manage/access-reviews) — recertification campaigns with immutable decisions
- [Compliance reports](https://documentation.teampass.net/#/manage/compliance-reports) — evidence you can hand to an auditor
- [Rotation tracking](https://documentation.teampass.net/#/manage/rotation-tracking) — what was rotated, when, by whom
- [Leaver risk](https://documentation.teampass.net/#/features/leaver-risk) — what a departing user could still know
- [Data classification](https://documentation.teampass.net/#/features/classification) — ownership and sensitivity labels

</details>

<details>
<summary><b>🪪 Identity integration</b></summary>
<br />

- [Authentication](https://documentation.teampass.net/#/features/authentication) — local, LDAP/AD with nested groups, OAuth2 / SSO
- Multi-factor: TOTP (Google Authenticator), Duo Security, YubiKey, AGSES
- [Network ACL](https://documentation.teampass.net/#/manage/network-acl) — restrict access by IP range
- [Session management](https://documentation.teampass.net/#/misc/session-management) — timeouts, concurrent sessions, Redis-backed storage

</details>

<details>
<summary><b>🤖 Automation</b></summary>
<br />

- [REST API](https://documentation.teampass.net/#/api/api-basic) — JWT-authenticated, OpenAPI 3.1 spec, Bash and PowerShell clients
- [Browser extension](https://documentation.teampass.net/#/misc/extension) — autofill, capture, one-click auto-configuration
- [Background tasks](https://documentation.teampass.net/#/manage/tasks) — key distribution, notifications, maintenance
- [Real-time collaboration](https://documentation.teampass.net/#/features/collaboration) — WebSocket sync and edition locks
- [Backups](https://documentation.teampass.net/#/features/backups) — scheduled, encrypted database dumps

</details>

<details>
<summary><b>📦 Migration in and out</b></summary>
<br />

- [Import](https://documentation.teampass.net/#/features/import) — Bitwarden, LastPass, 1Password, KeePassXC, CSV
- [Export](https://documentation.teampass.net/#/features/export) — CSV, PDF, and a self-contained encrypted offline HTML vault
- No lock-in: it is your database, on your server, under GPL-3.0

</details>

<details>
<summary><b>⚡ Daily productivity</b></summary>
<br />

- [Search](https://documentation.teampass.net/#/features/search) — across labels, descriptions, tags and custom fields
- [Command palette](https://documentation.teampass.net/#/features/command-palette) — keyboard-first navigation
- [Favourites](https://documentation.teampass.net/#/features/favourites) and quick access to recent items
- [Custom fields](https://documentation.teampass.net/#/features/custom-fields) — individually encryptable, role-restricted
- [Knowledge base](https://documentation.teampass.net/#/features/knowledge-base) and [notification center](https://documentation.teampass.net/#/features/notification-center)
- One-time view links and Secure Send for sharing outside the vault

</details>

---

## Get started

### Requirements

| | |
|---|---|
| **Database** | MySQL 5.7+ or MariaDB 10.7+ |
| **PHP** | 8.2 or newer (tested against 8.3) |
| **Required extensions** | `openssl` `mysqli` `mbstring` `bcmath` `iconv` `xml` `gd` `curl` `gmp` — plus `ldap` for LDAP/AD |
| **Recommended extensions** | `apcu` (config cache) · `opcache` (performance) · `redis` (HA sessions) · `pcntl` + `posix` (WebSocket daemon) |

Teampass follows active PHP support. Running the latest stable PHP release is strongly recommended for both security and performance.

### 🐳 Docker

```bash
docker run -d --name teampass \
  -p 8080:80 \
  -v teampass_data:/var/www/html \
  teampass/teampass:latest
```

Images are published to both registries:

- Docker Hub — `teampass/teampass`
- GitHub Container Registry — `ghcr.io/nilsteampassnet/teampass`

📖 [Docker guide](docs/DOCKER.md) · [Migrating an existing install to Docker](docs/DOCKER-MIGRATION.md)

### 🖥️ Bare metal (recommended for production)

Installing directly on a PHP/MySQL server gives the best performance and the most control over your environment.

- 📖 [Official installation guide](https://documentation.teampass.net/#/install/installation)
- 🔄 [Upgrade guide](https://documentation.teampass.net/#/install/upgrade)
- 🔑 [File permissions](https://documentation.teampass.net/#/install/file-permissions)
- 🎥 [Video tutorial](https://youtu.be/eXieWAIsGzc?feature=shared)

---

## Documentation

| | |
|---|---|
| 📖 **[Full documentation](https://documentation.teampass.net)** | Install, features, administration |
| 🚀 **[Installation](https://documentation.teampass.net/#/install/installation)** | Step-by-step first setup |
| 🔄 **[Upgrade](https://documentation.teampass.net/#/install/upgrade)** | Moving between versions |
| 🛡️ **[Security hardening](https://documentation.teampass.net/#/install/security-hardening)** | Production checklist |
| ⚙️ **[Performance](https://documentation.teampass.net/#/install/performance)** | PHP-FPM, caching, tuning |
| 🔌 **[REST API](https://documentation.teampass.net/#/api/api-basic)** | Endpoints, JWT auth, clients |
| 🧩 **[Browser extension](https://documentation.teampass.net/#/misc/extension)** | Setup and usage |
| 🩺 **[Troubleshooting](https://documentation.teampass.net/#/misc/troubleshooting)** | When something goes wrong |

---

## Languages

Teampass ships in **25 languages**, translated by the community.

<table>
<tr>
<td><img src="public/assets/images/flags/us.png" width="16" alt="" /> English</td>
<td><img src="public/assets/images/flags/fr.png" width="16" alt="" /> French</td>
<td><img src="public/assets/images/flags/de.png" width="16" alt="" /> German</td>
<td><img src="public/assets/images/flags/es.png" width="16" alt="" /> Spanish</td>
<td><img src="public/assets/images/flags/it.png" width="16" alt="" /> Italian</td>
</tr>
<tr>
<td><img src="public/assets/images/flags/pr.png" width="16" alt="" /> Portuguese</td>
<td><img src="public/assets/images/flags/br.png" width="16" alt="" /> Portuguese (BR)</td>
<td><img src="public/assets/images/flags/nl.png" width="16" alt="" /> Dutch</td>
<td><img src="public/assets/images/flags/ru.png" width="16" alt="" /> Russian</td>
<td><img src="public/assets/images/flags/ua.png" width="16" alt="" /> Ukrainian</td>
</tr>
<tr>
<td><img src="public/assets/images/flags/pl.png" width="16" alt="" /> Polish</td>
<td><img src="public/assets/images/flags/cz.png" width="16" alt="" /> Czech</td>
<td><img src="public/assets/images/flags/hu.png" width="16" alt="" /> Hungarian</td>
<td><img src="public/assets/images/flags/ro.png" width="16" alt="" /> Romanian</td>
<td><img src="public/assets/images/flags/bg.png" width="16" alt="" /> Bulgarian</td>
</tr>
<tr>
<td><img src="public/assets/images/flags/gr.png" width="16" alt="" /> Greek</td>
<td><img src="public/assets/images/flags/tr.png" width="16" alt="" /> Turkish</td>
<td><img src="public/assets/images/flags/se.png" width="16" alt="" /> Swedish</td>
<td><img src="public/assets/images/flags/no.png" width="16" alt="" /> Norwegian</td>
<td><img src="public/assets/images/flags/ee.png" width="16" alt="" /> Estonian</td>
</tr>
<tr>
<td><img src="public/assets/images/flags/catalonia.png" width="16" alt="" /> Catalan</td>
<td><img src="public/assets/images/flags/cn.png" width="16" alt="" /> Chinese</td>
<td><img src="public/assets/images/flags/ja.png" width="16" alt="" /> Japanese</td>
<td><img src="public/assets/images/flags/vi.png" width="16" alt="" /> Vietnamese</td>
<td>🇸🇦 Arabic <sub>(in progress)</sub></td>
</tr>
</table>

Translations are managed on POEditor — a few strings from you go a long way.

[![Help translate](https://img.shields.io/badge/Help_translate-POEditor-24c8ff?style=for-the-badge&labelColor=0f2740)](https://poeditor.com/join/project?hash=0vptzClQrM)

---

## Community

### Contributing

Contributions of any kind are very welcome. Fork the repo, make your changes, then open a pull request — see [CONTRIBUTING.md](.github/CONTRIBUTING.md) for the development setup, coding standards and branch conventions, and [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) for how we work together.

[![Submit a PR](https://img.shields.io/badge/Submit_a_PR-GitHub-%23060606?style=for-the-badge&logo=github&logoColor=fff)](https://github.com/nilsteampassnet/TeamPass/compare)

### Reporting bugs

If something does not work as it should, raise a ticket. Please include the steps to reproduce, your server configuration, and the diagnostic report from **Admin → Bug icon (bottom left)**.

[![Raise an Issue](https://img.shields.io/badge/Raise_an_Issue-GitHub-%23060606?style=for-the-badge&logo=github&logoColor=fff)](https://github.com/nilsteampassnet/TeamPass/issues/new/choose)

### Asking questions

Questions, deployment advice and ideas belong in Discussions rather than the issue tracker.

[![Discussions](https://img.shields.io/badge/Ask_a_question-Discussions-%23060606?style=for-the-badge&logo=github&logoColor=fff)](https://github.com/nilsteampassnet/TeamPass/discussions)

### Contributors

Teampass is what it is thanks to these people.

[![Contributors](https://readme-contribs.as93.net/contributors/nilsteampassnet/TeamPass?perRow=12&shape=squircle)](https://github.com/nilsteampassnet/TeamPass/graphs/contributors)

### Star history

[![Star History Chart](https://api.star-history.com/svg?repos=nilsteampassnet/TeamPass&type=Date)](https://star-history.com/#nilsteampassnet/TeamPass&Date)

---

## Support Teampass

Teampass is free, GPL-3.0, and has been maintained by one person since 2009. There is no company behind it — which is exactly the point, and also why support matters.

### What sponsorship funds

- **Security work** — triaging reports, fixing them, and publishing advisories with CVEs
- **Releases** — testing, upgrade paths, and keeping older installations able to move forward
- **Documentation** — the guides at documentation.teampass.net
- **Translations** — coordinating 25 languages
- **Infrastructure** — Docker images, CI, and the project websites

### Become a sponsor

The goal is **100 monthly sponsors**. Every tier helps, and small recurring amounts help most because they make the work predictable.

[![Sponsor on GitHub](https://img.shields.io/badge/Sponsor_on_GitHub-nilsteampassnet-%23ff4dda?style=for-the-badge&logo=githubsponsors&logoColor=ff4dda)](https://github.com/sponsors/nilsteampassnet)
[![Donate via PayPal](https://img.shields.io/badge/One--off_donation-PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=fff)](https://www.paypal.com/donate/?hosted_button_id=XUVWYJ7J92X6L)

### Sponsors

Huge thanks to everyone who sponsors this work — you keep Teampass free for everyone else.

[![Sponsors](https://readme-contribs.as93.net/sponsors/nilsteampassnet?perRow=12&shape=squircle)](https://github.com/sponsors/nilsteampassnet)

### Commercial support

Sponsorship funds the free server. The browser extension and professional services fund the roadmap.

| | |
|---|---|
| **Pro Extension** | Browser autofill, capture and phishing protection — from €49/year, 30-day trial |
| **Services** | Feature development, deployment assistance, priority handling — quoted per engagement |

[![Pricing](https://img.shields.io/badge/See_pricing-teampass.net-24c8ff?style=for-the-badge&labelColor=0f2740)](https://teampass.net/pricing.html)

---

## License

Teampass is released under the **[GNU General Public License v3.0](LICENSE.md)**.

You are free to use, study, modify and redistribute it — including commercially — provided derivative works remain under the same license.

📋 [Dependency license compliance report](LICENSE_COMPLIANCE_REPORT.md)

---

<div align="center">

<img src="https://teampass.net/images/madeinfrance.png" width="90" alt="Made in France" />

**Built and maintained by [Nils Laumaillé](mailto:nils@teampass.net) since 2009.**

Copyright © 2009-2026 Nils Laumaillé

<br />

[![StandWithUkraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

</div>
