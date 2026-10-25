/* TeamPass - GPL-3.0-or-later. Copyright 2009-2026 Teampass.net. */
'use strict'
;(() => {
  const data = JSON.parse(document.getElementById('recovery-data').textContent)
  const message = document.getElementById('recovery-message')
  const copyButtons = Array.from(document.querySelectorAll('[data-recovery-command]'), (command) => {
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'btn btn-sm btn-outline-secondary align-self-start mb-2'
    button.setAttribute('aria-controls', command.id)
    button.setAttribute('aria-live', 'polite')
    command.before(button)
    button.addEventListener('click', async () => {
      const text = command.textContent
      let copied = false
      if (navigator.clipboard?.writeText) {
        try { await navigator.clipboard.writeText(text); copied = true } catch { /* Try the local fallback. */ }
      }
      if (!copied) {
        const textarea = document.createElement('textarea')
        textarea.value = text
        textarea.setAttribute('readonly', '')
        textarea.className = 'recovery-copy-buffer'
        document.body.appendChild(textarea)
        try { textarea.select(); copied = document.execCommand('copy') } catch { /* Offer manual selection below. */ }
        finally { textarea.remove(); button.focus() }
      }
      button.textContent = copied ? 'Copied' : 'Copy unavailable — select the command and press Ctrl+C'
    })
    return { button, command }
  })
  const syncCopyButtons = () => copyButtons.forEach(({ button, command }) => {
    button.hidden = command.hidden || !command.textContent.trim() || command.dataset.copyDisabled === 'true'
    button.textContent = command.dataset.copyLabel || (command.textContent.includes('\n') ? 'Copy commands' : 'Copy command')
  })
  const showError = (text) => {
    message.className = 'alert alert-danger mt-3'
    message.textContent = text
    message.focus()
    message.scrollIntoView({ block: 'center', behavior: 'smooth' })
  }
  const validate = (form) => {
    const inputs = Array.from(form.querySelectorAll('input'))
    const errors = []
    const invalid = (input, text) => {
      input.classList.add('is-invalid')
      input.setAttribute('aria-invalid', 'true')
      errors.push(text)
    }
    inputs.forEach((input) => {
      input.classList.remove('is-invalid')
      input.removeAttribute('aria-invalid')
      const label = (data.fields || {})[input.name] || input.name
      const blank = input.required && input.type !== 'password' && input.type !== 'file' && !input.value.trim()
      if (input.validity?.valueMissing || blank) invalid(input, `${label} is required.`)
      else if (input.validity && !input.validity.valid) {
        invalid(input, input.name === 'port' ? 'Port must be a whole number between 1 and 65535.' : `${label}: enter a valid value.`)
      } else if (input.name === 'url' && input.value && !/^https:\/\//i.test(input.value.trim())) {
        invalid(input, 'Restored HTTPS address must start with https://.')
      }
    })
    for (const name of ['kit', 'backup']) {
      const file = inputs.find((input) => input.name === name)
      const server = inputs.find((input) => input.name === `${name}_server`)
      if (file && !file.files.length && !server?.value.trim()) {
        invalid(file, `${data.fields[name]}: select a file or enter its filename in storage/recovery/incoming/.`)
      }
    }
    if (errors.length) showError(`Please complete or correct the following fields:\n${errors.join('\n')}`)
    return errors.length === 0
  }
  const request = async (form) => {
    form.set('csrf', data.csrf)
    const response = await fetch(window.location.href, { method: 'POST', body: form, credentials: 'same-origin' })
    const result = await response.json()
    if (!response.ok || !result.success) {
      const error = new Error((data.errors || {})[result.error] || result.error || 'RECOVERY_REQUEST_FAILED')
      error.code = result.error
      throw error
    }
    return result.result
  }
  const show = (result) => {
    document.getElementById('recovery-result').hidden = false
    const resumed = result.status === 'resumed'
    const completed = result.status === 'complete'
    const published = completed || resumed
    const verified = result.status === 'verified'
    const prepared = result.status === 'prepared'
    const review = result.permissions || { status: 'not_checked', commands: [] }
    const permissionCommands = Array.isArray(review.commands) ? review.commands : []
    const permissionReady = review.status === 'passed' || review.status === 'manual'
    const finalStep = resumed && permissionReady
    const currentStep = finalStep ? 5 : (published ? (permissionReady ? 4 : 3) : (verified || result.status === 'activating' ? 2 : 1))
    for (let step = 1; step <= 5; step++) {
      const marker = document.getElementById(`recovery-step-${step}`)
      marker.className = `badge d-block p-2 ${step === currentStep ? 'badge-primary' : (step < currentStep ? 'badge-success' : 'badge-light')}`
      if (step === currentStep) marker.setAttribute('aria-current', 'step')
      else marker.removeAttribute('aria-current')
    }
    const counts = data.counts.replace('%s', String(result.tables)).replace('%s', String(result.documents))
    document.getElementById('recovery-plan').textContent = `${result.plan.source_version} → ${result.plan.target_version}\n${counts}\n${result.status}${result.error ? `: ${result.error}` : ''}`
    document.getElementById('recovery-instruction').textContent = resumed ? data.resumed : (completed ? data.complete
      : (verified ? data.verified : (prepared ? data.ready : (result.status === 'failed' ? data.failed : data.running))))
    const run = document.getElementById('recovery-command')
    run.hidden = !prepared
    run.textContent = prepared ? `${data.command} --run=${result.id}` : ''
    document.getElementById('recovery-activate-hint').hidden = !prepared
    const activate = document.getElementById('recovery-activate')
    activate.hidden = !verified
    activate.textContent = verified ? `${data.command} --activate=${result.id}` : ''
    document.getElementById('recovery-permissions').hidden = !published || finalStep
    document.getElementById('recovery-permissions-audit').hidden = review.status !== 'not_checked'
    document.getElementById('recovery-permissions-command').textContent = `${data.command} --check-permissions=${result.id}`
    const permissionStatus = document.getElementById('recovery-permissions-status')
    permissionStatus.className = `alert ${review.status === 'passed' ? 'alert-success' : 'alert-info'}`
    permissionStatus.textContent = review.status === 'passed' ? data.permissionsPassed
      : (review.status === 'issues' ? data.permissionsIssues.replace('%s', String(review.issues))
        : (review.status === 'manual' ? data.permissionsManual : data.permissionsNotChecked))
    document.getElementById('recovery-permissions-plan').hidden = review.status !== 'issues' || permissionCommands.length === 0
    document.getElementById('recovery-permissions-commands').textContent = permissionCommands.length
      ? ['(', 'set -e', ...permissionCommands, `${data.command} --check-permissions=${result.id}`, ')'].join('\n') : ''
    document.getElementById('recovery-permissions-manual').hidden = review.status !== 'manual'
      && !(review.status === 'issues' && permissionCommands.length === 0)
    document.getElementById('recovery-resume').hidden = !completed || !permissionReady
    document.getElementById('recovery-resume-command').textContent = `${data.command} --resume=${result.id}`
    document.getElementById('recovery-finished').hidden = !finalStep
    document.getElementById('recovery-finished-status').textContent = result.maintenance_enabled
      ? data.resumedMaintenance : data.resumed
    document.getElementById('recovery-cleanup-command').textContent = data.cleanup || data.cleanupManual
    document.getElementById('recovery-cleanup-command').dataset.copyDisabled = data.cleanup ? 'false' : 'true'
    document.getElementById('recovery-refresh').hidden = finalStep
    syncCopyButtons()
  }
  document.querySelectorAll('#recovery-unlock, #recovery-prepare').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault()
      if (!validate(form)) return
      const button = form.querySelector('button[type=submit]')
      button.disabled = true
      message.className = 'alert alert-info mt-3'
      message.textContent = data.preparing
      try {
        const result = await request(new FormData(form))
        form.querySelectorAll('input[type=password]').forEach((input) => { input.value = '' })
        if (result.unlocked) window.location.reload()
        else {
          message.textContent = ''
          message.className = 'mt-3'
          form.hidden = true
          show(result)
        }
      } catch (error) {
        if (form.id === 'recovery-prepare'
          && ['OPERATOR_CODE_REQUIRED', 'OPERATOR_CODE_EXPIRED', 'OPERATOR_AUTHORIZATION_REQUIRED'].includes(error.code)) {
          form.hidden = true
          document.getElementById('recovery-authorization-help').hidden = false
        }
        showError(`${data.error} ${error.message}`)
      } finally { button.disabled = false }
    })
  })
  document.getElementById('recovery-reauthorize').addEventListener('click', () => window.location.reload())
  document.getElementById('recovery-refresh').addEventListener('click', async () => {
    try {
      const form = new FormData()
      form.set('action', 'status')
      show(await request(form))
    } catch (error) { showError(`${data.error} ${error.message}`) }
  })
  if (data.initial) show(data.initial)
  else syncCopyButtons()
})()
