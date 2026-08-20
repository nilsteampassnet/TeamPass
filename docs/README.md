# Introduction

> **Teampass is an open-source credential vault you run yourself.**
> Folder-level access control, authenticated AES-256-GCM encryption and built-in compliance
> evidence — on your own servers, under GPL-3.0, maintained since 2009.

[![Release](https://img.shields.io/github/v/release/nilsteampassnet/TeamPass?style=flat-square&color=24c8ff)](https://github.com/nilsteampassnet/TeamPass/releases/latest)
[![License](https://img.shields.io/github/license/nilsteampassnet/TeamPass?style=flat-square&color=24c8ff)](https://github.com/nilsteampassnet/TeamPass/blob/master/LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-24c8ff?style=flat-square)](https://www.php.net/)
[![Docker Pulls](https://img.shields.io/docker/pulls/teampass/teampass?style=flat-square&color=24c8ff)](https://hub.docker.com/r/teampass/teampass)
[![Stars](https://img.shields.io/github/stars/nilsteampassnet/TeamPass?style=flat-square&color=24c8ff)](https://github.com/nilsteampassnet/TeamPass)

## Where to start

| | |
|---|---|
| 🚀 **[Installation](install/installation.md)** | Set up Teampass for the first time |
| 🐳 **[Docker](install/docker.md)** | Run it in a container |
| 🔄 **[Upgrade](install/upgrade.md)** | Move between versions safely |
| 🛡️ **[Security hardening](install/security-hardening.md)** | Production checklist |
| 🩺 **[Troubleshooting](misc/troubleshooting.md)** | When something goes wrong |
| 🔌 **[REST API](api/api-basic.md)** | Endpoints, JWT authentication, clients |

## What Teampass does

**Access control**

- [Folders](features/folders.md) — unlimited nesting with per-folder complexity rules
- [Roles](features/roles.md) and [rights](features/rights.md) — resolved least-permissive-wins
- [Users](features/users.md) — per-user overrides, and personal folders nobody else can read

**Encryption**

- [Encryption model](install/encryption.md) — how keys are derived, wrapped and distributed
- [Encryption hardening](install/encryption-improvements.md) — the AES-256-GCM format and its lazy migration
- [Key management](features/keys.md) — regeneration, recovery and repair

**Governance and audit**

- [Access reviews](manage/access-reviews.md) — recertification campaigns with immutable decisions
- [Compliance reports](manage/compliance-reports.md) and [rotation tracking](manage/rotation-tracking.md)
- [Leaver risk](features/leaver-risk.md) and [data classification](features/classification.md)

**Identity**

- [Authentication](features/authentication.md) — local, LDAP/AD with nested groups, OAuth2 SSO
- Multi-factor: TOTP, Duo Security, YubiKey, AGSES
- [Network ACL](manage/network-acl.md) and [session management](misc/session-management.md)

**Day to day**

- [Search](features/search.md), [command palette](features/command-palette.md), [favourites](features/favourites.md)
- [Custom fields](features/custom-fields.md), [password renewal](features/renewal.md), [breach detection](features/breach-detection.md)
- [Import](features/import.md) from Bitwarden, LastPass, 1Password, KeePassXC — and [export](features/export.md) back out
- [Browser extension](misc/extension.md) and [real-time collaboration](features/collaboration.md)

## Licence

Teampass is free software distributed under the
[GNU GPL-3.0](https://github.com/nilsteampassnet/TeamPass/blob/master/LICENSE.md).

## Support the project

Teampass is maintained by one person and funded by its community. If it is useful to you,
consider [sponsoring the work](https://github.com/sponsors/nilsteampassnet).
