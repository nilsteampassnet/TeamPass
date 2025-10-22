## LDAP login shows “wrong passphrase” on first-time login

### Symptom
During first-time LDAP logins, users may see:
> "Wrong / not accepted passphrase"

and cannot proceed to the database key setup screen.

### Root cause
This can occur if the PHP-FPM pool is saturated or too small, causing POST requests (especially the passphrase submission) to time out.

**In php-fpm logs:**
`WARNING: [pool www] server reached pm.max_children setting (5), consider raising it`

**In Apache access logs:**
`HTTP 408 (Request Timeout) entries around the same time`


These timeouts make TeamPass appear to reject the passphrase, while in fact the request was never processed.

---

### Solution (no code changes needed)

Tune your PHP-FPM pool settings.  
Edit `/etc/php/8.x/fpm/pool.d/www.conf`:

```
pm = dynamic
pm.max_children = 20      ; was 5
pm.start_servers = 4      ; was 2
pm.min_spare_servers = 4  ; was 1
pm.max_spare_servers = 10 ; was 3
pm.max_requests = 500     ; optional but recommended
```

Then reload services:
```
systemctl reload php8.x-fpm
systemctl reload apache2
```

After this change, first LDAP logins and passphrase setup should work immediately.

---

### How to verify

1. No more FPM warnings or HTTP 408s
Check your php-fpm log:
`sudo journalctl -u php8.x-fpm.service | tail -n 20`

2. TeamPass database shows normal first-login sequence
```
-- Recent system events for a user (replace USER_ID)
SELECT id, type, label,
       FROM_UNIXTIME(CAST(date AS UNSIGNED)) AS event_time,
       qui, field_1
FROM teampass_log_system
WHERE (qui = USER_ID OR field_1 = USER_ID)
ORDER BY id DESC
LIMIT 200;
```

3. User is ready for usage
```
SELECT id, login, is_ready_for_usage,
(public_key IS NOT NULL)  AS has_pub,
(private_key IS NOT NULL) AS has_priv,
(encrypted_psk IS NOT NULL) AS has_enc_psk
FROM teampass_users
WHERE id = USER_ID;
```

If is_ready_for_usage = 1 and all keys are present → success ✅

---

### Extra tips

If LDAP mapping uses sAMAccountName, make sure users don’t log in with user@domain.

For the first passphrase, use an ASCII-only code (no spaces or accents) in a private window to avoid autofill or charset issues.

If you still experience issues, test running the vhost under PHP-FPM 8.3 as a fallback (works fine once FPM pool is correctly sized).

---

 """ TL;DR

If you see “wrong passphrase” but logs show FPM warnings or HTTP 408s:

> It’s not a bad passphrase — it’s php-fpm capacity.

Increase pool size, reload FPM and Apache, and try again.