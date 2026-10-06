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
   * First, every shared object that the selected keys can open is checked, and the reference key of the internal `TP` account is rebuilt where it is missing or does not open the object. By default the selected keys are **your own account's**. When your account cannot open the objects, select in **Open the objects with the keys of** a user who can, and enter that user's password: it is sent to the server only, which opens the user's keys there for the duration of the repair. Neither the password nor any private key is stored or sent back to the browser, and the use of another user's keys is recorded in the system logs.
   * Then a **background task** (`restore_missing_sharekeys`, visible on the Tasks page) walks all shared objects and recreates every missing user sharekey, using the `TP` account key as reference.

   An object is only ever opened with a key that has been checked against its encrypted content. A sharekey only proves that a key was encrypted for a user, not that the object is still encrypted with it: when an item is saved and the background distribution of its new keys never completes, the other users — the `TP` account included — keep the previous key. The repair handles both sides of this:
   * a `TP` reference key that no longer opens its object is replaced from the selected keys, provided they open the object and the content decrypts to readable data. The other users' keys on that object came from the same incomplete distribution, so they are removed and the background task recreates them from the corrected reference key;
   * the background task never distributes a `TP` reference key that does not open its object. Such objects are reported in its summary; run the repair again with a user who can open them.

### Constraints and guarantees

* **Admin only** — the tool is on the Tools page, restricted to administrators.
* **Idempotent** — only missing, empty or legacy sharekeys are created. An existing key is modified or deleted only when its object was proven to be encrypted with another key (see above). The tool can be relaunched safely.
* **The internal `TP` account key must be usable** — every check goes through it. When it cannot be opened (typically an instance key in `TEAMPASS_SECRETS` that does not match the database), the repair stops before doing anything and says so.
* **Personal items are never redistributed** — a personal object only carries a key for its owner and for the internal `TP` account. The repair gives the owner their key back when it is missing, and removes the keys other users may still hold on it (see [Personal objects](#personal-objects)).
* **Eligible users** — keys are created for the same population as during a normal item save: all users owning a public key, excluding the internal OTV/SSH/API accounts.
* **Single instance** — a new repair task is refused while a previous one is still pending or running.
* **Audit** — the launch and the final counts are recorded in the system logs (`admin_action`).

### Objects that cannot be repaired automatically

If neither the `TP` internal account nor the selected account own a valid sharekey for an object, its encryption key cannot be recovered by the tool. **Show details** lists the users still holding a key on each such object: select one of them as the source of the repair and run it again, or ask that user to **re-save** the object, which redistributes fresh sharekeys to all users.

Objects whose content decrypts to binary data with every key are stored corrupted: no key can repair them. They are listed by the *Corrupted items* scan of the Health page.

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
* The owner is the owner of the personal folder, cross-checked with the user who created the item. When the owner cannot be determined (*Owner unknown* in the details) or disagrees with the creator, the object is reported and left untouched. One exception: a shared item moved into a personal folder keeps its creator, so the owner is accepted when they made the item's latest move, since only the owner of a personal folder can move an item into it. A folder changed from the item's edit form is not recorded as a move, so such an item is still left untouched.
* An object without a usable `TP` reference key keeps any key another user still holds on it: that key is the last way to open it, so it is not deleted.

## Other tools

* **Fix personal items are empty** — repairs personal items after a legacy migration issue (per-user pass, requires the user's old PSK).
* **Restore keys saved before an OTP repair** — restores the `TP` account keys saved by the former *Fix items are empty after user OTP change* tool. That tool rebuilt the `TP` keys from a reference user's keys; it is replaced since 3.2.2.8 by the reference user option of *Restore missing sharekeys*, which also creates the missing keys, covers custom fields and attachments, and checks every key against the encrypted content.
