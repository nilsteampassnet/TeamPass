# Contributing to Teampass

Thanks for taking the time to contribute. Whether you are fixing a typo, translating a string,
reporting a bug or building a feature, it is welcome.

By participating you agree to abide by the [Code of Conduct](../CODE_OF_CONDUCT.md).

## Contents

- [Ways to contribute](#ways-to-contribute)
- [Reporting a security vulnerability](#reporting-a-security-vulnerability)
- [Development setup](#development-setup)
- [Running the checks](#running-the-checks)
- [Coding standards](#coding-standards)
- [Branches and pull requests](#branches-and-pull-requests)
- [Commit messages](#commit-messages)

---

## Ways to contribute

| | |
|---|---|
| 🐛 **Report a bug** | [Open an issue](https://github.com/nilsteampassnet/TeamPass/issues/new/choose) with steps to reproduce and the diagnostic report from **Admin → Bug icon (bottom left)** |
| 💡 **Suggest a feature** | [Open a feature request](https://github.com/nilsteampassnet/TeamPass/issues/new/choose), or float the idea first in [Discussions](https://github.com/nilsteampassnet/TeamPass/discussions) |
| 🌍 **Translate** | Join the [POEditor project](https://poeditor.com/join/project?hash=0vptzClQrM) — no code required |
| 📖 **Improve the docs** | The documentation site lives in `docs/` (docsify); edit the Markdown and open a PR |
| 💬 **Help other users** | Answer questions in [Discussions](https://github.com/nilsteampassnet/TeamPass/discussions) |
| 💻 **Write code** | Read on |

New to open source? [This guide](https://www.freecodecamp.org/news/how-to-make-your-first-pull-request-on-github-3/)
walks through your first pull request.

---

## Reporting a security vulnerability

**Do not open a public issue for a security problem.** Use
[GitHub Security Advisories](https://github.com/nilsteampassnet/TeamPass/security/advisories/new)
— see [SECURITY.md](../SECURITY.md) for the full policy.

---

## Development setup

Requirements: PHP 8.2+, MySQL 5.7+ / MariaDB 10.7+, Composer, and the extensions listed in the
[README](../README.md#requirements).

```bash
git clone https://github.com/nilsteampassnet/TeamPass.git
cd TeamPass
composer install
```

The web root is `public/`; the application code lives in `app/`. Install through
`/install/install.php`.

### Setting up the dev toolchain

`composer.json` sets `vendor-dir` to `app/vendor`, so Composer binaries live in `app/vendor/bin/`
— **not** `vendor/bin/`.

**Install PHPUnit as a standalone phar.** Do not use `app/vendor/phpunit/phpunit/phpunit`: it
resolves its classes through `app/vendor/composer/`, which is committed in its **production**
form and maps no dev package. The symptom is `Class "PHPUnit\TextUI\Application" not found`
with the package sitting right there, and `composer dump-autoload` does not fix it. The phar
carries its own dependencies and is immune — the same reason `app/vendor/bin/phpstan` works
(it is a phar too).

```bash
mkdir -p _tools
curl -sL -o _tools/phpunit.phar    https://phar.phpunit.de/phpunit-10.5.64.phar
curl -sL -o /tmp/phpunit.phar.asc  https://phar.phpunit.de/phpunit-10.5.64.phar.asc
gpg --keyserver hkps://keys.openpgp.org \
    --recv-keys D8406D0D82947747293778314AA394086372C20A         # Sebastian Bergmann
gpg --verify /tmp/phpunit.phar.asc _tools/phpunit.phar           # expect: Good signature
chmod +x _tools/phpunit.phar
```

`_tools/` is gitignored, so the phar survives every checkout and merge. Keep its version in step
with `.github/workflows/quality.yml`, which resolves `phpunit/phpunit: ^10.5` from the lock file.

**The other dev tools still come from Composer**, and they carry a trap. The dev dependencies are
untracked, so any checkout or merge deletes them while leaving generated residue behind; because
their directories still exist, Composer believes they are installed and skips them.

```bash
rm -rf app/vendor/phpstan app/vendor/phpunit app/vendor/symfony/cache
composer install
git checkout -- app/vendor/composer/                    # restores the production autoloader
```

The `rm -rf` is not optional — without it `composer install` repairs nothing for those three
packages.

> **Always run `git checkout -- app/vendor/composer/` before committing.** `composer install`
> rewrites it into its *dev* form, and shipping that autoloader fatals every installation. The
> `Committed autoloader is production-only` CI job rejects a pull request that carries it.

---

## Running the checks

```bash
php _tools/phpunit.phar                                 # unit tests
php app/vendor/bin/phpstan analyse --memory-limit=2G    # static analysis, level 4
php app/vendor/bin/composer-license-checker             # dependency license compliance
```

A full PHPStan run takes several minutes. **All code must pass PHPStan level 4.**

### Manual testing checklist

Encryption and permissions are easy to break in ways tests do not catch. Before opening a PR,
exercise your change against:

- multiple user roles — administrator, manager, standard, read-only
- personal folders enabled and disabled
- the audit log (entries actually created?)
- LDAP / OAuth2, if you touched authentication
- the installer and the upgrade path, if you touched the schema

---

## Coding standards

### PHP

- `declare(strict_types=1)` in every new file
- Must pass **PHPStan level 4**
- Compatible with **PHP 8.2**
- Custom classes use the `TeampassClasses\*` namespace (PSR-4)
- Constants belong in `app/config/include.php`
- **Always use parameterized queries** — MeekroDB placeholders, never string concatenation:
  ```php
  DB::query('SELECT * FROM %l WHERE id=%i', 'teampass_items', $itemId);  // GOOD
  DB::query("SELECT * FROM teampass_items WHERE id=" . $itemId);         // NEVER
  ```
- Never use `exec()` / `shell_exec()` / `system()` with user input
- Use `hash_equals()` for secret comparisons, `realpath()` to validate file paths
- Never commit `includes/config/settings.php` or `TEAMPASS_SECRETS/SECUREFILE`

### JavaScript

Per `.eslintrc`: single quotes, no semicolons, 2-space indent, `const` over `let`, arrow
functions, ES6.

### Two rules that are easy to miss

1. **Every new `app/sources/*.queries.php` needs a matching proxy shim in `public/sources/`.**
   Without it the POST never reaches a real handler and is rejected with a misleading
   `403 Access Forbidden by CSRFProtector`.

2. **`teampassclasses` packages exist in two copies** — `app/includes/libraries/teampassclasses/`
   and `app/vendor/teampassclasses/`. Only the `vendor/` copy is autoloaded, so editing the other
   one alone produces a change with zero runtime effect. **Edit both.** Sentinel tests will fail
   otherwise.

### Folder tree

Always go through the `NestedTree` class. Never update `nleft` / `nright` / `nlevel` manually.

---

## Branches and pull requests

- `master` — stable, released code
- `develop` — integration branch, **target your PRs here** unless told otherwise
- When working on a specific GitHub issue, name your branch `pr-XXXX` where `XXXX` is the issue ID

Before opening a PR:

- [ ] PHPStan level 4 passes
- [ ] The test suite passes
- [ ] Every public function has a docblock
- [ ] Variable names and comments are in English
- [ ] No `var_dump()` or `console.log()` left behind
- [ ] Install and upgrade paths considered, if the schema changed
- [ ] `app/vendor/composer/` restored to its production form

Keep changes minimal and in the existing style. Large refactors are unlikely to be merged — open
a discussion first.

---

## Commit messages

- Written in **English**
- Simple, concise sentences describing what the commit does

```
Fix folder counter after a personal item is moved to a shared folder
```

---

Questions? Ask in [Discussions](https://github.com/nilsteampassnet/TeamPass/discussions).
