# Security Policy

## Supported Versions

TeamPass is maintained as a **single line**: only the latest published release receives security
fixes. There are no maintenance branches and no backports — a fix ships in the next release of the
current line, and nowhere else.

| Version                   | Supported          | Notes                                                        |
| ------------------------- | ------------------ | ------------------------------------------------------------ |
| 3.2.2.x                   | :white_check_mark: | Current maintenance line. Security fixes are released here.  |
| 3.2.1.x and earlier 3.2.x | :x:                | End of life — upgrade to the latest release.                 |
| 3.1.x                     | :x:                | End of life — upgrade to the latest release.                 |
| 3.0.x                     | :x:                | End of life — upgrade to the latest release.                 |
| 2.x and earlier           | :x:                | End of life — upgrade to the latest release.                 |

**Upgrading is the only supported remediation for an unsupported version.** No patch, hotfix or
backported release will be produced for a version that is no longer maintained, whatever the
severity of the issue.

If you run an unsupported version, assume you are affected by every advisory listed below whose
*Fixed in* column is later than the version you run — and by any issue that was fixed before this
list was started.

## Reporting a Vulnerability

For any vulnerability or potential security issue, please provide a complete description by filling
in a new advisory form from
<https://github.com/nilsteampassnet/TeamPass/security/advisories/new>.

This is the only supported reporting channel. Reports sent by email are not tracked and may be
filtered as spam before anyone sees them. **Please do not open a public issue, a discussion or a
pull request for a security bug** — use the advisory form so the report stays private until a fix
is available.

A useful report contains: the exact TeamPass version, the relevant configuration (authentication
mode, personal folders, API enabled…), a reproducible proof of concept, and the impact you
consider it has.

### Reports against an unsupported version

Reports are welcome even when they target a version that is no longer maintained. They are triaged
exactly like any other report, and the outcome depends on a single question: **is the issue still
present in the current release?**

| Situation                                                        | Outcome                                                                                                                                 |
| ---------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Still present in the current release                             | Normal handling — fix, release, advisory published naming the fixing version, CVE requested.                                             |
| Already fixed in an earlier release                              | No new release. An advisory is still published for the record, with the affected range and the version that fixed it. Reporter credited. |
| Reproducible only in an end-of-life version, not in the current one | Same as above. The remediation stated in the advisory is "upgrade"; no backported patch is issued.                                       |

Publishing an advisory for an end-of-life version is deliberate, not a formality: it is the only
mechanism that tells operators still running that version that they are exposed. It does **not**
put the version back into support.

Duplicates are declined and linked to the advisory that already covers the issue; when a duplicate
brings new analysis or a new attack path, the existing advisory is updated and its author is added
to the credits rather than a second advisory being published for the same flaw.

### Scope

**In scope** — anything in this repository that a remote attacker, or an authenticated user acting
beyond their granted rights, can abuse against a correctly deployed instance running the current
release: authentication and session handling, authorization and folder rights, the REST API,
encryption and key management, injection (SQL, XSS, path traversal, command), and the installer
and upgrade scripts.

**Out of scope**

- Self-XSS requiring the victim to paste a payload into their own browser.
- Findings produced by an automated scanner with no working proof of concept.
- Issues in third-party dependencies with no demonstrated exploit path in TeamPass — report those
  upstream.
- Anything that presupposes an already-compromised administrator account, shell access to the
  server, or direct database access.
- Denial of service through resource exhaustion on your own instance.
- Deliberately insecure deployments: TeamPass served over plain HTTP, the `install/` directory left
  in place after setup, debug output enabled, world-readable configuration files.
- Missing hardening headers or cookie flags that the deployment (web server / reverse proxy) is
  responsible for.
- Social engineering, phishing, physical access.

### Process and timeline

1. **Acknowledgement** — within 5 business days.
2. **Assessment** — the report is reproduced against the current release, and the version range
   actually affected is established from the code, not assumed from the version you tested on.
3. **Fix** — target of 90 days for a confirmed issue in the current release; critical
   authentication or authorization bypasses are handled faster.
4. **Disclosure** — the advisory is published when the fixing release is out. When the issue turns
   out to be already fixed, it is published as soon as the analysis is confirmed.
5. **Credit** — reporters are credited in the advisory unless they ask not to be. TeamPass runs no
   bug bounty and offers no monetary reward.
6. **CVE** — requested through GitHub for every published advisory.

Please keep the report private until the advisory is published.

## Published Security Advisories

Vulnerabilities that have been fixed and publicly disclosed. The GitHub Security Advisory (GHSA) is the authoritative record for each entry. `pending` means a CVE ID has been requested but not yet assigned; the advisory is updated automatically once it is.

Each entry is fixed in the version listed and in every later release. **Always upgrade to the latest release** rather than to the exact version named here.

| CVE            | Advisory                                                                                                  | Summary                                                                                        | Fixed in |
| -------------- | --------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | -------- |
| _pending_      | [GHSA-8fg2-3gpc-x8m7](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-8fg2-3gpc-x8m7) | Sixteen stored XSS issues across the web UI, API and log tables.                                | 3.2.0.3  |
| _pending_      | [GHSA-cpgh-9h3x-r8gm](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-cpgh-9h3x-r8gm) | Stored XSS in the One-Time View page via an unescaped URL field.                                | 3.2.0.4  |
| _pending_      | [GHSA-fhm7-pf6p-prgg](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-fhm7-pf6p-prgg) | Inverted admin authorization check making critical admin functions inaccessible.                | 3.2.0.4  |
| CVE-2026-68936 | [GHSA-x8jf-9g87-j232](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-x8jf-9g87-j232) | Mass assignment in user management leading to privilege escalation.                             | 3.2.0.8  |
| CVE-2026-68937 | [GHSA-2mvr-v9w8-34c7](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-2mvr-v9w8-34c7) | OAuth2 authentication bypass.                                                                   | 3.2.0.8  |
| CVE-2026-68938 | [GHSA-fqg6-xvv8-w228](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-fqg6-xvv8-w228) | SQL injection in the user logs datatable ordering.                                              | 3.2.0.8  |
| _pending_      | [GHSA-wwxq-c766-v93w](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-wwxq-c766-v93w) | Authenticated path traversal in the file-upload handler leading to remote code execution.       | 3.2.0.8  |
| _pending_      | [GHSA-3f3c-cw29-xxm7](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-3f3c-cw29-xxm7) | Decoupled authorization in `downloadFile.php` allowing arbitrary file download.                 | 3.2.1.0  |
| _pending_      | [GHSA-58ph-5gg6-h2v8](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-58ph-5gg6-h2v8) | Manager takeover of managed accounts through the legacy user update handler.                    | 3.2.1.0  |
| _pending_      | [GHSA-6x6x-v79m-v5x3](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-6x6x-v79m-v5x3) | Unauthenticated 2FA-secret reset, username enumeration and password-verification oracle.        | 3.2.1.0  |
| _pending_      | [GHSA-cm5h-m2xm-5pxr](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-cm5h-m2xm-5pxr) | Unauthenticated forced password reset and brute-force lockout wipe.                             | 3.2.1.0  |
| _pending_      | [GHSA-gjc5-pmxw-58p4](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-gjc5-pmxw-58p4) | Insufficient role-assignment scoping in `save_user_change`.                                     | 3.2.1.0  |
| _pending_      | [GHSA-hjhc-6g7v-8jxr](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-hjhc-6g7v-8jxr) | Folder authorization bypass in `show_details_item` exposing item metadata.                      | 3.2.1.0  |
| _pending_      | [GHSA-qhff-v9qj-75wc](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-qhff-v9qj-75wc) | Cross-user audit-log disclosure via an arbitrary `userId` in the user-logs datatable.           | 3.2.1.0  |
| _pending_      | [GHSA-r298-6mxv-j9hc](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-r298-6mxv-j9hc) | Stored XSS in the recycle-bin listing via API-created item labels.                              | 3.2.1.0  |
| _pending_      | [GHSA-cgcj-f9rx-c8r4](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-cgcj-f9rx-c8r4) | Import module authorization flaws enabling cross-user credential theft.                         | 3.2.1.1  |
| _pending_      | [GHSA-66q9-mxf2-xqw6](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-66q9-mxf2-xqw6) | Missing authorization in `update_folder`, letting any user re-parent or hide any folder.        | 3.2.1.2  |
| _pending_      | [GHSA-9g8h-6qg5-cmhx](https://github.com/nilsteampassnet/TeamPass/security/advisories/GHSA-9g8h-6qg5-cmhx) | Stored XSS through item labels, folder titles and directory-supplied attributes.                | 3.2.1.6  |
