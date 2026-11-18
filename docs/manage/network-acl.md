<!-- docs/manage/network-acl.md -->

## Overview

The **Network ACL** feature lets administrators control which IPv4 addresses or CIDR ranges can access TeamPass. It supports two independent lists:

- **Blacklist** — always-deny list: any matching IP is blocked regardless of the whitelist.
- **Whitelist** — exclusive allow list: when enabled, only IPs explicitly listed can reach TeamPass.

Both lists can be used together or independently. The shared HTTP runtime
checks the policy before login processing, application pages, AJAX actions and
the REST API (including preflight requests). A session opened on an allowed
network does not exempt later requests from a different or newly blocked IP.

The following entry points keep their separate authorization:

- **Secure Send recipient pages**, on both the main and public sharing address,
  use their link credentials and confirmation policy. They are not filtered by
  these network lists. Selecting the main address does not make a link LAN-only.
- **Installation and upgrade scripts** use their setup protections, so a missing
  ACL table cannot prevent a required migration or repair.
- **CLI scripts** do not represent an incoming HTTP client and are not filtered.

Static resources and the WebSocket transport are not
filtered by this PHP request guard. Use the web server, reverse proxy or
firewall when a network restriction must cover the entire service. To limit
Secure Send recipients by network, apply that policy on its dedicated public
virtual host or proxy route.

> :warning: **Important:** Before enabling the whitelist, make sure your own IP and the server IP are already added to the list. TeamPass automatically offers to add them when you save the settings, but verify them first to avoid locking yourself out.

---

## How it works

### Evaluation order

For each incoming request, TeamPass evaluates the rules in this order:

1. If the blacklist is enabled and the client IP matches a rule → **access denied**.
2. If the whitelist is enabled and the client IP matches a rule → **access granted**.
3. If the whitelist is enabled and the client IP matches **no** rule → **access denied**.
4. If neither list is enabled → **access granted** (no filtering).

### IP detection

TeamPass needs to determine the real client IP. Two modes are available:

| Mode | When to use |
|------|-------------|
| **Direct access** | TeamPass is directly reachable from the Internet or your LAN. `REMOTE_ADDR` is used as-is. |
| **Reverse proxy / WAF** | A proxy or load balancer sits in front of TeamPass. The real IP is read from a proxy header (`X-Forwarded-For` or `X-Real-IP`). |

In reverse proxy mode, the proxy header is only trusted when `REMOTE_ADDR` matches one of the configured **trusted proxies**. This prevents header spoofing from untrusted clients.

**Compatibility:** changing forwarded-address parsing in reverse proxy mode also
changes the address used for brute-force counters, audit logging and device-pairing
rate limits, even when both ACL lists are disabled. Verify the detected address
and trusted proxy configuration before updating.

---

## Configuration

Open **Admin → Options → Networks**.

### General settings

| Setting | Description |
|---------|-------------|
| **Enable blacklist** | Block any IP matching a blacklist rule. |
| **Enable whitelist** | Restrict access to IPs matching a whitelist rule only. |
| **IP detection mode** | `Direct access` or `Reverse proxy / WAF`. |
| **Proxy header** | Header to read the client IP from (`X-Forwarded-For` or `X-Real-IP`). Active only in reverse proxy mode. |
| **Trusted proxies** | One IPv4 or CIDR per line. Only proxies in this list have their headers trusted. |

Click **Save network settings** to apply.

### Detected connection context

This panel shows the current values TeamPass has resolved for your session:

| Field | Description |
|-------|-------------|
| **Detected client IP** | The IP that will be evaluated against ACL rules. |
| **Client remote address** | The raw `REMOTE_ADDR` value (direct TCP peer). |
| **Server IPv4** | The IPv4 resolved for the TeamPass server itself. |
| **IP detection mode** | Active mode (direct / reverse proxy). |
| **Proxy header** | Header currently used for IP extraction. |
| **Trusted proxy used** | Whether `REMOTE_ADDR` matched a trusted proxy entry. |

Use the **Add current detected IP to whitelist** and **Add server IPv4 to whitelist** buttons as a safety net before enabling the whitelist.

---

## Managing rules

### Supported formats

| Format | Example | Matches |
|--------|---------|---------|
| Single IPv4 | `192.168.1.10` | Exact address only |
| IPv4 CIDR | `10.0.0.0/24` | All addresses in the subnet |

Only IPv4 is supported. IPv6 addresses are not accepted.

> :bulb: CIDR ranges are automatically normalized (e.g. `10.0.1.5/24` is stored as `10.0.1.0/24`).

### Adding a rule

1. In the **Whitelist rules** or **Blacklist rules** card, fill in the **IPv4 / CIDR rule** field.
2. Optionally add a **Comment** to describe the purpose of the rule.
3. Check or uncheck **Rule enabled** (enabled by default).
4. Click **Save rule**.

The rule table refreshes automatically.

### Editing a rule

Click the **edit** button (pencil icon) on any row. The form above the table is populated with the rule values. Modify the fields and click **Save rule** to update.

### Enabling / disabling a rule

Click the **toggle** button on a rule row to enable or disable it without deleting it. Disabled rules are ignored during evaluation.

### Deleting a rule

Click the **delete** button (trash icon) on a rule row. The deletion is immediate and cannot be undone.

---

## Reverse proxy setup

### Single proxy

If TeamPass is behind one reverse proxy:

1. Set **IP detection mode** to `Reverse proxy / WAF`.
2. Set **Proxy header** to match what your proxy injects (`X-Forwarded-For` is the most common).
3. Add your proxy's IP in **Trusted proxies** (e.g. `192.168.0.1`).

### Multiple proxies / CDN

List all proxy IPs or their CIDR in **Trusted proxies**, one per line:

```
10.0.0.1
10.0.0.2
192.0.2.10
```

TeamPass reads `X-Forwarded-For` from right to left, skips declared trusted
proxies and uses the first non-trusted address. Values before that address
cannot select the client IP. Declare intermediary proxies only, never an
entire client network. Include the outgoing ranges published by your CDN or WAF
provider too; otherwise that undeclared intermediary becomes the detected client
and clients behind it share its address for filtering and rate limits.

If every hop is declared trusted, TeamPass uses the rightmost header entry,
written by the trusted peer itself. Earlier client-supplied entries cannot choose
this fallback. Prefer specific proxy addresses to broad ranges that include users.
A missing header or an invalid/IPv6 hop encountered during resolution yields no
usable client address: an enabled ACL then refuses access rather than using
`REMOTE_ADDR` as the client. `X-Real-IP` accepts one IPv4 address, not a chain.

At a single Internet-facing Nginx proxy, overwrite client-supplied headers:

```nginx
proxy_set_header X-Forwarded-For $remote_addr;
proxy_set_header X-Real-IP $remote_addr;
```

For several proxies, the edge must remove untrusted incoming forwarding
headers; subsequent trusted proxies can append their observed peer. Keep the
backend reachable only from the intended proxies. Test the resulting client
address before enabling or changing a whitelist.

### HTTPS terminated by the proxy

In `Reverse proxy / WAF` mode, a request coming from a trusted proxy with `X-Forwarded-Proto: https` is treated as HTTPS for the session cookie, which then gets the `Secure` attribute. Make the proxy overwrite that header with its own scheme (`proxy_set_header X-Forwarded-Proto $scheme;` on Nginx). The header is ignored in `Direct` mode and from any other address, and it can only add the attribute, never remove it.

### Security note

Never add untrusted IP ranges to **Trusted proxies**. A client that controls a trusted-proxy header can forge any source IP and bypass blacklist rules.

---

## Common scenarios

### Block a known attacker

1. Enable the **blacklist** in the general settings.
2. Add the attacker's IP (e.g. `203.0.113.42`) to the **Blacklist rules**.
3. Save. The IP is blocked immediately.

### Restrict access to your office network only

1. Click **Add current detected IP to whitelist** to ensure your current IP is included.
2. Add your office CIDR (e.g. `198.51.100.0/22`) to the **Whitelist rules**.
3. Optionally add the server IP via **Add server IPv4 to whitelist** (required if TeamPass runs scheduled background tasks that make local HTTP requests).
4. Enable the **whitelist** and save.

### Temporarily disable filtering

Uncheck **Enable blacklist** and/or **Enable whitelist** in the general settings and save. Rules are preserved but not evaluated.

---

## Troubleshooting

### I am locked out after enabling the whitelist

If you can still reach the database, run this query to disable the whitelist:

```sql
UPDATE teampass_misc SET valeur = '0'
WHERE type = 'admin' AND intitule = 'network_whitelist_enabled';
```

Then clear the APCu cache (restart PHP-FPM or run `php -r "apcu_clear_cache();"`) so the change takes effect immediately.

### My IP is not detected correctly behind a proxy

- Check that **IP detection mode** is set to `Reverse proxy / WAF`.
- Check that your proxy's IP appears in **Trusted proxies**.
- Verify the header your proxy actually injects (it may be `X-Real-IP` instead of `X-Forwarded-For`).
- Use the **Detected connection context** panel to inspect what TeamPass currently sees.

### Rules are not applied after saving

TeamPass caches settings in APCu for 60 seconds. If you have APCu enabled, the new rules may take up to one minute to be picked up by existing PHP workers.

### Access is refused after an ACL storage error

When either list is enabled, TeamPass refuses access if its ACL table is missing
or the rules cannot be read. A storage failure never disables an enabled policy.
The response does not disclose database details; the PHP error log records a
generic ACL diagnostic without SQL or credentials. Check database permissions
and complete any required installation/upgrade before reopening the application.
When both lists are disabled, no ACL table is required.
Policy refusals return HTTP 403; unavailable enabled ACL storage returns HTTP
503. API and AJAX responses contain a generic JSON error, without the rules.
