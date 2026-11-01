const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { resolve } = require('node:path')
const { spawnSync } = require('node:child_process')
const { test } = require('node:test')

const root = resolve(__dirname, '../..')

// Run the entrypoint's real auto_upgrade() against the migration scripts of this
// checkout, with the database replaced by the recorded version, the complete release
// that recorded it and the schema stamp. The fake helper mirrors write-db-version.php:
// reaching the image version stamps the image release. The manifest lists the 3.2.3
// data operations; failingOperation makes one of them report an error.
function bootContainer (recordedVersion, recordedTimestamp, recordedFull = '', failingOperation = '') {
  const source = readFileSync(resolve(root, 'docker/docker-entrypoint.sh'), 'utf8').replace(/\r\n/g, '\n')
  const start = source.indexOf('read_db_version() {')
  const end = source.indexOf('# Function to show manual installation instructions', start)
  assert.ok(start >= 0 && end > start)
  const functions = source.slice(start, end).replaceAll('/var/www/html', '"$CHAIN_TEST_ROOT"')
  // The trace goes to fd 3: run_upgrade_step() captures the script's stdout and stderr.
  const script = `set -e
exec 3>&2
RECORDED="${recordedVersion}"
RECORDED_FULL="${recordedFull}"
FAILING_OPERATION="${failingOperation}"
${functions}
php() {
  case "$1" in
    */read-db-version.php)
      if [ "$2" = "--full" ]; then printf '%s' "$RECORDED_FULL"; else printf '%s' "$RECORDED"; fi
      ;;
    */read-db-upgrade-timestamp.php) printf '%s' "${recordedTimestamp}" ;;
    */write-db-version.php)
      printf 'REC:%s\\n' "$2" >&3
      RECORDED="$2"
      if [ "$2" = "$TP_VERSION" ]; then RECORDED_FULL="$TEAMPASS_VERSION"; fi
      ;;
    */upgrade_run_*.php)
      _ran=\${1##*upgrade_run_}
      printf 'RAN:%s\\n' "\${_ran%.php}" >&3
      printf '[{"finish":"1","error":""}]\\n'
      ;;
    */list-upgrade-operations.php)
      if [ "$2" = "3.2.3" ]; then
        printf 'sanitize_legacy_fa_icons\\nsanitize_directory_user_fields\\nsanitize_imported_folder_titles\\n'
      fi
      ;;
    */upgrade_operations.php)
      printf 'OP:%s\\n' "$2" >&3
      if [ "$2" = "$FAILING_OPERATION" ]; then
        printf '[{"finish":"1", "next":"", "error":"test failure"}]\\n'
      else
        printf '[{"finish":"1" , "next":"", "error":"", "total":"0"}]\\n'
      fi
      ;;
    *) return 1 ;;
  esac
}
TP_VERSION=3.2.3
TEAMPASS_VERSION=3.2.3.2
TP_UPGRADE_MIN_DATE=1792522689
auto_upgrade
printf 'RECORDED:%s\\n' "$RECORDED" >&2
printf 'RECORDED_FULL:%s\\n' "$RECORDED_FULL" >&2
`
  const bash = process.platform === 'win32' ? 'C:/Program Files/Git/bin/bash.exe' : 'bash'
  const result = spawnSync(bash, ['--noprofile', '--norc', '-s'], {
    input: script, encoding: 'utf8', timeout: 20000,
    env: { ...process.env, CHAIN_TEST_ROOT: root.replaceAll('\\', '/') }
  })
  assert.ifError(result.error)
  assert.equal(result.status, 0, result.stderr + result.stdout)

  return {
    ran: (result.stderr.match(/RAN:[\d.]+/g) || []).map(line => line.slice(4)),
    trace: result.stderr.match(/^(RAN|OP|REC):[\w.]+$/gm) || [],
    recorded: (result.stderr.match(/RECORDED:([\d.]*)/) || [])[1],
    recordedFull: (result.stderr.match(/RECORDED_FULL:([\d.]*)/) || [])[1],
    log: result.stdout
  }
}

test('Docker replays the recorded version step when moving to a newer TP_VERSION', () => {
  // 3.2.2.3 recorded "3.2.2" while its schema lacked what 3.2.2.4-3.2.2.8 appended to
  // upgrade_run_3.2.2.php: the 3.2.3 image must replay that script, not skip it.
  const boot = bootContainer('3.2.2', '1788511813')
  assert.deepEqual(boot.ran, ['3.2.2', '3.2.3'])
  assert.equal(boot.recorded, '3.2.3')
})

test('Docker never replays steps older than the recorded version', () => {
  const boot = bootContainer('3.2.1', '1788511813')
  assert.deepEqual(boot.ran, ['3.2.1', '3.2.2', '3.2.3'])
})

test('Docker runs nothing on an up-to-date database', () => {
  const boot = bootContainer('3.2.3', '1792658752')
  assert.deepEqual(boot.ran, [])
  assert.equal(boot.recorded, '3.2.3')
})

test('Docker replays the image step when only the schema floor moved', () => {
  const boot = bootContainer('3.2.3', '1788511813')
  assert.deepEqual(boot.ran, ['3.2.3'])
})

test('Docker logs name the complete releases on both sides of an upgrade', () => {
  const boot = bootContainer('3.2.2', '1788511813', '3.2.2.3')
  assert.match(boot.log, /Upgrading database from 3\.2\.2\.3 to 3\.2\.3\.2\.\.\./)
  assert.match(boot.log, /Database upgraded to 3\.2\.3\.2 \(2 step\(s\) applied\)/)
  assert.equal(boot.recordedFull, '3.2.3.2')
})

test('Docker logs the release that recorded an up-to-date database', () => {
  assert.match(bootContainer('3.2.3', '1792658752', '3.2.3.2').log, /Database is up to date \(3\.2\.3\.2\)/)
  // Recorded before teampass_version_full existed: only the short form is known.
  assert.match(bootContainer('3.2.3', '1792658752').log, /Database is up to date \(3\.2\.3\)/)
})

test('Docker never names a release that does not belong to the recorded version', () => {
  // A chain stopped at 3.2.2 keeps the release stamped by the previous complete run.
  const boot = bootContainer('3.2.2', '1788511813', '3.2.1.7')
  assert.match(boot.log, /Upgrading database from 3\.2\.2 to 3\.2\.3\.2\.\.\./)
})

test('Docker applies the data operations of a step before recording its version', () => {
  const boot = bootContainer('3.2.2', '1788511813')
  assert.deepEqual(boot.trace, [
    'RAN:3.2.2', 'REC:3.2.2',
    'RAN:3.2.3',
    'OP:sanitize_legacy_fa_icons', 'OP:sanitize_directory_user_fields', 'OP:sanitize_imported_folder_titles',
    'REC:3.2.3'
  ])
})

test('Docker replays the data operations with the image step', () => {
  const boot = bootContainer('3.2.3', '1788511813')
  assert.equal(boot.trace.filter(line => line.startsWith('OP:')).length, 3)
  assert.deepEqual(bootContainer('3.2.3', '1792658752').trace, [])
})

test('Docker keeps the previous version when a data operation fails', () => {
  // Recording 3.2.3 anyway would make the next boot skip the operation for good.
  const boot = bootContainer('3.2.2', '1788511813', '', 'sanitize_directory_user_fields')
  assert.deepEqual(boot.trace.slice(-2), ['OP:sanitize_legacy_fa_icons', 'OP:sanitize_directory_user_fields'])
  assert.equal(boot.recorded, '3.2.2')
  assert.match(boot.log, /Data operation sanitize_directory_user_fields failed: test failure/)
  assert.match(boot.log, /Upgrade stopped at 3\.2\.3/)
})
