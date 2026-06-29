<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<title>{{TITLE}}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px; margin: 0; background: #eceff4; color: #2e3440; }
    header { background: #344151; color: #fff; padding: 16px 24px; }
    header h1 { margin: 0; font-size: 20px; font-weight: 600; }
    header .meta { font-size: 12px; opacity: .8; margin-top: 4px; }
    .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
    .card { background: #fff; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,.12); padding: 20px; margin-bottom: 20px; }
    .unlock { max-width: 420px; margin: 60px auto; text-align: center; }
    .unlock label { display: block; margin-bottom: 8px; font-weight: 600; }
    input[type="password"], input[type="text"], input.search, select { width: 100%; padding: 9px 12px; border: 1px solid #c8ced9; border-radius: 4px; font-size: 14px; background: #fff; }
    .unlock select { margin-top: 6px; }
    button { cursor: pointer; border: none; border-radius: 4px; padding: 9px 16px; font-size: 14px; background: #3b6ea5; color: #fff; }
    button.secondary { background: #707d92; }
    button.small { padding: 3px 8px; font-size: 12px; }
    .error { color: #bf2d2d; margin-top: 12px; min-height: 18px; }
    .toolbar { display: flex; gap: 12px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
    .toolbar .search { flex: 1; min-width: 220px; }
    .count { font-size: 12px; color: #707d92; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #344151; color: #fff; text-align: left; padding: 8px 10px; font-size: 12px; position: sticky; top: 0; }
    tbody td { padding: 8px 10px; border-bottom: 1px solid #e5e9f0; vertical-align: top; word-break: break-word; }
    tbody tr.item:nth-child(4n+1) td { background: #f7f9fc; }
    .pw-cell { white-space: nowrap; }
    .pw-value { font-family: ui-monospace, "Courier New", monospace; }
    .muted { color: #9aa4b5; }
    .detail td { background: #fbfcfe !important; font-size: 13px; }
    .detail dl { margin: 0; display: grid; grid-template-columns: max-content 1fr; gap: 4px 14px; }
    .detail dt { font-weight: 600; color: #4c566a; }
    .detail dd { margin: 0; word-break: break-word; }
    a.url-link { color: #3b6ea5; }
    .hidden { display: none !important; }
    footer { text-align: center; font-size: 11px; color: #707d92; padding: 16px; }
    footer a { color: #707d92; }
</style>
</head>
<body>
<header>
    <h1>{{HEADING}}</h1>
    <div class="meta">{{GENERATED_INFO}}</div>
</header>

<div class="container">
    <!-- Unlock screen -->
    <div id="unlock" class="card unlock">
        <form id="unlock-form" autocomplete="off">
            <label for="password" id="lbl-password"></label>
            <input type="password" id="password" autocomplete="off" autofocus />
            <label for="lock-duration" id="lbl-duration" style="display:block; margin-top:14px;"></label>
            <select id="lock-duration">
                <option value="1">1 min</option>
                <option value="5" selected>5 min</option>
                <option value="15">15 min</option>
                <option value="30">30 min</option>
                <option value="60">60 min</option>
            </select>
            <div style="margin-top:14px;">
                <button type="submit" id="btn-unlock"></button>
            </div>
            <div class="error" id="unlock-error"></div>
        </form>
    </div>

    <!-- Data screen -->
    <div id="data" class="card hidden">
        <div class="toolbar">
            <input type="text" class="search" id="search" />
            <button type="button" class="secondary small" id="btn-hide-all"></button>
            <span class="count" id="count"></span>
            <span class="count" id="lock-countdown"></span>
            <button type="button" class="small" id="btn-extend"></button>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr id="thead-row"></tr>
                </thead>
                <tbody id="tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<footer>{{BRANDING}}</footer>

<script type="application/json" id="tp-bundle">{{BUNDLE_JSON}}</script>
<script type="application/json" id="tp-i18n">{{I18N_JSON}}</script>
<script>
(function () {
    "use strict";

    var bundle = JSON.parse(document.getElementById("tp-bundle").textContent);
    var t = JSON.parse(document.getElementById("tp-i18n").textContent);

    // Apply static labels
    document.getElementById("lbl-password").textContent = t.enter_password;
    document.getElementById("btn-unlock").textContent = t.unlock;
    document.getElementById("btn-hide-all").textContent = t.hide_all;
    document.getElementById("search").placeholder = t.search;
    document.getElementById("lbl-duration").textContent = t.lock_duration;
    document.getElementById("btn-extend").textContent = t.extend;

    if (!window.crypto || !window.crypto.subtle) {
        showUnlockError(t.no_webcrypto);
        document.getElementById("password").disabled = true;
        document.getElementById("btn-unlock").disabled = true;
        return;
    }

    function showUnlockError(msg) {
        document.getElementById("unlock-error").textContent = msg;
    }

    function b64ToBytes(b64) {
        var bin = atob(b64);
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) { bytes[i] = bin.charCodeAt(i); }
        return bytes;
    }

    async function decryptBundle(password) {
        var enc = new TextEncoder();
        var baseKey = await crypto.subtle.importKey(
            "raw", enc.encode(password), "PBKDF2", false, ["deriveKey"]
        );
        var key = await crypto.subtle.deriveKey(
            { name: "PBKDF2", salt: b64ToBytes(bundle.salt), iterations: bundle.it, hash: "SHA-256" },
            baseKey,
            { name: "AES-GCM", length: 256 },
            false,
            ["decrypt"]
        );
        var plain = await crypto.subtle.decrypt(
            { name: "AES-GCM", iv: b64ToBytes(bundle.iv) },
            key,
            b64ToBytes(bundle.data)
        );
        return new TextDecoder().decode(plain);
    }

    // ---- Rendering (all dynamic values via textContent to avoid any HTML injection) ----

    function makeCell(text) {
        var td = document.createElement("td");
        td.textContent = text || "";
        if (!text) { td.className = "muted"; }
        return td;
    }

    function renderTable(items) {
        var headRow = document.getElementById("thead-row");
        [t.col_folder, t.col_label, t.col_login, t.col_password, t.col_url, t.col_email, ""].forEach(function (label) {
            var th = document.createElement("th");
            th.textContent = label;
            headRow.appendChild(th);
        });

        var tbody = document.getElementById("tbody");

        items.forEach(function (it, idx) {
            var tr = document.createElement("tr");
            tr.className = "item";

            tr.appendChild(makeCell(it.folder));
            tr.appendChild(makeCell(it.label));
            tr.appendChild(makeCell(it.login));

            // Password cell with reveal / copy
            var pwTd = document.createElement("td");
            pwTd.className = "pw-cell";
            if (it.password) {
                var span = document.createElement("span");
                span.className = "pw-value muted";
                span.textContent = "••••••••";
                var revealBtn = document.createElement("button");
                revealBtn.className = "small secondary";
                revealBtn.style.marginLeft = "6px";
                revealBtn.textContent = t.reveal;
                revealBtn.addEventListener("click", function () {
                    // State derived from the DOM so "hide all" stays consistent
                    var isHidden = span.classList.contains("muted");
                    span.textContent = isHidden ? it.password : "••••••••";
                    span.classList.toggle("muted", !isHidden);
                    revealBtn.textContent = isHidden ? t.hide : t.reveal;
                });
                var copyBtn = document.createElement("button");
                copyBtn.className = "small";
                copyBtn.style.marginLeft = "4px";
                copyBtn.textContent = t.copy;
                copyBtn.addEventListener("click", function () {
                    navigator.clipboard && navigator.clipboard.writeText(it.password).then(function () {
                        copyBtn.textContent = t.copied;
                        setTimeout(function () { copyBtn.textContent = t.copy; }, 1200);
                    });
                });
                pwTd.appendChild(span);
                pwTd.appendChild(revealBtn);
                pwTd.appendChild(copyBtn);
            } else {
                pwTd.className = "pw-cell muted";
                pwTd.textContent = "—";
            }
            tr.appendChild(pwTd);

            // URL: only linkify safe schemes; never assign a javascript:/data: URL to href.
            // The original value is always shown via textContent.
            var urlTd = document.createElement("td");
            if (it.url) {
                var rawUrl = String(it.url).trim();
                var safeUrl = /^(https?:|ftp:|mailto:)/i.test(rawUrl) ? rawUrl : null;
                if (safeUrl) {
                    var a = document.createElement("a");
                    a.className = "url-link";
                    a.href = safeUrl;
                    a.target = "_blank";
                    a.rel = "noopener noreferrer";
                    a.textContent = it.url;
                    urlTd.appendChild(a);
                } else {
                    // Unsafe or relative scheme: render as inert plain text
                    urlTd.textContent = it.url;
                }
            } else {
                urlTd.className = "muted";
            }
            tr.appendChild(urlTd);

            tr.appendChild(makeCell(it.email));

            // Details toggle
            var hasDetail = it.description || (it.tags) || (it.fields && it.fields.length);
            var toggleTd = document.createElement("td");
            if (hasDetail) {
                var toggleBtn = document.createElement("button");
                toggleBtn.className = "small secondary";
                toggleBtn.textContent = t.details;
                toggleTd.appendChild(toggleBtn);
                tr.appendChild(toggleTd);

                var detailTr = document.createElement("tr");
                detailTr.className = "detail hidden";
                var detailTd = document.createElement("td");
                detailTd.colSpan = 7;
                var dl = document.createElement("dl");
                function addRow(label, value) {
                    if (!value) { return; }
                    var dt = document.createElement("dt");
                    dt.textContent = label;
                    var dd = document.createElement("dd");
                    dd.textContent = value;
                    dl.appendChild(dt); dl.appendChild(dd);
                }
                addRow(t.col_description, it.description);
                addRow(t.col_tags, it.tags);
                (it.fields || []).forEach(function (f) {
                    addRow(f.title, f.value);
                });
                detailTd.appendChild(dl);
                detailTr.appendChild(detailTd);

                toggleBtn.addEventListener("click", function () {
                    detailTr.classList.toggle("hidden");
                });

                // searchable text (excludes password)
                tr.dataset.search = [it.folder, it.label, it.login, it.url, it.email, it.description, it.tags]
                    .join(" ").toLowerCase();
                tbody.appendChild(tr);
                tbody.appendChild(detailTr);
                tr._detail = detailTr;
            } else {
                tr.appendChild(toggleTd);
                tr.dataset.search = [it.folder, it.label, it.login, it.url, it.email].join(" ").toLowerCase();
                tbody.appendChild(tr);
            }
        });

        document.getElementById("count").textContent = items.length + " " + t.items;
    }

    // Hide-all passwords
    document.getElementById("btn-hide-all").addEventListener("click", function () {
        document.querySelectorAll("#tbody .pw-value").forEach(function (span) {
            span.textContent = "••••••••";
            span.className = "pw-value muted";
        });
        document.querySelectorAll("#tbody .pw-cell button.secondary").forEach(function (b) {
            b.textContent = t.reveal;
        });
    });

    // Search filter
    document.getElementById("search").addEventListener("input", function (e) {
        var q = e.target.value.toLowerCase().trim();
        document.querySelectorAll("#tbody tr.item").forEach(function (tr) {
            var match = !q || (tr.dataset.search || "").indexOf(q) !== -1;
            tr.classList.toggle("hidden", !match);
            if (tr._detail && !match) { tr._detail.classList.add("hidden"); }
        });
    });

    // ---- Auto-lock timer ----
    // When the countdown reaches zero the page is reloaded: this wipes every decrypted value
    // from memory and brings back the password + duration prompt.

    var lockDurationMs = 5 * 60 * 1000;
    var lockDeadline = 0;
    var countdownTimer = null;

    function formatRemaining(ms) {
        if (ms < 0) { ms = 0; }
        var totalSec = Math.floor(ms / 1000);
        var m = Math.floor(totalSec / 60);
        var s = totalSec % 60;
        return m + ":" + (s < 10 ? "0" : "") + s;
    }

    function lockNow() {
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        location.reload();
    }

    function refreshCountdown() {
        var remaining = lockDeadline - Date.now();
        if (remaining <= 0) {
            lockNow();
            return;
        }
        document.getElementById("lock-countdown").textContent = t.locks_in + " " + formatRemaining(remaining);
    }

    function startLockTimer() {
        lockDeadline = Date.now() + lockDurationMs;
        if (countdownTimer) { clearInterval(countdownTimer); }
        refreshCountdown();
        countdownTimer = setInterval(refreshCountdown, 1000);
    }

    // "Extend" resets the countdown to the duration initially chosen
    document.getElementById("btn-extend").addEventListener("click", startLockTimer);

    // Unlock submit
    document.getElementById("unlock-form").addEventListener("submit", async function (e) {
        e.preventDefault();
        showUnlockError("");
        var pwd = document.getElementById("password").value;
        if (!pwd) { showUnlockError(t.password_empty); return; }
        var btn = document.getElementById("btn-unlock");
        btn.disabled = true;
        btn.textContent = t.decrypting;
        try {
            var json = await decryptBundle(pwd);
            var items = JSON.parse(json);
            renderTable(items);
            lockDurationMs = (parseInt(document.getElementById("lock-duration").value, 10) || 5) * 60 * 1000;
            document.getElementById("unlock").classList.add("hidden");
            document.getElementById("data").classList.remove("hidden");
            startLockTimer();
        } catch (err) {
            showUnlockError(t.wrong_password);
            btn.disabled = false;
            btn.textContent = t.unlock;
        }
    });
})();
</script>
</body>
</html>
