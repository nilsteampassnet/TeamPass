const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { resolve } = require('node:path')
const { spawnSync } = require('node:child_process')
const { test } = require('node:test')

test('Docker selects one recordable 3.2.2 migration when upgrading from 3.2.1', () => {
  const root = resolve(__dirname, '../..')
  const source = readFileSync(resolve(root, 'docker/docker-entrypoint.sh'), 'utf8').replace(/\r\n/g, '\n')
  const start = source.indexOf('version_to_number() {')
  const end = source.indexOf('auto_upgrade() {', start)
  assert.ok(start >= 0 && end > start)
  // Keep the actual discovery and step runner, redirecting only the container root.
  const functions = source.slice(start, end).replaceAll('/var/www/html', '"$RENEWAL_TEST_ROOT"')
  const script = `set -e
${functions}
# Replace PHP/MySQL transport. An unrecordable four-component version must fail.
php() {
  case "$1" in
    */write-db-version.php)
      [[ "$2" =~ ^[0-9]+(\\.[0-9]+){1,2}$ ]] || return 1
      printf 'RECORDED:%s\\n' "$2"
      ;;
    */upgrade_run_*.php) printf '[{"finish":"1","error":""}]\\n' ;;
    *) return 1 ;;
  esac
}
upgrade_chain
printf 'STEPS\\n'
for step in $(upgrade_chain); do
  number=$(version_to_number "$step")
  if [ "$number" -gt 3002001 ] && [ "$number" -le 3002002 ]; then
    run_upgrade_step "$step"
  fi
done
`
  const bash = process.platform === 'win32' ? 'C:/Program Files/Git/bin/bash.exe' : 'bash'
  const result = spawnSync(bash, ['--noprofile', '--norc', '-s'], {
    input: script, encoding: 'utf8', timeout: 20000,
    env: { ...process.env, RENEWAL_TEST_ROOT: root.replaceAll('\\', '/') }
  })
  assert.ifError(result.error)
  assert.equal(result.status, 0, result.stderr + result.stdout)
  const [chain, execution] = result.stdout.split('STEPS\n')
  const versions = chain.trim().split('\n')
  assert.ok(versions.includes('3.2.2'))
  versions.forEach(version => assert.match(version, /^\d+\.\d+\.\d+$/))
  assert.deepEqual(execution.match(/RECORDED:[\d.]+/g), ['RECORDED:3.2.2'])
  // Also verify the schema-floor replay path: the same script owns both schema changes.
  const migration = readFileSync(resolve(root, 'public/install/upgrade_run_3.2.2.php'), 'utf8')
  assert.match(migration, /addColumnIfNotExist\(prefixTable\('items'\), 'renewal_period'/)
  assert.match(migration, /checkIndexExist\(prefixTable\('lapr_endpoints'\), 'idx_ssh_credential_source'/)
})
