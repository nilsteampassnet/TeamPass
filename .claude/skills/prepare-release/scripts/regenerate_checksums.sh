#!/usr/bin/env bash
#
# regenerate_checksums.sh — Regenerate the TeamPass file-integrity reference files.
#
# Produces the root `files_reference.txt` from the git index (format: "<path> <md5>",
# one entry per tracked file, the file itself excluded), then copies it over
# `app/files_reference.txt` — the copy the application actually reads
# (`verifyFileHashes()` / `filesIntegrityCheck()` in app/sources/admin.queries.php).
#
# Run it LAST, after every other commit of the release, otherwise the hashes are stale.
#
# Usage:  .claude/skills/prepare-release/scripts/regenerate_checksums.sh
#
# This exists as a script on purpose: the underlying pipeline is multi-line and gets
# mangled when passed inline to `bash -c` (syntax error on the `done \` continuation).

if [ -z "${BASH_VERSION:-}" ]; then
    exec bash "$0" "$@"
fi

set -uo pipefail

ROOT="$(git rev-parse --show-toplevel)" || {
    echo "ERROR: not inside a git repository." >&2
    exit 1
}
cd "$ROOT" || exit 1

OUT="files_reference.txt"
APP_OUT="app/files_reference.txt"

# A tracked file that is missing from the working tree is skipped silently by the loop
# below, which would produce a reference file with holes (e.g. `public/install/` renamed to
# `install1` by the post-install cleanup drops 68 entries). Refuse to run in that state.
MISSING="$(git status --porcelain | grep -c '^.D ' || true)"
if [ "${MISSING:-0}" -gt 0 ] && [ "${1:-}" != "--force" ]; then
    echo "ERROR: $MISSING tracked file(s) are deleted from the working tree." >&2
    echo "       The reference file would be generated with holes. Restore them first:" >&2
    git status --porcelain | grep '^.D ' | head -5 | sed 's/^/         /' >&2
    echo "       (or re-run with --force if the deletions are intentional)" >&2
    exit 1
fi

TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

git ls-files --cached -z \
| grep -zv '^files_reference\.txt$' \
| while IFS= read -r -d $'\0' f; do
    { [ -f "$f" ] && md5sum "$f"; } || true
done \
| awk '{print $2 " " $1}' > "$TMP"

if [ ! -s "$TMP" ]; then
    echo "ERROR: no checksum produced — aborting without touching $OUT." >&2
    exit 1
fi

mv "$TMP" "$OUT"
cp "$OUT" "$APP_OUT"

printf 'Wrote %s entries to %s and copied it to %s\n' \
    "$(wc -l < "$OUT" | tr -d ' ')" "$OUT" "$APP_OUT"
printf 'Now commit both files: git add %s %s\n' "$OUT" "$APP_OUT"
