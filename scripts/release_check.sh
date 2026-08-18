#!/usr/bin/env bash
#
# release_check.sh — Reproducible pre-release verification for TeamPass.
#
# Runs the same battery of checks used to validate a release:
#   1. Version constants
#   2. PHPStan (level from phpstan.neon)
#   3. PHPUnit unit tests
#   4. PHP syntax lint on every changed *.php file
#   5. Shell syntax lint on every changed *.sh file
#   6. Debug-leftover scan on the diff (var_dump / console.log / die / ...)
#   7. Language parity: new English keys must exist in French; code-referenced
#      keys must exist in English
#   8. DB seed parity: admin settings seeded in the installer vs. the upgrade
#      script for the current version
#   9. Installer table registration: every CREATE TABLE method in run.step5.php
#      must be registered as an action in install.js
#  10. Shipped autoloader integrity: every file required by autoload_files.php
#      must be tracked, or the release archive fatals on every request
#
# Usage:
#   scripts/release_check.sh [<base-ref>]
#
#   <base-ref>  Git ref of the previous release (default: latest tag before HEAD).
#
# Exit code: 0 if no blocking failure, 1 otherwise. Warnings never fail the run.

# This script relies on bash features (pipefail, BASH_SOURCE, arrays).
# Re-exec under bash when invoked via sh/dash (e.g. `sh release_check.sh`).
if [ -z "${BASH_VERSION:-}" ]; then
    exec bash "$0" "$@"
fi

set -uo pipefail

# --- Locate the repository root (this script lives in <root>/scripts) ----------
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# --- Resolve binaries ----------------------------------------------------------
PHP_BIN="$(command -v php || true)"
PHPSTAN="$ROOT/app/vendor/bin/phpstan"
PHPUNIT_PHAR="$ROOT/app/vendor/phpunit/phpunit/phpunit"

EN_LANG="app/includes/language/english.php"
FR_LANG="app/includes/language/french.php"
INSTALL_STEP="public/install/install-steps/run.step5.php"
INSTALL_JS="public/install/install-steps/install.js"

# --- Base ref (previous release) ----------------------------------------------
BASE_REF="${1:-}"
if [ -z "$BASE_REF" ]; then
    BASE_REF="$(git describe --tags --abbrev=0 HEAD^ 2>/dev/null || true)"
fi
if [ -z "$BASE_REF" ]; then
    echo "ERROR: could not determine a base ref. Pass it explicitly: scripts/release_check.sh <tag>"
    exit 1
fi

FAILURES=0
WARNINGS=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

pass() { printf '  \033[32m✓\033[0m %s\n' "$1"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$1"; WARNINGS=$((WARNINGS+1)); }
fail() { printf '  \033[31m✗\033[0m %s\n' "$1"; FAILURES=$((FAILURES+1)); }
hdr()  { printf '\n\033[1m%s\033[0m\n' "$1"; }

echo   "================================================================"
echo   " TeamPass release check — diff range: ${BASE_REF}..HEAD"
echo   "================================================================"

# Files changed in the range (used by several checks).
git diff --name-only "${BASE_REF}..HEAD" > "$TMP/changed.txt" 2>/dev/null || true

# 1. ---------------------------------------------------------------------------
hdr "1. Version constants"
VER="$(grep -oE "define\('TP_VERSION', *'[^']+'\)" app/config/include.php | grep -oE "'[0-9.]+'" | tr -d "'")"
VER_MINOR="$(grep -oE "define\('TP_VERSION_MINOR', *'[^']+'\)" app/config/include.php | grep -oE "'[0-9]+'" | tr -d "'")"
if [ -n "$VER" ] && [ -n "$VER_MINOR" ]; then
    pass "TP_VERSION=$VER  TP_VERSION_MINOR=$VER_MINOR  => release $VER.$VER_MINOR"
else
    fail "Could not read TP_VERSION / TP_VERSION_MINOR from app/config/include.php"
fi

# The Dockerfile default only serves local builds — CI passes the release tag — but a stale
# value mislabels every locally built image, so it is kept equal to the release version and
# bumped in the same commit as the constants above.
DOCKER_VER="$(grep -oE '^ARG TEAMPASS_VERSION=[0-9.]+' Dockerfile 2>/dev/null | cut -d= -f2)"
if [ -z "$DOCKER_VER" ]; then
    warn "Could not read ARG TEAMPASS_VERSION from Dockerfile"
elif [ "$DOCKER_VER" = "$VER.$VER_MINOR" ]; then
    pass "Dockerfile ARG TEAMPASS_VERSION=$DOCKER_VER matches the release version"
else
    fail "Dockerfile ARG TEAMPASS_VERSION=$DOCKER_VER but the release is $VER.$VER_MINOR"
fi

UPGRADE_FILE="public/install/upgrade_run_${VER}.php"

# 2. ---------------------------------------------------------------------------
hdr "2. PHPStan"
if [ -x "$PHPSTAN" ] || [ -f "$PHPSTAN" ]; then
    if "$PHP_BIN" "$PHPSTAN" analyse --no-progress --memory-limit=1G --error-format=raw > "$TMP/phpstan.log" 2>&1; then
        pass "PHPStan: no errors"
    else
        fail "PHPStan reported errors (see below)"; sed 's/^/      /' "$TMP/phpstan.log" | head -30
    fi
else
    warn "PHPStan not found at $PHPSTAN — skipped"
fi

# 3. ---------------------------------------------------------------------------
hdr "3. PHPUnit"
if [ -f "$PHPUNIT_PHAR" ]; then
    if "$PHP_BIN" "$PHPUNIT_PHAR" --no-coverage > "$TMP/phpunit.log" 2>&1; then
        pass "$(grep -E 'OK \(|Tests:' "$TMP/phpunit.log" | tail -1)"
    else
        fail "PHPUnit failed (tail below)"; tail -25 "$TMP/phpunit.log" | sed 's/^/      /'
    fi
else
    warn "PHPUnit not found at $PHPUNIT_PHAR — skipped"
fi

# 4. ---------------------------------------------------------------------------
hdr "4. PHP syntax lint (changed files)"
LINT_ERR=0
while IFS= read -r f; do
    [ -f "$f" ] || continue
    case "$f" in *.php) ;; *) continue ;; esac
    if ! "$PHP_BIN" -l "$f" > "$TMP/lint.log" 2>&1; then
        fail "Syntax error in $f"; sed 's/^/      /' "$TMP/lint.log"; LINT_ERR=1
    fi
done < "$TMP/changed.txt"
[ "$LINT_ERR" -eq 0 ] && pass "All changed PHP files parse cleanly"

# 5. ---------------------------------------------------------------------------
hdr "5. Shell syntax lint (changed files)"
SH_ERR=0
while IFS= read -r f; do
    [ -f "$f" ] || continue
    case "$f" in *.sh) ;; *) continue ;; esac
    if ! bash -n "$f" 2> "$TMP/sh.log"; then
        fail "Shell syntax error in $f"; sed 's/^/      /' "$TMP/sh.log"; SH_ERR=1
    fi
done < "$TMP/changed.txt"
[ "$SH_ERR" -eq 0 ] && pass "All changed shell scripts parse cleanly"

# 6. ---------------------------------------------------------------------------
hdr "6. Debug-leftover scan (added lines)"
LEFT="$(git diff "${BASE_REF}..HEAD" -- '*.php' '*.js' '*.js.php' \
        | grep -E '^\+' | grep -vE '^\+\+\+' \
        | grep -inE 'var_dump|print_r\(|\bdie\(|\bdd\(|console\.(log|debug)|error_log\(|//\s*(TODO|FIXME|XXX|HACK)' \
        || true)"
if [ -z "$LEFT" ]; then
    pass "No debug leftovers introduced"
else
    warn "Potential debug leftovers (review manually):"; echo "$LEFT" | head -20 | sed 's/^/      /'
fi

# 7. ---------------------------------------------------------------------------
hdr "7. Language parity"
extract_keys() { grep -oE "^[[:space:]]*'[a-zA-Z0-9_]+' =>" "$1" 2>/dev/null | grep -oE "'[a-zA-Z0-9_]+'" | tr -d "'" | sort -u; }
extract_keys "$EN_LANG" > "$TMP/en.txt"
extract_keys "$FR_LANG" > "$TMP/fr.txt"
git show "${BASE_REF}:${EN_LANG}" 2>/dev/null | grep -oE "^[[:space:]]*'[a-zA-Z0-9_]+' =>" | grep -oE "'[a-zA-Z0-9_]+'" | tr -d "'" | sort -u > "$TMP/en_old.txt"
comm -13 "$TMP/en_old.txt" "$TMP/en.txt" > "$TMP/en_new.txt"   # keys added this release
MISSING_FR="$(comm -23 "$TMP/en_new.txt" "$TMP/fr.txt")"        # new EN keys not in FR
if [ -z "$MISSING_FR" ]; then
    pass "All $(wc -l < "$TMP/en_new.txt" | tr -d ' ') new English keys are present in French"
else
    fail "New English keys missing from French:"; echo "$MISSING_FR" | sed 's/^/      /'
fi
# Keys referenced by code but absent from English (would render as raw key).
git diff "${BASE_REF}..HEAD" -- '*.php' '*.js.php' '*.js' | grep -E '^\+' \
    | grep -oE "(\\\$lang->get|lang\.get|langGet)\([[:space:]]*'[a-zA-Z0-9_]+'" \
    | grep -oE "'[a-zA-Z0-9_]+'" | tr -d "'" | sort -u > "$TMP/ref.txt"
MISSING_EN="$(comm -23 "$TMP/ref.txt" "$TMP/en.txt")"
if [ -z "$MISSING_EN" ]; then
    pass "All newly-referenced language keys exist in English"
else
    warn "Newly-referenced keys absent from English (verify they are not variables):"; echo "$MISSING_EN" | sed 's/^/      /'
fi

# 8. ---------------------------------------------------------------------------
hdr "8. DB admin-setting seed parity (install vs upgrade ${VER})"
if [ -f "$UPGRADE_FILE" ] && [ -f "$INSTALL_STEP" ]; then
    # Settings seeded by the installer: array('admin', 'key', ...)
    grep -oE "array\('admin', *'[a-zA-Z0-9_]+'" "$INSTALL_STEP" | grep -oE "'[a-zA-Z0-9_]+'" | grep -v "'admin'" | tr -d "'" | sort -u > "$TMP/inst.txt"
    # Settings seeded by the upgrade: INSERT [IGNORE] ... ('admin', 'key', ...)
    grep -oE "\('admin', *'[a-zA-Z0-9_]+'" "$UPGRADE_FILE" | grep -oE "'[a-zA-Z0-9_]+'" | grep -v "'admin'" | tr -d "'" | sort -u > "$TMP/upg.txt"
    UPG_ONLY="$(comm -13 "$TMP/inst.txt" "$TMP/upg.txt")"   # in upgrade, not in install
    if [ -z "$UPG_ONLY" ]; then
        pass "Every admin setting added by the upgrade is also seeded by the installer"
    else
        warn "Admin settings in upgrade_run_${VER}.php but NOT in installer (fresh installs would miss them):"
        echo "$UPG_ONLY" | sed 's/^/      /'
    fi
else
    warn "Could not compare ($UPGRADE_FILE or $INSTALL_STEP missing)"
fi

# 9. ---------------------------------------------------------------------------
hdr "9. Installer table registration (run.step5.php vs install.js)"
if [ -f "$INSTALL_STEP" ] && [ -f "$INSTALL_JS" ]; then
    # Method names whose body contains CREATE TABLE.
    awk '/private function [a-zA-Z0-9_]+\(/{name=$0; sub(/.*private function /,"",name); sub(/\(.*/,"",name)} /CREATE TABLE/{if(name!=""){print name; name=""}}' "$INSTALL_STEP" | sort -u > "$TMP/methods.txt"
    UNREG=""
    while IFS= read -r m; do
        [ -z "$m" ] && continue
        if ! grep -qE "action: *'$m'" "$INSTALL_JS"; then
            UNREG="$UNREG $m"
        fi
    done < "$TMP/methods.txt"
    if [ -z "$UNREG" ]; then
        pass "All CREATE TABLE methods are registered as install.js actions"
    else
        warn "Table methods not registered in install.js (fresh install would skip them):$UNREG"
    fi
else
    warn "Could not check installer table registration (files missing)"
fi

# 10. --------------------------------------------------------------------------
hdr "10. Shipped autoloader integrity (app/vendor/composer)"
AUTOLOAD_FILES="app/vendor/composer/autoload_files.php"
INSTALLED_JSON="app/vendor/composer/installed.json"
if [ -f "$AUTOLOAD_FILES" ]; then
    # Every entry of autoload_files.php is `require`d unconditionally by
    # autoload_real.php, so a file missing from the release archive is a fatal
    # error on every request. The archive is built from the index, so membership
    # is decided by `git ls-files`, never by the working tree: an untracked dev
    # dependency is present locally and absent from the archive.
    git ls-files app/vendor > "$TMP/tracked_vendor.txt"
    sed -n "s/.*\$vendorDir \. '\/\([^']*\)'.*/app\/vendor\/\1/p" "$AUTOLOAD_FILES" > "$TMP/autoload_needs.txt"
    sed -n "s/.*\$baseDir \. '\/\([^']*\)'.*/\1/p" "$AUTOLOAD_FILES" >> "$TMP/autoload_needs.txt"
    MISSING_FROM_ARCHIVE=""
    while IFS= read -r f; do
        [ -z "$f" ] && continue
        grep -qxF "$f" "$TMP/tracked_vendor.txt" && continue
        git ls-files --error-unmatch "$f" >/dev/null 2>&1 && continue
        MISSING_FROM_ARCHIVE="$MISSING_FROM_ARCHIVE $f"
    done < "$TMP/autoload_needs.txt"
    if [ -z "$MISSING_FROM_ARCHIVE" ]; then
        pass "Every file required by autoload_files.php is tracked (archive boots)"
    else
        fail "autoload_files.php requires files absent from the release archive — it fatals on every request:"
        for f in $MISSING_FROM_ARCHIVE; do echo "      $f"; done
        echo "      Fix: composer install --no-dev, then commit app/vendor/composer/"
    fi
else
    warn "Could not check the shipped autoloader ($AUTOLOAD_FILES missing)"
fi
if [ -f "$INSTALLED_JSON" ]; then
    if grep -qE '"dev": *true' "$INSTALLED_JSON"; then
        warn "installed.json advertises the dev dependencies (\"dev\": true) — scanners report CVEs for packages that are not shipped. Fix: composer install --no-dev"
    else
        pass "installed.json describes a production install"
    fi
fi

# --- Summary -------------------------------------------------------------------
echo   ""
echo   "================================================================"
printf " Result: \033[31m%d failure(s)\033[0m, \033[33m%d warning(s)\033[0m\n" "$FAILURES" "$WARNINGS"
echo   "================================================================"
[ "$FAILURES" -eq 0 ]
