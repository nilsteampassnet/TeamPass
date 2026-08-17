# Security Policy

## Supported Versions

This project is currently being supported with security updates.

| Version | Supported          |
| ------- | ------------------ |
| 3.x     | :white_check_mark: |

## Reporting a Vulnerability

For any vulnerability or potential security issue, please provide complete description by filling in a new advisory form from https://github.com/nilsteampassnet/TeamPass/security/advisories.

This is the only supported reporting channel. Reports sent by email are not tracked and may be filtered as spam before anyone sees them.

## Published Security Advisories

Vulnerabilities that have been fixed and publicly disclosed. The GitHub Security Advisory (GHSA) is the authoritative record for each entry. `pending` means a CVE ID has been requested but not yet assigned; the advisory is updated automatically once it is.

Each entry is fixed in the version listed and in every later release. **Always upgrade to the latest release** rather than to the exact version named here.

| CVE            | Advisory                                                                                                  | Summary                                                                                        | Fixed in |
| -------------- | --------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | -------- |
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
