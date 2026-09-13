/** Shared renewal notices for folder browsing, item forms and move previews. */
function createRenewalPreview(config) {
  const messages = config.messages
  const requests = new Map()

  /** Use a dated badge on cards and a compact status icon beside the list's security markers. */
  function badgeHtml(renewal, listMarker = false) {
    const colours = { scheduled: 'info', soon: 'warning', expired: 'danger', unknown: 'secondary' }
    if (!renewal || !Object.prototype.hasOwnProperty.call(colours, renewal.state)
        || (listMarker && renewal.state !== 'soon' && renewal.state !== 'expired')) return ''
    const escape = value => String(value).replace(/[&<>"']/g, character => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character])
    const dateText = renewal.due_date ? messages.due.replace('#date#', renewal.due_date) : messages.unknown
    const title = messages.effective.replace('#days#', renewal.days) + ' ' + dateText
    if (listMarker) {
      const icon = renewal.state === 'expired' ? 'fa-calendar-xmark' : 'fa-hourglass-half'
      const tooltip = escape(messages['badge_' + renewal.state] + ' — ' + dateText + ' ' + messages.effective.replace('#days#', renewal.days))
      return '<i class="fa-solid ' + icon + ' mr-1 infotip tp-item-renewal-marker text-' + colours[renewal.state]
        + '" role="img" aria-label="' + tooltip + '" title="' + tooltip + '"></i>'
    }
    const label = messages['badge_' + renewal.state] + (renewal.due_date ? ' — ' + renewal.due_date : '')
    return '<span class="badge badge-' + colours[renewal.state] + ' ml-2 flex-shrink-0 tp-item-renewal-badge" title="' + escape(title) + '">'
      + '<i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>' + escape(label) + '</span>'
  }

  function lines(data) {
    const allExcluded = data.items.length > 0 && data.items.every(item => item.source === 'lapr')
    const result = allExcluded ? [] : [data.days ? messages.period.replace('#days#', data.days) : messages.none]
    data.items.forEach(item => {
      const prefix = data.items.length > 1 ? item.label + ' — ' : ''
      result.push(prefix + (item.days ? messages.effective.replace('#days#', item.days) + ' ' : '') + messages['source_' + item.source])
      if (item.days) {
        const date = item.due_date ? messages.due.replace('#date#', item.due_date) : messages.unknown
        result.push(prefix + date + (item.expired ? ' ' + messages.expired : ''))
      }
    })
    if (!allExcluded && (data.days || data.items.some(item => item.days))) result.push(messages.explanation)
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

  return { update, clear, confirmMove, badgeHtml }
}
