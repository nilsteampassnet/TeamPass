# What's new — Encryption hardening (3.2)

> Your secrets, even better protected — and you have nothing to do.

The 3.2 line brings a major upgrade to the **cryptographic core** of TeamPass. The change is
**automatic, transparent and reversible**: your data upgrades itself to the new format as it is
used, with no downtime, no re-typing and no data loss.

---

## In one sentence

**TeamPass now protects your secrets with bank-grade authenticated encryption, upgrades itself
without any service interruption, and guarantees that a personal folder stays truly personal.**

---

## What it brings

### For everyone

- **Truly private personal folders.** A personal item is now decryptable only by its owner (plus
  the internal recovery account) — no longer technically readable by other accounts.
- **Stronger protection of stored secrets**, with no action required on your side.
- **Faster saving** of shared passwords: the heavy work now runs in the background.
- **Invisible migration**: nothing to re-enter, no outage.

### For administrators

| Before | Now |
|---|---|
| The stored-data format had known weaknesses (no integrity check, fixed salt and IV). | **Authenticated AES-256-GCM**: a database leak alone stays unusable, and any tampering with an encrypted value is **detected** instead of silently returning corrupted data. |
| A "personal" folder actually distributed a decryption key to **every** account at creation time. | **Real isolation**: only the owner (and the recovery account) holds a key. A **remediation script** cleans up existing data. |
| Key distribution was brittle: one corrupted user key failed the whole batch; a failed background task never retried. | **Fault tolerance**: the failing user is isolated and logged, everyone else still gets their key; failed background tasks **retry automatically**. |
| Saving a shared password could trigger dozens of heavy crypto operations inside the web request. | **Faster save**: the web request performs a single operation; distribution to other users moves to the background. |

---

## The concepts applied (in plain language)

| Technical concept | What it means |
|---|---|
| **Authenticated encryption (AES-256-GCM)** | The vault can tell if its content was tampered with — it never returns a "doctored" value without flagging it. |
| **Random IV + per-secret random salt** | Two identical passwords no longer produce the same encrypted blob, so nothing can be inferred by comparison. |
| **Modern key derivation (HKDF-SHA256, PBKDF2 600,000 iterations)** | Keys are derived with methods aligned to current (2023) recommendations — far more expensive to brute-force. |
| **256-bit object keys** | The per-secret key entropy went from 64 to 256 bits: out of reach for brute force. |
| **Self-describing versioned format + lazy migration** | The vault upgrades itself **gradually**, secret by secret, as data is read — no "big bang", no maintenance window. |
| **Least privilege / isolation** | "Personal" finally means personal. |
| **Resilience (isolation + retry)** | A transient failure no longer leaves anyone without their key. |

---

## How to roll it out (administrators)

1. **Upgrade** TeamPass. The upgrade script prepares everything (settings + columns). **No
   `ALTER TABLE`** is required on the sensitive data tables — a lightweight deployment.
2. **Back up the database** first (standard reflex, mandatory before running the remediation
   script).
3. **Enable the new format** when you are ready, via the admin toggle **`aes_v2_write_enabled`**
   (Settings → Encryption). While it is off, everything stays readable; once on, new secrets are
   written in the hardened format.
4. **Let the migration happen on its own.** Each secret is re-encrypted to the hardened format the
   first time it is read, and on user login. A **progress indicator** is available in the admin
   area.
5. **(Optional) Clean up existing personal data**: run the personal-sharekeys remediation script
   first in **`--dry-run`** (the default), review the report, then run it for real. See
   [Security hardening](security-hardening.md).
6. **Nothing to ask of your users** — it is transparent. At most, when a *shared* item is edited,
   colleagues may see a few seconds of delay while the background distribution completes.

**Reversibility:** the new format is enabled/disabled by a toggle, and the previous format stays
readable indefinitely. There is no point of no return.


---

## See also

- [Information — encryption model](encryption.md)
- [Security hardening](security-hardening.md)
- [Performance](performance.md)
