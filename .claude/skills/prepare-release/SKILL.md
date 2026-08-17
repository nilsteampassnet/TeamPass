---
name: prepare-release
description: Prepare, cut and publish a TeamPass release following the 3.2.1.x model — scope the commit range, decide the version number, run the release gate, bump the version constants, refresh the Docker image (version arg and base image), regenerate the integrity checksums, finish git flow manually, and draft the GitHub release notes. Use when asked to prepare a release, cut a version, bump TP_VERSION_MINOR, or publish release notes.
---

# Prepare a TeamPass release

Drives a release from `develop` to a tagged, published version, reproducing exactly what was
done for 3.2.1.0 → 3.2.1.3.

**Two irreversible, outward-facing steps require explicit user confirmation before running:**
pushing `master`/`develop`/the tag, and publishing the GitHub release. Everything before that
is local and reversible. Never run those two without an explicit go-ahead.

---

## 0. Inputs to establish first

Ask only if they cannot be derived:

| Input | How to derive |
|---|---|
| Base ref | `git describe --tags --abbrev=0 develop` — the previous release tag |
| Target version | See §2 (patch vs minor decision) |
| Pre-release? | A `.0` opening a new line is usually published as **Pre-release** (3.2.1.0 was); ask |

Everything runs from the repository root. Start from an up-to-date `develop`:

```bash
git checkout develop
git status --short          # must be clean
git rev-parse --short HEAD
```

---

## 1. Scope the release

```bash
BASE=$(git describe --tags --abbrev=0 develop)
git rev-list --format='%h %ci %s' --no-merges "$BASE"..develop | grep -v '^commit'
git diff --stat "$BASE"..develop | tail -1
```

> **rtk gotcha:** the `rtk` hook linearizes `git log` and **hides merge commits**. Use
> `git rev-list --merges --format='%h %s'`, `git rev-parse HEAD` and
> `git show -s --format='%h %s'` whenever the merge structure matters.

Classify every commit into the release-note buckets (§8): security fix / new feature /
improvement / bug fix. Commits titled `Updated languages strings` and pure test commits are
not release-note material on their own — fold them into the feature they belong to.

Then detect the two things that drive the rest of the procedure:

```bash
# (a) Schema / installer changes → decides UPGRADE_MIN_DATE and the upgrade-script work
git diff --stat "$BASE"..develop -- public/install/

# (b) Composer dependency changes → decides whether LICENSE_COMPLIANCE_REPORT.md is regenerated
git diff --stat "$BASE"..develop -- composer.json composer.lock
```

---

## 2. Decide the version number

Version constants live in `app/config/include.php:29-31`:

```php
define('TP_VERSION', '3.2.1');            // major.minor.patch — the "line"
define("UPGRADE_MIN_DATE", "1785099115"); // upgrade-wizard trigger, see §4
define('TP_VERSION_MINOR', '3');          // 4th digit — the patch on that line
```

The published version is `TP_VERSION . '.' . TP_VERSION_MINOR`.

**Patch release on the current line (the common case — 3.2.1.1, .2, .3):**
bump `TP_VERSION_MINOR` only. Any DDL is **appended to the existing
`public/install/upgrade_run_<TP_VERSION>.php`** — no new script, no `TP_VERSION` bump. This
works because every upgrade script is replayed on every upgrade run (see §3).

**New line (3.2.1 → 3.2.2):** bump `TP_VERSION`, reset `TP_VERSION_MINOR` to `0`, and:

1. create `public/install/upgrade_run_<new TP_VERSION>.php`, modelled on
   `upgrade_run_3.2.1.php`;
2. append it to `$scripts_list` in `public/install/upgrade_scripts_manager.php`
   (flat ordered array, appended **at the end**, never reordered);
3. seed every new admin setting / table in `public/install/install-steps/run.step5.php` so
   fresh installs match, and register each new `CREATE TABLE` method as an action in
   `public/install/install-steps/install.js`.

Checks 8 and 9 of the release gate (§3) verify points 3 automatically.

---

## 3. Run the release gate

```bash
scripts/release_check.sh "$BASE"
```

Nine checks: version constants, PHPStan (level from `phpstan.neon`), PHPUnit, PHP lint on
changed files, shell lint, debug-leftover scan on added lines, EN/FR language parity,
installer-vs-upgrade admin-setting seed parity, installer `CREATE TABLE` → `install.js`
registration. Exit code is non-zero on any failure; warnings never fail the run.

**Failures block the release. Warnings are triaged, not ignored** — each one is either fixed
or explicitly justified to the user.

Binaries (Composer vendor dir is `app/vendor`, and `app/vendor/bin/phpunit` is not executable):

```bash
php app/vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php app/vendor/phpunit/phpunit/phpunit --no-coverage
```

Manual checks the gate does not cover — run them when the range touches the matching area:

- **Encryption paths:** every new sharekey read uses `decryptUserObjectKeyWithMigration()`
  (with `increment_id` selected for `sharekeys_fields`); custom fields encrypt **before**
  INSERT.
- **Dual-location classes:** every `teampassclasses` package edited in
  `app/includes/libraries/teampassclasses/` must be identical in `app/vendor/teampassclasses/`
  — only the `vendor/` copy is autoloaded. Sentinel tests:
  `tests/Unit/CryptoManagerCopiesInSyncTest.php`, `tests/Unit/LdapExtraCopiesInSyncTest.php`.
- **Proxy shims:** every new `app/sources/*.queries.php` has a matching
  `public/sources/*.queries.php` shim (a missing shim surfaces as a misleading
  `403 Access Forbidden by CSRFProtector`).
- **Idempotency of the upgrade script** if DDL was added (§4).
- **Docker image freshness.** The image ships an OS, so it ages on its own even when no code
  changed, and Trivy publishes the result as GitHub code-scanning alerts. Check both `FROM`
  lines of `Dockerfile`:

  ```bash
  grep -n '^FROM' Dockerfile
  # Is the pinned Alpine still supported? Compare against the published tags:
  curl -sS 'https://hub.docker.com/v2/repositories/library/php/tags/?page_size=100&name=fpm-alpine' -o /tmp/php-tags.json
  php -r '$d=json_decode(file_get_contents("/tmp/php-tags.json"),true); foreach($d["results"] as $t) echo $t["name"]," ",substr($t["last_updated"],0,10),"\n";'
  ```

  An Alpine version that no longer appears in the recently-updated tags is end of life: its
  packages get no more security fixes, so **every CVE Trivy reports on them is unfixable until
  the base image moves**. Bump the Alpine part and keep the PHP minor, unless the user decides
  to move PHP too. Verify each `apk add` package still exists in the target release before
  trusting the bump — a missing or split package is the usual breakage:

  ```bash
  curl -sS "https://dl-cdn.alpinelinux.org/alpine/v<TARGET>/main/x86_64/APKINDEX.tar.gz" -o /tmp/idx.tar.gz
  tar xzf /tmp/idx.tar.gz -O APKINDEX | grep -x 'P:<package>'
  ```

  Keep the `composer:` builder tag on the **current Composer line** (2.10 as of 3.2.1.7). It
  does *not* need to match the local `composer --version`: the installed versions come from
  `composer.lock`, not from the binary that reads it. The builder is a discarded stage, so its
  own CVEs never ship either — which is why "latest stable" is the right default here.

  > Docker is installed in the dev environment but WSL integration is off, so there is no
  > daemon and no local build. **This does not mean the `FROM` change goes unvalidated until
  > the tag:** `docker-publish.yml` fires on `push` to `develop` *and* `master`, not only on
  > tags. Pushing `develop` therefore builds the image on GitHub runners and is the earliest
  > real proof that the base image still compiles `intl`, `xml`, `gd`, `ldap` and the rest.
  > Watch it with `gh run watch` and read the Trivy step.
  >
  > A cheap pre-flight before pushing, when only `FROM` moved:
  >
  > ```bash
  > curl -s "https://hub.docker.com/v2/repositories/library/php/tags/<TAG>" | head -c 200
  > ```
  >
  > An HTTP body starting with `{"creator":` means the tag exists; `{"message": "httperror 404"`
  > means the `FROM` line would fail instantly. It proves the tag, never the compilation.

If dependencies changed (§1b), regenerate the licence report and commit it with the release:

```bash
php app/vendor/bin/composer-license-checker
```

---

## 4. Decide `UPGRADE_MIN_DATE`

`upgradeRequired()` (`app/sources/main.functions.php`) compares the DB `upgrade_timestamp`
against this constant and sends the whole installation through the upgrade wizard when it is
lower.

**Bump it only when the release carries a DB schema change or a data migration.** A code-only
release keeps the previous value — 3.2.0.9 and 3.2.1.3 both did, deliberately, so
installations already on the previous patch are not sent back through the wizard.

When it is bumped, the value is the epoch of the release moment:

```bash
date +%s
```

**Every `upgrade_run_*.php` is replayed on every upgrade, from index 0.** Nothing compares the
installed version — `upgrade_scripts_manager.php` hands out `$scripts_list` entries by index and
the client walks them all. So any DDL you add must be idempotent:
`CREATE TABLE IF NOT EXISTS`, `addColumnIfNotExist()`, `checkIndexExist($table,$index,$sql)`
(both in `public/install/tp.functions.php`), `INSERT ... ON DUPLICATE KEY UPDATE`.
Precedent to copy: `upgrade_run_3.2.0.php` (guarded de-duplication + unique index).

---

## 5. Create the release branch and the bump commit

```bash
git checkout develop
git flow release start <VERSION>          # creates release/<VERSION>
```

> If git flow AVH refuses (a stale `release/*` branch already exists), either delete the stale
> branch or create the branch by hand: `git checkout -b release/<VERSION> develop`, and finish
> manually as in §7 — the manual sequence is byte-identical to what git flow produces.

Edit `app/config/include.php` **and `Dockerfile`**, then commit both together. Use the
historical message forms:

| Situation | Commit message |
|---|---|
| Patch, code only | `Bump version to <VERSION>` |
| Patch + schema change | `Bump version to <VERSION> and raise UPGRADE_MIN_DATE` |
| `UPGRADE_MIN_DATE` alone | `Bump UPGRADE_MIN_DATE for <VERSION> schema change` |

The `Dockerfile` carries `ARG TEAMPASS_VERSION`, whose default must equal
`TP_VERSION.TP_VERSION_MINOR`. CI overrides it (`docker-publish.yml` passes
`TEAMPASS_VERSION=${{ github.ref_name }}`), so the default only serves local builds — but a
stale value mislabels every image built by hand, which is how it silently drifted six releases
behind. §3 compares the two and **fails** on a mismatch.

```bash
git add app/config/include.php Dockerfile
git commit -m "Bump version to <VERSION>"
```

`composer.json` carries a `version` field that has not been maintained since 3.2.0. That one is
genuinely not part of the procedure — leave it alone unless the user asks.

---

## 6. Regenerate the integrity checksums — always last

The application's `verifyFileHashes()` / `filesIntegrityCheck()` (`app/sources/admin.queries.php`)
read **`app/files_reference.txt`**, while the root `files_reference.txt` is the canonical
generated file. Both must be identical and both are committed.

Run this **after every other commit of the release**, otherwise the hashes are stale:

```bash
.claude/skills/prepare-release/scripts/regenerate_checksums.sh
git add files_reference.txt app/files_reference.txt
git commit -m "Regenerate file integrity checksums for <VERSION>"
```

The script regenerates the root file from `git ls-files` (format `<path> <md5>`, ~15 000
entries, self-excluded) and copies it over `app/files_reference.txt`.

> **The working tree must be clean.** A tracked file missing from disk is skipped, producing a
> reference file with holes. The classic case: the app's post-install cleanup renames
> `public/install/` to `public/install1/`, which makes 68 tracked installer files look deleted.
> The script refuses to run in that state — restore the files (`git checkout -- public/install`)
> before regenerating.

> **Why a script:** the pipeline is multi-line and gets mangled when passed inline to
> `bash -c` (syntax error on the `done \` continuation). Never inline it.

> **Known benign quirk:** the root file lists `app/files_reference.txt` with its *pre-copy*
> hash, so the admin integrity screen reports at most two "mismatch" lines for the two
> reference files themselves. Pre-existing and harmless — do not try to fix it by reordering.

---

## 7. Finish git flow — manual sequence

Run each git command on its own line; chained `&&` / backslash-continued commands get mangled
by the rtk hook.

**Before checking out `master`, check whether the range untracks anything that is still on
disk.** A commit that removes a path from the index (making it untracked and `.gitignore`d on
`develop`) leaves the files in place there — but `master` still tracks them, and git *silently
adopts* an untracked file whose content matches the target version. The merge then applies the
deletion and **wipes the files from disk**:

```bash
git diff --name-status --diff-filter=D "$BASE"..develop
```

For every `D` path still present on disk, note it now — the merge will delete it. Restore it
afterwards with `git archive <commit-before-the-deletion> <path>/ | tar -x`, which writes to
disk **without touching the index**, so a `.gitignore`d directory stays untracked.
`git checkout <commit> -- <path>` would re-stage the files and silently undo the untracking.
(Hit on 3.2.1.4: the `.claude/` directory, untracked earlier on `develop`, vanished mid-release.)

**Since 3.2.1.7 this fires on `app/vendor` every single time.** Commit `c018a28a2` untracked
**1940 files** of Composer dev dependencies, so the merge into `master` deletes them from disk.
Do *not* restore them with `git archive` — they are Composer's to manage, and the git copy is
the stale one. Run this after the merge instead:

```bash
rm -rf app/vendor/phpstan app/vendor/phpunit app/vendor/symfony/cache
composer install
git checkout -- app/vendor/composer/
```

The `rm -rf` is **not** optional, and it is the part nobody guesses. Those three packages keep
untracked *generated* files that git has no reason to delete — `phpstan/phpstan/turbo-ext/`
(28 native `.so` binaries), the generated Redis proxies under `symfony/cache/Traits/`, and the
three `Xdebug*` files that the committed vendor tree never contained. Their directory therefore
survives the deletion, and Composer — which only checks that a package directory *exists*, never
what is inside it — considers them installed and skips them. Symptom: `composer install` aborts
with `Could not scan for classes inside ".../symfony/cache/Traits/ValueWrapper.php" which does
not appear to be a file nor a folder`, leaving the autoloader ungenerated. Deleting a directory
that happens to be already gone costs nothing; missing one breaks the whole install.

The third line discards pure churn: `composer install` always rewrites `autoload_classmap.php`,
`autoload_real.php` and `autoload_static.php` (a randomly regenerated `apcuPrefix`, plus three
`Xdebug*` classmap entries absent from the committed tree). Never commit it.

Then re-verify the toolchain, which the deletion had disarmed:

```bash
php app/vendor/phpunit/phpunit/phpunit          # expect: OK (1318 tests, 38189 assertions)
php app/vendor/bin/phpstan analyse --memory-limit=2G
```

```bash
git checkout master
GIT_MERGE_AUTOEDIT=no git merge --no-ff release/<VERSION> -m "Merge branch 'release/<VERSION>'"
git tag -a <VERSION> -m "<VERSION>"
git checkout develop
git merge --no-ff <VERSION> -m "Merge tag '<VERSION>' into develop"
git branch -d release/<VERSION>
```

Verify the structure before pushing (remember: `rtk` hides merges):

```bash
git rev-list --merges --max-count=3 --format='%h %ci %s' master | grep -v '^commit'
git rev-list --merges --max-count=3 --format='%h %s' develop | grep -v '^commit'
git cat-file -t <VERSION>          # must print "tag" (annotated)
git show <VERSION>:app/config/include.php | grep TP_VERSION
```

**STOP — ask the user before pushing.** Every one of the three pushes below triggers the Docker
build workflow: `.github/workflows/docker-publish.yml` fires on `push` to `master` **and**
`develop`, on tags matching `*.*.*.*` / `v*.*.*`, on `release: published`, and on
`workflow_dispatch`. Each run publishes to Docker Hub and GHCR (`push:` is true for every event
except `pull_request`), so this is not a dry run.

```bash
git push origin master
git push origin develop
git push origin <VERSION>
```

---

## 8. Draft the GitHub release notes

Read `references/release-notes-template.md` for the full skeleton and the boilerplate footer,
then write the notes to a scratch file and review them with the user before publishing.

Writing rules distilled from the 3.2.1.x releases:

- **Opening paragraph** states what kind of release this is, what it fixes, and who should
  upgrade. If there is no schema change, say so explicitly and in bold, together with the
  `UPGRADE_MIN_DATE` consequence ("installations already running X are **not** sent back
  through the upgrade wizard").
- **One bullet per change, written for an administrator, not for a developer.** Each bullet
  opens with a bolded outcome, then explains the *cause* and the *observable* effect. Cite the
  issue/PR number and the contributor handle when there is one:
  `(PR [#5307](https://github.com/nilsteampassnet/TeamPass/pull/5307), @guerricv)`.
- **Sections are adaptive.** The usual set is `🔒 Security fixes`, `✨ New features`,
  `🛠️ Improvements`, `🐛 Bug fixes`, `⬆️ Upgrade notes`. Drop empty ones; add a themed one when
  a single subject dominates (3.2.1.1 had `🔐 Encryption hardening`).
- **`⬆️ Upgrade notes` is mandatory** whenever behaviour changes for an existing installation:
  schema/no-schema statement, defaults that shift existing counters, settings seeded on fresh
  installs only, anything an admin must re-check after upgrading, and the standing
  "Back up your database before upgrading, as always."
- Close with the compare link:
  `**Full Changelog**: [<BASE>...<VERSION>](https://github.com/nilsteampassnet/TeamPass/compare/<BASE>...<VERSION>)`
  followed by the constant boilerplate from the template.

**STOP — ask the user before publishing.**

```bash
gh release create <VERSION> \
  --repo nilsteampassnet/TeamPass \
  --title "<VERSION>" \
  --notes-file <scratch>/release-notes-<VERSION>.md
# add --prerelease for a .0 opening a new line
```

---

## 9. After publishing

- `docker-publish.yml` (push to `master`/`develop`, tags, release, manual dispatch) and
  `docker-image.yml` (release published) build and push the
  images. Check both runs: `gh run list --repo nilsteampassnet/TeamPass --limit 5`.
- Confirm the release is visible and correctly flagged:
  `gh release view <VERSION> --repo nilsteampassnet/TeamPass --json name,isPrerelease`.
- **Read the Trivy outcome.** `docker-publish.yml` scans the freshly built image and uploads the
  result as GitHub **code-scanning** alerts (`github/codeql-action/upload-sarif`), so that feed is
  where the image's CVEs land — it is *not* CodeQL, and no `paths-ignore` affects it. This is the
  only place the base-image work of §3 is actually confirmed:

  ```bash
  gh api 'repos/nilsteampassnet/TeamPass/code-scanning/alerts?per_page=100&state=open' \
    --jq 'group_by(.rule.security_severity_level)
          | map({severity: .[0].rule.security_severity_level, count: length})'
  ```

  Alerts located on `app/vendor/composer/installed.json` are Composer packages (fix by updating
  the dependency); those located on the image name itself are OS packages (fix by moving `FROM`,
  §3). A count that did not drop after a base-image bump means the published image was built
  from the old layers — check which commit the workflow ran on.
- The SourceForge download button in the notes points at
  `https://sourceforge.net/projects/communitypasswo/files/<VERSION>/<VERSION>%20source%20code.zip/download`
  — the archive must be uploaded there by the maintainer; the link 404s until then.

---

## Traps, in one place

| Trap | Consequence |
|---|---|
| Regenerating checksums before the last commit | `files_reference.txt` is stale ⇒ integrity check reports false mismatches |
| Forgetting `app/files_reference.txt` | The app reads that copy — the root file alone changes nothing at runtime |
| Regenerating with `public/install/` renamed to `install1` | 68 installer entries silently missing from the reference file (the script now refuses) |
| Bumping `UPGRADE_MIN_DATE` "by reflex" | Every installation is sent back through the upgrade wizard for a code-only release |
| Non-idempotent DDL in an `upgrade_run_*.php` | Scripts are **all replayed every time** ⇒ upgrade crashes on an installation already on that version |
| New setting seeded in the upgrade only | Fresh installs miss it (release gate check 8 warns) |
| New `CREATE TABLE` not registered in `install.js` | Fresh installs skip the table (check 9 warns) |
| `rtk` hiding merge commits | Release/merge structure looks wrong or missing — use `git rev-list --merges` |
| A path untracked on `develop` that `master` still tracks | `git checkout master` adopts the on-disk copy, the merge then **deletes it from disk** — pre-check with `--diff-filter=D`, restore with `git archive \| tar -x` (§7) |
| Multi-line checksum pipeline inlined into `bash -c` | Syntax error on `done \` — always use the script |
| Chained `&&` git commands | Mangled by the rtk hook — one command per line |
| Bumping `include.php` without `Dockerfile` | Locally built images carry the previous version (check 1 now fails) |
| Treating the base image as code-only | It ages on its own: an end-of-life Alpine makes every Trivy CVE unfixable until `FROM` moves (§3) |
| Changing `FROM` without running `docker-publish` | No Docker locally ⇒ a broken build is only discovered after the release is published |
| Running only `composer install` after the `master` merge | Repairs nothing for `phpstan`, `phpunit` and `symfony/cache`: untracked generated files keep their directory alive, so Composer believes they are installed — `rm -rf` those three first (§7) |
| Restoring `app/vendor` with `git archive` | Reinstates the *stale* dev dependencies and re-stages them, undoing the untracking — `composer install` is the only correct restore (§7) |
