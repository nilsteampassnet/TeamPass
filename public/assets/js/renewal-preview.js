/** Shared renewal notices for folder browsing, item forms and move previews. */
function createRenewalPreview(config) {
  const messages = config.messages
  const requests = new Map()

  function lines(data) {
    if (!data.enabled) return []
    if (!data.days) return [messages.none]
    const result = [messages.period.replace('#days#', data.days), messages.explanation]
    data.items.forEach(item => {
      const date = item.due_date ? messages.due.replace('#date#', item.due_date) : messages.unknown
      result.push((data.items.length > 1 ? item.label + ' — ' : '') + date + (item.expired ? ' ' + messages.expired : ''))
    })
    if (data.items.length) result.push(data.creation ? messages.estimate : messages.existing)
    return result
  }

  function render(target, data) {
    const text = lines(data)
    target.empty().toggleClass('hidden', text.length === 0)
      .toggleClass('alert-warning', data.items.some(item => item.expired)).addClass('alert')
      .toggleClass('alert-info', !data.items.some(item => item.expired))
    text.forEach(line => $('<div>').text(line).appendTo(target))
  }

  function fetchPreview(folderId, itemIds = [], creation = false) {
    return $.post('sources/items.queries.php', {
      type: 'get_renewal_preview', folder_id: folderId, item_ids: itemIds,
      context: creation ? 'create' : '', key: config.key
    }).then(data => {
      data = decodeQueryReturn(data, config.key, 'items.queries.php', 'get_renewal_preview')
      if (data.error !== false) return $.Deferred().reject().promise()
      return data
    })
  }

  function clear(selector) {
    requests.set(selector, (requests.get(selector) || 0) + 1)
    $(selector).empty().addClass('hidden')
  }

  function update(selector, folderId, itemIds = [], creation = false) {
    clear(selector)
    const requestId = requests.get(selector)
    if (!config.enabled || !Number(folderId)) return $.Deferred().resolve().promise()
    const target = $(selector)
    target.removeClass('hidden alert-warning').addClass('alert alert-info').text(messages.loading)
    return fetchPreview(folderId, itemIds, creation).then(data => {
      if (requestId === requests.get(selector)) render(target, data)
      return data
    }, () => {
      if (requestId === requests.get(selector)) target.text(messages.unavailable)
    })
  }

  function confirmMove(folderId, itemIds) {
    if (!config.enabled) return $.Deferred().resolve(true).promise()
    return fetchPreview(folderId, itemIds).then(data => {
      return !data.days || window.confirm(lines(data).join('\n') + '\n\n' + messages.move_confirm)
    }, () => {
      toastr.error(messages.unavailable)
      return false
    })
  }

  return { update, clear, confirmMove }
}
