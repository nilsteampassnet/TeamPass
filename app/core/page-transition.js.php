<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      page-transition.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;

// Local names only: this file is included in the middle of the page, where
// index.php already holds its own $session and $lang.
$tpPageTransitionSession = SessionManager::getSession();
$tpPageTransitionLang = new Language($tpPageTransitionSession->get('user-language') ?? 'english');
?>
<div id="tp-page-progress" aria-hidden="true"><div id="tp-page-progress-bar"></div></div>
<span id="tp-page-progress-status" class="sr-only" role="status" aria-live="polite"></span>
<script type="text/javascript">
    //<![CDATA[
    /**
     * Page transition indicator.
     *
     * Every TeamPass navigation is a full reload of index.php?page=X, so nothing
     * moves on screen between the click and the first paint of the next page.
     * This module fills that gap with three signals, all vanilla JS so they are
     * live from the first bytes of the page body, long before jQuery loads:
     *
     *   1. a thin progress bar pinned to the top of the viewport,
     *   2. a spinner on the very control that was clicked, so the user knows
     *      which action was taken into account,
     *   3. a re-entrance guard that swallows further navigation clicks.
     *
     * The bar spans the whole transition: the departing page starts it and
     * leaves its position in sessionStorage, the arriving page picks it up at
     * that exact percentage and carries it until its own scripts have run and
     * painted. The user sees one continuous bar, never a rewind.
     */
    (function () {
        'use strict'

        var LOADING_TEXT = <?php echo json_encode($tpPageTransitionLang->get('loading')); ?>
        // Hard stop: a navigation that never happens (download, blocked popup,
        // cancelled request) must not leave the interface locked forever.
        var SAFETY_MS = 20000
        // A page that is ready faster than this needs no indicator at all -
        // showing one would only add flicker.
        var ARRIVAL_DELAY_MS = 150
        // Last resort when requestAnimationFrame never fires (background tab).
        var READY_FALLBACK_MS = 2000
        // AJAX below this duration is not worth an indicator: the bar would only
        // flash. Replaces what the removed Pace corner indicator used to show.
        var AJAX_DELAY_MS = 400
        var PENDING_KEY = 'tpNavigationPending'
        // A hand-over older than this is not the page we just left: the tab went
        // somewhere else and came back. Start from scratch instead.
        var HANDOVER_MAX_AGE_MS = 60000

        // FontAwesome family classes, FA5 short form and FA6 long form alike:
        // an icon that already declares one keeps it.
        var ICON_FAMILY = /^(fa|fas|far|fab|fal|fad|fat|fa-solid|fa-regular|fa-brands|fa-light|fa-thin|fa-duotone)$/
        // Classes describing how an icon is drawn rather than which glyph it is:
        // they must survive the swap so size and spacing stay identical once the
        // spinner replaces the glyph.
        var ICON_MODIFIER = /^fa-(fw|xs|sm|lg|xl|2xs|[0-9]+x|spin|spin-pulse|spin-reverse|pulse|border|pull-left|pull-right|rotate-[a-z0-9-]+|flip[a-z-]*|stack[a-z-]*|inverse|beat|beat-fade|fade|shake|bounce|li|solid|regular|brands|light|thin|duotone|sharp)$/

        var wrap = null
        var bar = null
        var status = null
        var creepTimer = null
        var arrivalTimer = null
        var safetyTimer = null
        var doneTimer = null
        var readyTimer = null
        var ajaxTimer = null
        var progress = 0
        var active = false
        var navigating = false
        var busyElement = null

        /**
         * Resolves the bar nodes once.
         *
         * @return {boolean} True when the indicator can be painted.
         */
        function elements() {
            if (wrap === null) {
                wrap = document.getElementById('tp-page-progress')
            }
            if (bar === null) {
                bar = document.getElementById('tp-page-progress-bar')
            }
            if (status === null) {
                status = document.getElementById('tp-page-progress-status')
            }
            return wrap !== null && bar !== null
        }

        /**
         * Creeps towards 92% and stops there. The last 8% belong to the moment
         * the next page is actually usable, so the bar never claims to be done
         * while the user is still waiting.
         *
         * @return void
         */
        function creep() {
            creepTimer = window.setTimeout(function () {
                progress += Math.max(0.4, (92 - progress) * 0.06)
                if (progress > 92) {
                    progress = 92
                }
                bar.style.width = progress + '%'
                creep()
            }, 260)
        }

        /**
         * Shows the bar and starts the creep. Idempotent.
         *
         * @param {number} [from] Percentage handed over by the previous page.
         *                        Painted with no transition so the bar picks up
         *                        exactly where it was instead of rewinding.
         *
         * @return void
         */
        function start(from) {
            if (active === true || elements() === false) {
                return
            }
            active = true

            var resumed = typeof from === 'number' && isNaN(from) === false && from > 0
            progress = resumed === true ? Math.min(from, 92) : 8

            window.clearTimeout(doneTimer)
            doneTimer = null
            bar.classList.remove('tp-page-progress-done')
            // Place the bar without animating: a resume lands straight on the
            // handed-over position, a fresh start rewinds to zero so the first
            // step reads as a reaction to the click.
            bar.style.transition = 'none'
            bar.style.width = (resumed === true ? progress : 0) + '%'
            wrap.classList.add('tp-page-progress-active')
            void bar.offsetWidth
            bar.style.transition = ''

            bar.style.width = progress + '%'
            creep()

            if (status !== null) {
                status.textContent = LOADING_TEXT
            }
        }

        /**
         * Completes the bar, then fades it out. Also releases the click guard.
         *
         * @return void
         */
        function done() {
            window.clearTimeout(arrivalTimer)
            arrivalTimer = null
            window.clearTimeout(creepTimer)
            creepTimer = null
            unlock()

            if (active === false || elements() === false) {
                return
            }
            active = false
            progress = 100
            bar.style.width = '100%'

            doneTimer = window.setTimeout(function () {
                bar.classList.add('tp-page-progress-done')
                doneTimer = window.setTimeout(function () {
                    wrap.classList.remove('tp-page-progress-active')
                    bar.classList.remove('tp-page-progress-done')
                    bar.style.width = '0%'
                    progress = 0
                    doneTimer = null
                }, 350)
            }, 60)

            if (status !== null) {
                status.textContent = ''
            }
        }

        /**
         * Builds the spinner class list from an existing icon, keeping every
         * modifier and dropping only the glyph.
         *
         * @param {string} original Current className of the <i> element.
         *
         * @return {string}
         */
        function spinnerClassName(original) {
            var kept = []
            original.split(/\s+/).forEach(function (name) {
                if (name === '') {
                    return
                }
                if (name.indexOf('fa-') === 0 && ICON_MODIFIER.test(name) === false) {
                    return
                }
                if (name === 'fa-spin' || name === 'fa-circle-notch') {
                    return
                }
                kept.push(name)
            })
            // Only pick a family when the icon declared none, otherwise the
            // spinner would inherit two conflicting font weights.
            var hasFamily = kept.some(function (name) {
                return ICON_FAMILY.test(name) === true
            })
            if (hasFamily === false) {
                kept.push('fa-solid')
            }
            kept.push('fa-circle-notch')
            kept.push('fa-spin')

            return kept.join(' ')
        }

        /**
         * Turns the clicked control into a busy control.
         *
         * @param {Element|null} element The control that triggered the navigation.
         *
         * @return void
         */
        function markBusy(element) {
            if (document.body !== null) {
                document.body.classList.add('tp-navigating')
            }
            if (element === null) {
                return
            }
            busyElement = element
            element.classList.add('tp-nav-busy')
            element.setAttribute('aria-busy', 'true')

            var icon = element.querySelector('i')
            if (icon !== null) {
                icon.setAttribute('data-tp-nav-icon', icon.className)
                icon.className = spinnerClassName(icon.className)
            }
        }

        /**
         * Restores the clicked control and re-enables navigation clicks.
         *
         * @return void
         */
        function unlock() {
            window.clearTimeout(safetyTimer)
            safetyTimer = null
            navigating = false

            if (document.body !== null) {
                document.body.classList.remove('tp-navigating')
            }
            if (busyElement === null) {
                return
            }
            busyElement.classList.remove('tp-nav-busy')
            busyElement.removeAttribute('aria-busy')

            var icon = busyElement.querySelector('i[data-tp-nav-icon]')
            if (icon !== null) {
                icon.className = icon.getAttribute('data-tp-nav-icon')
                icon.removeAttribute('data-tp-nav-icon')
            }
            busyElement = null
        }

        /**
         * Hands the current position of the bar to the document about to load,
         * so it resumes there instead of rewinding to its starting point.
         *
         * @return void
         */
        function handOverProgress() {
            try {
                window.sessionStorage.setItem(
                    PENDING_KEY,
                    Math.round(progress) + ':' + Date.now()
                )
            } catch (exception) {
                // Private mode or storage full: the next page simply falls back
                // to its own delayed start.
            }
        }

        /**
         * Enters the navigating state: bar, busy control, click guard and the
         * hand-over read by the next document.
         *
         * @param {Element|null} element The control that triggered the navigation.
         *
         * @return void
         */
        function startNavigation(element) {
            if (navigating === true) {
                return
            }
            navigating = true
            markBusy(element)
            start()
            handOverProgress()
            safetyTimer = window.setTimeout(done, SAFETY_MS)
        }

        /**
         * Tells whether an anchor leads to another TeamPass page. Deliberately
         * narrow: a file served for download must not arm the indicator, since
         * the page never unloads and nothing would ever close it.
         *
         * @param {HTMLAnchorElement} anchor
         *
         * @return {boolean}
         */
        function isPageLink(anchor) {
            var href = anchor.getAttribute('href')
            if (href === null || href === '' || href.charAt(0) === '#') {
                return false
            }
            if (/^(javascript|mailto|tel|data):/i.test(href) === true) {
                return false
            }
            if (anchor.hasAttribute('download') === true) {
                return false
            }
            var target = anchor.getAttribute('target')
            if (target !== null && target !== '' && target !== '_self') {
                return false
            }
            if (anchor.href.indexOf(window.location.origin) !== 0) {
                return false
            }

            return anchor.href.indexOf('page=') !== -1
                || /(?:^|\/)index\.php(?:[?#]|$)/.test(anchor.href) === true
                || /logout\.php(?:[?#]|$)/.test(anchor.href) === true
        }

        /**
         * Resolves the control to decorate, or null when the click does not
         * lead to a page change.
         *
         * @param {Element} element
         *
         * @return {Element|null}
         */
        function navigationTrigger(element) {
            // Sidebar entries: load.js.php turns data-name into index.php?page=X.
            if (element.matches('.nav-link[data-name]') === true) {
                return element
            }
            // Account menu: only these two entries really leave the page, the
            // others open a dialog in place.
            if (element.matches('.user-menu[data-name="profile"], .user-menu[data-name="logout"]') === true) {
                return element
            }
            if (element.tagName === 'A' && isPageLink(element) === true) {
                return element
            }

            return null
        }

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented === true || event.button !== 0) {
                return
            }
            // Modified clicks open a new tab: the current page stays put.
            if (event.metaKey === true || event.ctrlKey === true
                || event.shiftKey === true || event.altKey === true
            ) {
                return
            }
            var target = event.target
            if (target === null || typeof target.closest !== 'function') {
                return
            }
            var element = target.closest('a, button')
            if (element === null) {
                return
            }
            var trigger = navigationTrigger(element)
            if (trigger === null) {
                return
            }
            if (navigating === true) {
                if (busyElement !== null
                    && (trigger === busyElement || busyElement.contains(trigger) === true)
                ) {
                    // Impatient double-click on the entry already loading.
                    event.preventDefault()
                    event.stopImmediatePropagation()
                    return
                }
                // Another destination: the user changed their mind, hand the
                // busy state over instead of blocking them.
                unlock()
            }
            startNavigation(trigger)
        }, true)

        // Catch-all for the navigations triggered from JS (location.href = ...)
        // or by a form submit: no busy control to decorate, but the bar shows.
        window.addEventListener('beforeunload', function () {
            start()
            // Written last: by now the creep has advanced past what
            // startNavigation() recorded at click time.
            handOverProgress()
        })

        // Back/forward from the bfcache restores the page as it was left, busy
        // control included.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted === true) {
                done()
            }
        })

        // --- Arrival side of the transition -----------------------------------

        /**
         * Reads the position left by the page we come from.
         *
         * @return {number} Percentage to resume at, -1 when there is nothing to
         *                  resume (direct hit, reload, or a stale hand-over).
         */
        function readHandOver() {
            var raw = null
            try {
                raw = window.sessionStorage.getItem(PENDING_KEY)
                window.sessionStorage.removeItem(PENDING_KEY)
            } catch (exception) {
                // See handOverProgress().
            }
            if (raw === null || raw === '') {
                return -1
            }

            var parts = raw.split(':')
            var value = parseInt(parts[0], 10)
            var writtenAt = parseInt(parts[1], 10)
            if (isNaN(value) === true) {
                return -1
            }
            if (isNaN(writtenAt) === false && Date.now() - writtenAt > HANDOVER_MAX_AGE_MS) {
                return -1
            }

            return value
        }

        var handOver = readHandOver()

        if (handOver >= 0) {
            // Resume where the previous page left the bar, so the indicator is
            // uninterrupted from the click to a usable page.
            start(handOver)
        } else {
            // Direct hit or reload: stay silent unless the page is slow.
            arrivalTimer = window.setTimeout(function () {
                start()
            }, ARRIVAL_DELAY_MS)
        }

        /**
         * Extends the bar to AJAX, the role the Pace corner indicator used to
         * play. A navigation always wins: it owns the bar until the page is
         * replaced.
         *
         * @return void
         */
        function bindAjaxIndicator() {
            var jq = window.jQuery
            if (jq === undefined) {
                return
            }
            jq(document).on('ajaxStart', function () {
                if (navigating === true) {
                    return
                }
                window.clearTimeout(ajaxTimer)
                ajaxTimer = window.setTimeout(function () {
                    ajaxTimer = null
                    if (navigating === false) {
                        start()
                    }
                }, AJAX_DELAY_MS)
            })
            jq(document).on('ajaxStop', function () {
                window.clearTimeout(ajaxTimer)
                ajaxTimer = null
                if (navigating === true) {
                    return
                }
                done()
            })
        }

        /**
         * Closes the bar once the page is really usable. Two frames after
         * DOMContentLoaded: jQuery ready handlers have run synchronously by
         * then, and the browser has painted their result.
         *
         * @return void
         */
        function finishOnReady() {
            // jQuery is loaded at the end of the page body, so it is there by now.
            bindAjaxIndicator()
            readyTimer = window.setTimeout(done, READY_FALLBACK_MS)
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    window.clearTimeout(readyTimer)
                    readyTimer = null
                    done()
                })
            })
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', finishOnReady)
        } else {
            finishOnReady()
        }

        window.tpPageTransition = {
            start: startNavigation,
            done: done
        }
    })()
    //]]>
</script>
