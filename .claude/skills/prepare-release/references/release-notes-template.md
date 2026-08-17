# GitHub release-notes template — TeamPass 3.2.1.x model

Replace `<VERSION>` and `<BASE>` (the previous tag). Drop sections that have no content.
Everything from `## Important` down is constant boilerplate — keep it verbatim.

---

```markdown
# What's Changed

<One paragraph: what kind of release this is, what it fixes, who should upgrade.
 Example (3.2.1.3): "This is a maintenance release on the 3.2.1 line. It fixes an Active
 Directory group mapping regression introduced in 3.2.1.2, tightens the Security Posture
 authorization boundary, ... Upgrading is recommended for all installations, and **required**
 for anyone mapping Active Directory groups to roles.">

<Schema statement — mandatory. One of:
 "**This release is code only: no `ALTER TABLE`, no schema change and no new data migration.**
  `UPGRADE_MIN_DATE` is deliberately left at its <BASE> value, so installations already running
  <BASE> are **not** sent back through the upgrade wizard."
 — or —
 "**This release changes the database schema.** `UPGRADE_MIN_DATE` is raised, so every
  installation goes through the upgrade wizard once.">

<Optional call-out for people coming from a specific earlier build:
 "> **Already ran the <X> pre-release?** The delta for you is: ...">

## 🔒 Security fixes

* **<Bolded outcome>** — <cause, then observable effect for an administrator>. <Cite the
  advisory / issue / PR and the contributor: (PR [#5312](https://github.com/nilsteampassnet/TeamPass/pull/5312), @handle)>

## ✨ New features

* **<Feature name>** (PR [#NNNN](https://github.com/nilsteampassnet/TeamPass/pull/NNNN), @handle) — <what it
  does, where it lives in the UI, what it changes for the admin>.

## 🛠️ Improvements

* **<Improvement>** — <what was wrong or limited before, what it is now>.

## 🐛 Bug fixes

* **<Symptom as the user saw it> (issue [#NNNN](https://github.com/nilsteampassnet/TeamPass/issues/NNNN))** —
  <root cause in one or two sentences, then the fix>.

## ⬆️ Upgrade notes

* **Schema.** <No schema change / what the upgrade runs.>
* **<Behaviour that changes on an existing installation>.** <What an admin will observe, and
  what they must re-check or re-configure.>
* **<Settings seeded on fresh installs only>.** <Fallback behaviour on an existing install.>
* **Back up your database before upgrading**, as always.

**Full Changelog**: [<BASE>...<VERSION>](https://github.com/nilsteampassnet/TeamPass/compare/<BASE>...<VERSION>)

## Important

* Requires at least `PHP 8.2`

## Languages

Please join [Teampass v3 translation project on Poeditor](https://poeditor.com/projects/view?id=433631) and translate it for your language.

## Installation

Follow instructions from [Documentation](https://documentation.teampass.net/#/install/installation).

## Upgrade

Follow instructions from [Documentation](https://documentation.teampass.net/#/install/upgrade).

## Ideas and comments

Are welcome ... please use [Discussions](https://github.com/nilsteampassnet/TeamPass/discussions).


[![Download TeamPass](https://a.fsdn.com/con/app/sf-download-button)](https://sourceforge.net/projects/communitypasswo/files/<VERSION>/<VERSION>%20source%20code.zip/download)
```

---

## Themed sections

When one subject dominates the release, add a dedicated section before the standard ones
rather than diluting it across the buckets. 3.2.1.1 used `## 🔐 Encryption hardening` with a
short narrative paragraph and a link to the matching documentation page.

## Tone reference — a good bullet

> * **Security scans no longer abort on a malformed legacy password** (PR [#5311](...), @guerricv) —
>   zxcvbn expects valid UTF-8 and throws on malformed legacy bytes. A single such password
>   aborted the whole scan, the background metadata calculation or the API complexity check.
>   Every server-side evaluation now goes through one safe adapter,
>   `evaluatePasswordStrengthSafely()`, which validates the encoding, catches any evaluator
>   failure and rejects a malformed result. The password itself is never converted or
>   normalised — doing so would change the credential — and the item is reported as
>   unassessable while the run continues.

What makes it work: the bolded outcome first, the technical cause in one sentence, the blast
radius (three call sites), the fix named by function, and the deliberate non-change explained.
