<!-- docs/manage/email-templates.md -->

## Overview

**Configuration → Email templates** lets an administrator rewrite the subject and the body of
every email TeamPass sends, for each installed language, without touching the files shipped with
the application.

Only your changes are stored. An email you never open keeps the text delivered with TeamPass, and
**Reset to default** puts it back. That also means an upgrade never overwrites your work, and your
work never blocks an upgrade.

---

## How resolution works

When TeamPass needs the text of an email, it looks in this order and stops at the first hit:

1. your customization **in the recipient's language**;
2. your customization **in English**;
3. the text shipped with TeamPass **in that language**;
4. the text shipped with TeamPass **in English**.

Two consequences worth remembering:

- **Customizing only English is enough to change every language.** Users who read TeamPass in
  German get your English text until you write a German version.
- **Emptying a template does not blank the email.** A customization saved empty is deleted, and the
  shipped text applies again — the same result as **Reset to default**.

> :bulb: When a language has no customization of its own but English does, the editor tells you so
> explicitly, because what will be sent is the English text and not the shipped translation.

---

## Editing an email

1. Pick the **language** you want to write. The list on the left shows a dot next to every email
   already customized *in that language*, and a counter per section.
2. Click the email you want to change.
3. Edit the **subject** and the **body**. The body editor has a *code view* button if you prefer
   writing HTML directly.
4. **Save**.

### Placeholders

Placeholders such as `#login#` or `#reset_url#` are replaced with real values when the email is
prepared. Click a chip below the editor to insert one where the cursor is.

- **Highlighted chips are required.** Saving a body that dropped one is refused: without
  `#password#`, `#reset_url#`, `#enc_code#` or `#2FACode#`, the recipient has nothing to act on.
- Dropping an *optional* placeholder is allowed; TeamPass only warns you which ones are no longer
  used.
- A placeholder that is not in the list for that email is **not** replaced — it goes out as literal
  text.

> :warning: `#password#` and `#enc_code#` are replaced when the message is actually **sent**, not
> when it is prepared. They cannot be previewed and appear as a placeholder marker in the preview.

### Preview and test

- **Preview** renders the content currently in the editor — unsaved changes included — with sample
  values, after the same sanitizing the real send applies. What you see is what would leave the
  server.
- **Send a test** mails that same rendering to **your own address**, taken from your profile. The
  recipient can never be chosen; if your account has no email address the action is refused.

### Subject and body are stored separately

Several emails share the same subject. Changing the subject of *Encryption keys ready* also changes
it for *Password changed by an administrator* and *Account created by an administrator*, and the
editor lists which ones when it happens.

Some subjects ship with a prefix that is not part of the translation (`TEAMPASS - `,
`[Teampass] `). The editor shows the complete line, prefix included, and the whole of it is
yours to edit — remove the prefix and it disappears from the emails sent. Reset the template and
the prefixed default comes back.

---

## Rules applied when saving

| Field | What TeamPass does |
|---|---|
| Subject | Markup removed, line breaks collapsed into spaces. A subject travels in a mail header, so it must stay plain text. |
| Body | Sanitized against XSS (scripts and event handlers removed), line breaks removed. The formatting tags emails rely on — `<p>`, `<b>`, `<i>`, `<u>`, `<br>`, `<ul>`, `<ol>`, `<li>`, `<a href>` — are kept. Inline styles are **not**: the same sanitizing runs when the email is sent, and it drops every `style` attribute. That is why the editor offers no colour, no highlighting and no alignment — they would look right in the editor and never reach the recipient. |
| Both | Refused above 64 KB. |

Every save and every reset is recorded in **Utilities → Logs**, administration tab, with the email
and the language concerned.

---

## The list of emails

Placeholders in **Required** must stay in the body.

### Users

| Email | Required | Placeholders |
|---|---|---|
| New user: credentials | `#password#` | `#login#` `#password#` |
| Encryption keys ready: credentials | `#password#` | `#lastname#` `#firstname#` `#login#` `#password#` |
| Account ready (external authentication) | — | `#lastname#` `#firstname#` `#login#` |
| Password changed by an administrator | `#password#` | `#lastname#` `#firstname#` `#login#` `#password#` |
| Encryption keys ready | — | `#lastname#` `#firstname#` `#login#` |
| Account created by an administrator | `#password#` | `#lastname#` `#firstname#` `#login#` `#password#` |
| Account created from the directory | `#enc_code#` | `#tp_login#` `#enc_code#` `#tp_link#` |
| Temporary password | `#enc_code#` | `#enc_code#` |
| Inactive account notice | — | `#login#` `#firstname#` `#lastname#` `#inactivity_days#` `#grace_days#` `#action#` `#url#` |

> :bulb: In the account emails, `#lastname#` historically carries the **first** name. `#firstname#`
> was added later and holds the same value. Prefer `#firstname#` in new texts.

### Authentication

| Email | Required | Placeholders |
|---|---|---|
| Two-factor code by email | `#2FACode#` | `#2FACode#` |
| Password recovery link | `#reset_url#` | `#name#` `#lastname#` `#login#` `#reset_url#` |
| Password recovery: temporary password | `#password#` | `#name#` `#lastname#` `#login#` `#password#` `#tp_link#` |

### Security

| Email | Required | Placeholders |
|---|---|---|
| User login notification | — | `#tp_user#` `#tp_date#` `#tp_time#` |
| Account locked notification | — | `#tp_user#` `#tp_name#` `#tp_email#` `#tp_ip#` `#tp_date#` `#tp_time#` `#tp_unlock_at#` |
| Account locked: unlock link | `#reset_url#` | `#name#` `#reset_url#` `#unlock_at#` |
| Security summary | — | `#breached#` `#weak#` `#reused#` `#overdue#` `#total#` `#url#` |

### Items

| Email | Required | Placeholders |
|---|---|---|
| Item created | — | `#label#` `#link#` |
| Item updated | — | `#item_label#` `#item_category#` `#item_id#` `#url#` `#name#` `#lastname#` `#folder_name#` `#changes#` |
| Item opened notification | — | `#tp_user#` `#tp_item#` `#tp_link#` |
| Access request to an item | — | `#tp_item_author#` `#tp_user#` `#tp_item#` |
| Item shared | `#tp_link#` | `#tp_link#` `#tp_user#` `#tp_item#` |

> :bulb: *Item created* historically used `#label` and `#link` without their closing `#`.
> Translations that were never updated still contain that form and keep working — TeamPass
> substitutes both — but write the canonical `#label#` / `#link#` in new texts.
>
> `#changes#` is only filled by one of the two places that send *Item updated*; do not build the
> message around it.

### Maintenance

| Email | Required | Placeholders |
|---|---|---|
| Scheduled backup report | — | `#tp_status#` `#tp_datetime#` `#tp_message#` `#tp_file#` `#tp_size#` `#tp_output_dir#` `#tp_retention_days#` `#tp_purge_deleted#` `#tp_externalized_report#` |
| Scheduled backup: externalization block | — | `#tp_externalized_status#` `#tp_externalized_message#` `#tp_externalized_destination#` `#tp_externalized_target#` `#tp_externalized_file#` `#tp_externalized_size#` `#tp_externalized_retention_days#` `#tp_externalized_retention_count#` `#tp_externalized_purge_deleted#` `#tp_externalized_retry#` |

*Scheduled backup: externalization block* is not an email on its own — it is inserted into the
backup report through `#tp_externalized_report#`, which is why it has no subject.

`#tp_status#` is the only placeholder that also works in a **subject** (the backup report's).

---

## Known limitations

### Editing a template does not change the emails already queued

TeamPass renders an email when it is *queued*, not when it is sent. Messages already waiting in the
sending queue keep the text they were created with. Only `#password#` is resolved later.

### Which language an email uses is not always the recipient's

Some emails are written in the **recipient's** language (account notifications, inactive account
notice, backup report, security summary, *New user: credentials*, *Temporary password*), others in
the language of the **user who triggered the action** (item created, item updated, item shared,
access request). So a French user acting on an item may send a French notification to an English
colleague.

This predates the customization feature and is unchanged by it, but it becomes visible once you
maintain several languages: keep an English version of the item emails up to date.

---

## Turning the feature off

The setting `emails_templates_enabled` is a global switch. Set it to `0` and TeamPass ignores every
stored template and sends the shipped texts again — your customizations are **not** deleted and come
back as soon as you set it to `1`.

```sql
UPDATE teampass_misc SET valeur = '0'
WHERE type = 'admin' AND intitule = 'emails_templates_enabled';
```

The page shows a warning banner while the switch is off. Settings are cached in APCu for 60 seconds,
so allow up to a minute for the change to reach every PHP worker.

---

## Troubleshooting

### My change is not taken into account

- Check that you edited the **language the recipient reads**, not your own. An English-only
  customization does apply everywhere, but a French one only reaches French users.
- Check that the email was not already queued before the change (see the limitation above).
- Check the `emails_templates_enabled` switch.

### A placeholder appears as literal text in the received email

The placeholder is not in the list for that email. Only the chips shown under the editor are
replaced; anything else goes out verbatim.

### Saving is refused

The body lost a required placeholder — the message names which ones. Insert it back with its chip,
or use **Reset to default** and start again from the shipped text.

### I want to start over completely

Reset the templates one by one, or empty the table:

```sql
DELETE FROM teampass_emails_templates;
```

An empty table restores the exact behaviour of a fresh installation.
