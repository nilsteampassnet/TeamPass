<!-- docs/manage/tools.md -->

## Generalities

> The `Tools` page groups maintenance and repair operations for the encrypted data.

It is only accessible to **administrators**.

⚠️ These tools can have a direct impact on the database content. Always perform a database backup before using them.

## Restore missing sharekeys

### Purpose

In Teampass, every encrypted object (item password, encrypted custom field, attached file) has one sharekey per user. A user with folder access but **no sharekey** for an object sees the object but cannot decrypt it — typically shown as a crossed-out password icon.

Missing sharekeys can appear after an interrupted background task, a failure during key distribution, or historical bugs (for example when copying a folder with its items). This tool detects and recreates them.

### How it works

1. **Analyze** — a read-only pass. For each object type (items, custom fields, files) it reports:
   * the number of objects concerned,
   * the number of missing sharekeys (user × object pairs),
   * the number of objects for which the internal `TP` account has no reference key,
   * the number of objects that cannot be repaired automatically.

   When objects without a `TP` reference key are found, a **Show details** button lists them (first 100 per type) with, for each one, the users still holding a valid sharekey. This tells you exactly who to ask to re-save an object that the tool cannot repair automatically; an object with no key holder at all is highlighted — its content cannot be recovered.

2. **Repair** — a two-phase operation:
   * First, the missing reference keys of the internal `TP` account are recreated using **your own account's keys** (this runs in your browser session, because your private key only exists there).
   * Then a **background task** (`restore_missing_sharekeys`, visible on the Tasks page) walks all shared objects and recreates every missing user sharekey, using the `TP` account key as reference.

### Constraints and guarantees

* **Admin only** — the tool is on the Tools page, restricted to administrators.
* **Idempotent** — only missing (or empty) sharekeys are created. Existing keys are never modified or deleted. The tool can be relaunched safely.
* **Personal items are never redistributed** — a personal object only carries a key for its owner and for the internal `TP` account. The repair gives the owner their key back when it is missing, and removes the keys other users may still hold on it (see [Personal objects](#personal-objects)).
* **Eligible users** — keys are created for the same population as during a normal item save: all users owning a public key, excluding the internal OTV/SSH/API accounts.
* **Single instance** — a new repair task is refused while a previous one is still pending or running.
* **Audit** — the launch and the final counts are recorded in the system logs (`admin_action`).

### Objects that cannot be repaired automatically

If neither the `TP` internal account nor your account own a valid sharekey for an object, its encryption key cannot be recovered by the tool. In that case, ask any user who can still open the object to **re-save it**: saving an item redistributes fresh sharekeys to all users.

### Personal objects

Personal items, their custom fields and their attachments are analysed in a separate table, because their repair is different: it never creates a key for anyone but the owner.

| Column | Meaning |
|--------|---------|
| **Owner cannot read** | Personal objects whose owner has no usable key |
| **Repairable here** | The owner's key can be rebuilt from the `TP` reference key: the **Repair** task does it |
| **Needs the owner** | The `TP` reference key is missing, but the owner can still read the object |
| **Not automatically recoverable** | Neither the owner nor the `TP` account holds a usable key |

* **Needs the owner** — nobody but the owner can rebuild the `TP` reference key, from their own session. Ask each owner to open **My Profile** and click **Repair my personal items encryption keys**. Nothing is lost in the meantime.
* **Not automatically recoverable** — the content cannot be recovered, only recreated.
* The owner is the owner of the personal folder, cross-checked with the user who created the item. When the owner cannot be determined (*Owner unknown* in the details) or disagrees with the creator, the object is reported and left untouched.
* An object without a usable `TP` reference key keeps any key another user still holds on it: that key is the last way to open it, so it is not deleted.

## Other tools

* **Fix personal items are empty** — repairs personal items after a legacy migration issue (per-user pass, requires the user's old PSK).
* **Fix items are empty after user OTP change** — regenerates the master (`TP` account) sharekeys from a selected reference user. A backup of the replaced keys is created and can be restored with the *Restore keys* tool. Note: this tool only repairs the internal `TP` account keys, not the end-user keys — use *Restore missing sharekeys* for those.
