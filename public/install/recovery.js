/* TeamPass - GPL-3.0-or-later. Copyright 2009-2026 Teampass.net. */
'use strict'
;(() => {
  const data = JSON.parse(document.getElementById('recovery-data').textContent)
  const message = document.getElementById('recovery-message')
  const request = async (form) => {
    form.set('csrf', data.csrf)
    const response = await fetch(window.location.href, { method: 'POST', body: form, credentials: 'same-origin' })
    const result = await response.json()
    if (!response.ok || !result.success) throw new Error((data.errors || {})[result.error] || result.error || 'RECOVERY_REQUEST_FAILED')
    return result.result
  }
  const show = (result) => {
    document.getElementById('recovery-result').hidden = false
    const completed = result.status === 'complete'
    const verified = result.status === 'verified'
    const prepared = result.status === 'prepared'
    const counts = data.counts.replace('%s', String(result.tables)).replace('%s', String(result.documents))
    document.getElementById('recovery-plan').textContent = `${result.plan.source_version} → ${result.plan.target_version}\n${counts}\n${result.status}${result.error ? `: ${result.error}` : ''}`
    document.getElementById('recovery-instruction').textContent = completed ? data.complete
      : (verified ? data.verified : (prepared ? data.ready : (result.status === 'failed' ? data.failed : data.running)))
    const run = document.getElementById('recovery-command')
    run.hidden = !prepared
    run.textContent = `${data.command} --run=${result.id}`
    document.getElementById('recovery-activate-hint').hidden = !prepared
    const activate = document.getElementById('recovery-activate')
    activate.hidden = !(prepared || verified || completed)
    activate.textContent = `${data.command} --${completed ? 'resume' : 'activate'}=${result.id}`
  }
  document.querySelectorAll('#recovery-unlock, #recovery-prepare').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault()
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
        message.className = 'alert alert-danger mt-3'
        message.textContent = `${data.error} ${error.message}`
      } finally { button.disabled = false }
    })
  })
  document.getElementById('recovery-refresh').addEventListener('click', async () => {
    try {
      const form = new FormData()
      form.set('action', 'status')
      show(await request(form))
    } catch (error) { message.textContent = `${data.error} ${error.message}` }
  })
  if (data.initial) show(data.initial)
})()
