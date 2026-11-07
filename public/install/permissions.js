/* TeamPass - GPL-3.0-or-later. Copyright 2009-2026 Teampass.net. */
(() => {
  'use strict'
  const element = (id) => document.getElementById(`setup-permissions-${id}`)
  const data = JSON.parse(element('data').textContent)
  const status = element('status')
  const refresh = element('refresh')
  const finalize = element('finalize')
  const acknowledge = element('ack')
  let review = null
  let busy = false
  let completed = false
  const reasons = {
    protected_writable: 'PHP can modify application code.',
    protected_web_owned: 'PHP owns application code and can restore write permission.',
    secret_web_owned: 'PHP owns the encryption key and can restore write permission.',
    runtime_not_readable: 'PHP cannot read or traverse this runtime path.',
    runtime_not_writable: 'PHP cannot write to this runtime path.',
    world_writable: 'Every system user can write to this path.',
    runtime_world_accessible: 'Other system users can access runtime data.',
    secret_not_readable: 'PHP cannot read the encryption key.',
    secret_world_accessible: 'Other system users can access the encryption key.',
    secret_group_writable: 'The encryption key is writable by its group.',
    secret_writable: 'PHP can modify or replace the encryption key.',
    protected_not_readable: 'PHP cannot read application code.',
    group_writable: 'The group can write to a protected path.',
    required_path_missing: 'A required runtime directory is missing.',
    inspection_failed: 'The permissions could not be inspected.',
    instance_data_world_accessible: 'Other system users can access legacy instance data.'
  }
  const updateButton = () => {
    finalize.disabled = busy || completed || !(review?.status === 'passed' || (review?.status === 'manual' && acknowledge.checked))
  }
  const show = (result) => {
    review = result
    status.className = `alert ${result.status === 'passed' ? 'alert-success' : 'alert-warning'}`
    status.textContent = result.status === 'passed' ? 'The permission audit passed. Finalize setup to check once more and record the result.'
      : result.status === 'manual' ? 'Automatic verification is incomplete. A manual review is required before finalization.'
        : `${result.issues ?? 'Unresolved'} permission findings remain. Correct them before finalizing setup.`
    element('manual').hidden = result.status !== 'manual'
    element('identity').textContent = result.identity?.web_user
      ? `PHP account: ${result.identity.web_user}; group: ${result.identity.web_group}. Review this identity before running commands.` : ''
    const commands = Array.isArray(result.commands) ? result.commands : []
    element('corrections').hidden = commands.length === 0
    element('no-plan').hidden = result.status !== 'issues' || commands.length > 0
    element('commands').textContent = commands.length ? ['(', 'set -e', ...commands, ')'].join('\n') : ''
    const findings = Array.isArray(result.findings) ? result.findings : []
    element('findings').hidden = findings.length === 0
    element('findings-text').textContent = findings.map((finding) => `${finding.scope || finding.path}: ${reasons[finding.reason] || 'Manual inspection required.'} (${finding.actual_mode}; expected ${finding.expected})`).join('\n')
    updateButton()
  }
  const errors = {
    SETUP_SESSION_REQUIRED: 'Your setup session is no longer valid. Return to the authorized setup flow.',
    SETUP_OPERATION_BUSY: 'Another setup or recovery operation is running. Wait for it to finish, then check again.',
    PERMISSIONS_REVIEW_REQUIRED: 'Permissions changed or findings remain. Correct the current findings and check again.',
    PERMISSIONS_MANUAL_ACK_REQUIRED: 'Confirm that you have completed the manual permission review.',
    PERMISSIONS_AUDIT_LOG_UNAVAILABLE: 'The review could not be recorded. Check PHP write access to storage/logs and try again.',
    PERMISSIONS_AUDIT_FAILED: 'The audit did not complete. Check server logs and request time limits, then try again.'
  }
  const request = async (action) => {
    if (busy || completed) return
    busy = true
    refresh.disabled = true
    updateButton()
    status.textContent = action === 'audit' ? 'Checking current permissions… This may take a few moments.' : 'Verifying permissions again and finalizing setup…'
    try {
      const body = new URLSearchParams({ flow: data.flow, action, acknowledge_manual: acknowledge.checked ? '1' : '0' })
      const response = await fetch('permissions.php', { method: 'POST', headers: { 'X-CSRF-Token': data.csrf }, body })
      const result = await response.json()
      if (result.review) show(result.review)
      if (!response.ok || !result.success) throw new Error(errors[result.error] || errors.PERMISSIONS_AUDIT_FAILED)
      if (result.finalized) {
        completed = true
        status.className = 'alert alert-success'
        status.textContent = result.review.status === 'manual' ? 'Manual review acknowledged and recorded. Remove the installer next.' : 'Permissions verified and recorded. Remove the installer next.'
        element('complete').hidden = false
        element('cleanup').textContent = result.cleanup || 'The installer path could not be validated. Remove or block public/install manually.'
        element('copy-cleanup').hidden = !result.cleanup
        element('corrections').hidden = true
        element('manual').hidden = true
        refresh.hidden = true
        finalize.hidden = true
      }
    } catch (error) {
      // A failed audit never leaves a previous successful result actionable.
      review = null
      status.className = 'alert alert-danger'
      status.textContent = error.message && Object.values(errors).includes(error.message) ? error.message : errors.PERMISSIONS_AUDIT_FAILED
    } finally {
      busy = false
      refresh.disabled = false
      updateButton()
    }
  }
  const copy = async (id, button) => {
    const text = element(id).textContent
    let copied = false
    if (navigator.clipboard?.writeText) {
      try { await navigator.clipboard.writeText(text); copied = true } catch { /* Try local fallback. */ }
    }
    if (!copied) {
      const textarea = document.createElement('textarea')
      textarea.value = text
      textarea.style.position = 'fixed'
      textarea.style.opacity = '0'
      document.body.appendChild(textarea)
      try {
        textarea.select()
        copied = document.execCommand('copy')
      } catch { /* Offer manual selection below. */ }
      finally { textarea.remove() }
    }
    button.textContent = copied ? 'Copied' : 'Select and copy the command manually'
  }
  refresh.addEventListener('click', () => { acknowledge.checked = false; request('audit') })
  finalize.addEventListener('click', () => request('finalize'))
  acknowledge.addEventListener('change', updateButton)
  element('copy').addEventListener('click', () => copy('commands', element('copy')))
  element('copy-cleanup').addEventListener('click', () => copy('cleanup', element('copy-cleanup')))
  request('audit')
})()
