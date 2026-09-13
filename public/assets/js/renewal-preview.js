/** Shared renewal notices for folder browsing, item forms and move previews. */
function createRenewalPreview(config) {
  const messages = config.messages
  const requests = new Map()

  function lines(data) {
    const result = [data.days ? messages.period.replace('#days#', data.days) : messages.none]
    data.items.forEach(item => {
      const prefix = data.items.length > 1 ? item.label + ' — ' : ''
      result.push(prefix + (item.days ? messages.effective.replace('#days#', item.days) + ' ' : '') + messages['source_' + item.source])
      if (item.days) {
        const date = item.due_date ? messages.due.replace('#date#', item.due_date) : messages.unknown
        result.push(prefix + date + (item.expired ? ' ' + messages.expired : ''))
      }
    })
    if (data.days || data.items.some(item => item.days)) result.push(messages.explanation)
    if (data.items.some(item => item.days)) result.push(data.creation ? messages.estimate : messages.existing)
    return result
  }

  function render(target, data) {
    const text = lines(data)
    target.empty().toggleClass('hidden', text.length === 0)
      .toggleClass('alert-warning', data.items.some(item => item.expired)).addClass('alert')
      .toggleClass('alert-info', !data.items.some(item => item.expired))
    text.forEach(line => $('<div>').text(line).appendTo(target))
  }

  function fetchPreview(folderId, itemIds = [], creation = false, itemPeriod = null, copy = false) {
    const request = {
      type: 'get_renewal_preview', folder_id: folderId, item_ids: itemIds,
      context: copy ? 'copy' : (creation ? 'create' : ''), key: config.key
    }
    if (itemPeriod !== null) request.renewal_period = itemPeriod
    return $.post('sources/items.queries.php', request).then(data => {
      data = decodeQueryReturn(data, config.key, 'items.queries.php', 'get_renewal_preview')
      if (data.error !== false) return $.Deferred().reject().promise()
      return data
    })
  }

  function clear(selector) {
    requests.set(selector, (requests.get(selector) || 0) + 1)
    $(selector).empty().addClass('hidden')
  }

  function update(selector, folderId, itemIds = [], creation = false, itemPeriod = null, copy = false) {
    clear(selector)
    const requestId = requests.get(selector)
    if (!Number(folderId)) return $.Deferred().resolve().promise()
    const target = $(selector)
    target.removeClass('hidden alert-warning').addClass('alert alert-info').text(messages.loading)
    return fetchPreview(folderId, itemIds, creation, itemPeriod, copy).then(data => {
      if (requestId === requests.get(selector)) render(target, data)
      return data
    }, () => {
      if (requestId === requests.get(selector)) target.text(messages.unavailable)
    })
  }

  function confirmMove(folderId, itemIds) {
    return fetchPreview(folderId, itemIds).then(data => {
      return !data.items.some(item => item.days) || window.confirm(lines(data).join('\n') + '\n\n' + messages.move_confirm)
    }, () => {
      toastr.error(messages.unavailable)
      return false
    })
  }

  return { update, clear, confirmMove }
}
