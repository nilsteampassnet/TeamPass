const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { resolve } = require('node:path')
const { spawnSync } = require('node:child_process')
const { test } = require('node:test')

const root = resolve(__dirname, '../..')

// Run the entrypoint's real auto_upgrade() against the migration scripts of this
// checkout, with the database replaced by the recorded version and schema stamp.
function bootContainer (recordedVersion, recordedTimestamp) {
  const source = readFileSync(resolve(root, 'docker/docker-entrypoint.sh'), 'utf8').replace(/\r\n/g, '\n')
  const start = source.indexOf('read_db_version() {')
  const end = source.indexOf('# Function to show manual installation instructions', start)
  assert.ok(start >= 0 && end > start)
  const functions = source.slice(start, end).replaceAll('/var/www/html', '"$CHAIN_TEST_ROOT"')
  // The trace goes to fd 3: run_upgrade_step() captures the script's stdout and stderr.
  const script = `set -e
exec 3>&2
RECORDED="${recordedVersion}"
${functions}
php() {
  case "$1" in
    */read-db-version.php) printf '%s' "$RECORDED" ;;
    */read-db-upgrade-timestamp.php) printf '%s' "${recordedTimestamp}" ;;
    */write-db-version.php) RECORDED="$2" ;;
    */upgrade_run_*.php)
      _ran=\${1##*upgrade_run_}
      printf 'RAN:%s\\n' "\${_ran%.php}" >&3
      printf '[{"finish":"1","error":""}]\\n'
      ;;
    *) return 1 ;;
  esac
}
TP_VERSION=3.2.3
TP_UPGRADE_MIN_DATE=1792522689
auto_upgrade >/dev/null
printf 'RECORDED:%s\\n' "$RECORDED" >&2
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
    recorded: (result.stderr.match(/RECORDED:([\d.]*)/) || [])[1]
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
