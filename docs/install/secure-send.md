<!-- docs/install/secure-send.md -->

## Secure Send deployment and hardening

Secure Send, also known as One-Time View (OTV), lets a Teampass user share an
encrypted copy of an item or an ad-hoc secret with someone who does not have a
Teampass account. The recipient opens a time-limited link, confirms the reveal
and, when configured, enters a separate passphrase.

This guide explains how to expose that recipient flow without exposing the
complete Teampass application on the same public address. It also covers the
operational controls that are needed around the application: TLS, reverse
proxies, logging, sessions, rate limiting, validation and link-handling policy.

> :warning: **Version baseline:** this guide targets the application behaviour
> introduced in Teampass 3.2.2.6. Do not expose Secure
> Send from an older release. Re-run the validation checklist after every
> Teampass upgrade because the public route or its static assets may change.

---

## 1. Security objective

Use two distinct addresses:

```text
Authenticated Teampass application:
https://vault.example.com

Public Secure Send recipient endpoint:
https://secure.example.com
```

The public address should serve only:

```text
GET  /index.php?otv=1&code=<alphanumeric>&key=<alphanumeric>&stamp=<digits>
POST /index.php?otv=1
GET  /plugins/adminlte/css/adminlte.min.css
HEAD /plugins/adminlte/css/adminlte.min.css
```

Match the raw query string in that exact order and case. A looser test for the
presence of an `otv` parameter can disagree with PHP when a name is encoded,
repeated or uses a different case, and can expose the normal login page on the
public hostname.

Everything else, including `/`, plain `/index.php`, `/api/`, `/install/` and
`/ws/`, should be denied by the web server or reverse proxy.

This separation provides defence in depth. It does not create a separate
Teampass application or cryptographic trust boundary: the recipient endpoint
still executes Teampass code and reads the same database and encryption key.

### Recommended architecture

```text
Internet
   |
   v
secure.example.com:443
   |
   v
Dedicated virtual host or reverse proxy
   |  allow: Secure Send route and required CSS only
   |  deny: every other path
   v
Teampass public/ directory or private application backend

vault.example.com:443
   |
   v
Full Teampass application, restricted as required by the organisation
```

Use standard HTTPS on TCP/443 when possible. A non-standard port such as 9443
can separate listeners on one host, but it is not a security control and is
often blocked by customer, guest and enterprise networks.

---

## 2. Understand the application security model

Teampass 3.2.2.6 applies the following rules:

- A GET request never reveals the secret or consumes a view. It displays a
  confirmation form and creates a random, session-bound, single-use token.
- The secret is revealed only after a valid confirmation POST.
- Link views, failed passphrase attempts and item automatic-deletion counters
  are updated transactionally.
- Five incorrect key or passphrase attempts destroy the link.
- New item links contain an encrypted snapshot of the label, login, URL,
  description and password at creation time.
- The sender's current access to an active item is checked when the link is
  created and again when it is redeemed. Disabling or deleting the sender,
  deleting the item or removing the sender's access blocks the link.
- Administrator status alone does not grant access to item content for creating
  a link; the sender must hold the normal item and folder permissions.
- An optional TOTP profile can be copied into an item snapshot. The recipient
  receives generated short-lived codes, never the seed itself.
- Public links are accepted only on the hostname currently configured as the
  public sharing address. Internal links intentionally retain compatibility
  with aliases and proxy rewrites.
- Responses prohibit caching and referrer transmission, deny framing and
  include a restrictive Content Security Policy.

### What Secure Send does not guarantee

- **It is not end-to-end or zero-knowledge encryption.** Teampass decrypts the
  snapshot on the server when the recipient confirms the reveal.
- A link without a passphrase is a bearer credential. Anyone who obtains it can
  open the confirmation page and submit the reveal form.
- Secure Send does not authenticate a named recipient or prevent forwarding.
  Possession of the link and, when enabled, its passphrase is the authorisation.
- Confirmation prevents ordinary link previews from consuming a view, but it
  does not make a leaked URL harmless. A scanner or attacker may retain the URL
  and a capable automated client may submit the form.
- Revocation cannot erase content that a recipient has already viewed, copied,
  photographed or stored.
- A dedicated hostname and certificate do not provide anonymity. DNS, IP
  addresses, Certificate Transparency records and infrastructure metadata can
  still correlate the public service with the organisation.
- Expiration prevents redemption but does not promise immediate physical
  deletion of the encrypted database row. Treat expiration, revocation and data
  retention as separate controls.

---

## 3. Choose an administrative policy first

Configure policy before allowing users to issue public links.

| Setting | Recommended public-sharing baseline | Reason |
|---------|-------------------------------------|--------|
| **Enable One-Time View** | Enable only after infrastructure validation | Avoid issuing links before the public endpoint is ready |
| **OTV expiration period** | Use the shortest practical organisational maximum | Reduces the window in which a leaked URL is useful |
| **Public sharing address** | Explicit absolute HTTPS URL | Avoids ambiguity around hostname, port and installation path |
| **Secure Send maximum number of views per link** | `1` unless a documented use case requires more | Preserves the one-time property |
| **Force a passphrase on every Secure Send link** | Enabled for Internet-facing use | A URL leak alone is not sufficient to reveal the content |
| **Allow sending ad-hoc notes/secrets** | Disabled unless explicitly required | Notes are not governed by an item's folder and restriction lifecycle |

Example public address:

```text
https://secure.example.com
```

The sender must still select **Use public address** when generating a link.
The option is deliberately unchecked each time the Secure Send form opens. If
it is not selected, Teampass generates a link on the normal application address.

Changing the configured public hostname also changes where existing public
links may be redeemed. Revoke or regenerate active links as part of such a
change.

### Passphrase policy

Require a unique, randomly generated passphrase or a sufficiently long random
word sequence. Do not reuse an account password, API key or other production
secret as the passphrase.

Send the link and passphrase through different channels. For example, send the
link by email and communicate the passphrase by telephone or an approved secure
messaging channel. Two messages in the same mailbox, ticket or chat thread do
not provide meaningful channel separation.

### TOTP policy

Do not include TOTP by default. Sending an account password and its current TOTP
codes to the same recipient and through the same mechanism weakens the intended
separation of authentication factors.

TOTP is copied into the encrypted snapshot. Changing or disabling the item's
TOTP configuration later does not modify existing links. Revoke those links
and generate new ones whenever the copied TOTP profile should no longer be
usable.

---

## 4. Prepare DNS and TLS

Create an `A` record for the Secure Send hostname. Add an `AAAA` record only
when IPv6 is intentionally routed and protected by an equivalent firewall
policy.

Use a trusted certificate that covers the public hostname. A certificate
containing only `secure.example.com` avoids disclosing the vault hostname in
that certificate's Subject Alternative Name list, but a shared certificate is
not by itself a Secure Send vulnerability when both names are already public.

Automate renewal and reload the web server only after its configuration passes
validation. DNS-01 is convenient for a dedicated or non-standard listener
because it does not require exposing an application route. HTTP-01 is also
possible, but it requires an explicit exception for the ACME challenge path on
TCP/80.

Use current TLS policy for the organisation, normally TLS 1.2 and TLS 1.3.
Enable HSTS only after HTTPS has been validated. Do not add `includeSubDomains`
unless every affected subdomain is ready for permanent HTTPS enforcement.

When a non-standard HTTPS port is unavoidable, include it explicitly in the
public sharing address and in the listener, firewall, NAT and monitoring
configuration. The certificate still validates the hostname, not the port.
Test recipient connectivity from the external networks that will use the link.

> :warning: Teampass generates public Secure Send links with HTTPS. If a
> complete link is sent to an HTTP endpoint, its bearer parameters have already
> crossed the network in clear text before any redirect can occur. Never rely
> on an HTTP-to-HTTPS redirect to protect a Secure Send URL.

---

## 5. Apache 2.4 direct deployment

This example serves Teampass directly from Apache and assumes that the public
address has no path prefix. The authenticated application and Secure Send use
separate virtual hosts.

Adjust the filesystem path, certificate path and PHP integration to the local
installation. Reuse the normal virtual host's PHP handler only; do not copy its
front-controller, API, WebSocket or proxy rules.

### Safe access-log format

Define a log format that uses `%U`, which records the path without the query
string. Do not use `combined`, `%r` or `%q` for this virtual host.

```apache
LogFormat "%a %t \"%m %U %H\" %>s %B" secure_send
```

`%B` is used instead of `%O` so the example does not require `mod_logio`.

### HTTPS virtual host

```apache
<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName secure.example.com

    DocumentRoot /var/www/teampass/public
    DirectoryIndex disabled
    ErrorDocument 404 "Not Found"

    <Directory /var/www/teampass/public>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>

    SSLEngine On
    SSLCertificateFile /etc/letsencrypt/live/secure.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/secure.example.com/privkey.pem

    RewriteEngine On

    # Reject an unexpected Host value.
    RewriteCond %{HTTP_HOST} !^secure\.example\.com(?::443)?$ [NC]
    RewriteRule ^ - [R=404,L]

    # Serve only the stylesheet currently required by the recipient page.
    RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$
    RewriteRule ^/?plugins/adminlte/css/adminlte\.min\.css$ - [L]

    # Accept only the exact query shapes generated by Teampass. Do not make
    # this match case-insensitive or replace it with an "otv exists" test.
    RewriteCond %{REQUEST_METHOD} =GET
    RewriteCond %{QUERY_STRING} ^otv=1&code=[A-Za-z0-9]{1,100}&key=[A-Za-z0-9]{1,1024}&stamp=[0-9]{1,20}$
    RewriteRule ^/?index\.php$ - [L]

    RewriteCond %{REQUEST_METHOD} =POST
    RewriteCond %{QUERY_STRING} =otv=1
    RewriteRule ^/?index\.php$ - [L]

    # Deny the full application and every unrecognised resource.
    RewriteRule ^ - [R=404,L]

    CustomLog ${APACHE_LOG_DIR}/secure-send-access.log secure_send
    ErrorLog ${APACHE_LOG_DIR}/secure-send-error.log

    Header always set X-Content-Type-Options "nosniff"

    # Reuse the installation's tested PHP handler when it is not global.
    # Example only; the socket and handler can differ:
    # <Files "index.php">
    #     SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost/"
    # </Files>
</VirtualHost>
</IfModule>
```

### Why `.htaccess` is disabled here

The normal Teampass virtual host needs `AllowOverride All` because its
`.htaccess` files implement the complete application routing and protections.
The dedicated Secure Send host is different: its explicit virtual-host rules
replace that routing, so `AllowOverride None` prevents an unrelated or future
front-controller rule from widening the public surface.

This is safe only with the deny-by-default allowlist above. Do not apply the
same setting to the normal Teampass virtual host.

### Optional HTTP listener

The safest simple design is not to publish TCP/80 for this hostname. If TCP/80
is required for general navigation or ACME, never redirect a request that
already contains a query string:

```apache
<VirtualHost *:80>
    ServerName secure.example.com
    RewriteEngine On

    # A query may contain a Secure Send bearer secret. Do not forward it.
    RewriteCond %{QUERY_STRING} !^$
    RewriteRule ^ - [R=404,L]

    RewriteRule ^ https://secure.example.com%{REQUEST_URI} [R=301,L,NE]
</VirtualHost>
```

If HTTP-01 is used, add only the exact ACME challenge location required by the
chosen client and verify that it cannot reach Teampass.

### Apache validation commands

On Debian and Ubuntu:

```bash
sudo a2enmod ssl rewrite headers
sudo a2ensite secure.example.com.conf
sudo apache2ctl configtest
sudo apache2ctl -S
sudo systemctl reload apache2
```

When the reused handler is PHP-FPM through `proxy_fcgi`, also enable the Apache
modules required by that handler (commonly `proxy_fcgi` and `setenvif`). Do not
change a working PHP integration only for the Secure Send virtual host.

On RHEL, Rocky Linux, AlmaLinux and CentOS Stream, configurations normally live
under `/etc/httpd/conf.d/`:

```bash
sudo httpd -t
sudo httpd -S
sudo systemctl reload httpd
```

When SELinux is enforcing, preserve the normal Teampass file contexts and allow
only the backend connections actually required by the selected PHP-FPM or proxy
architecture. Do not disable SELinux to make the virtual host work.

---

## 6. Nginx with PHP-FPM

Nginx does not process `.htaccess`; all restrictions must be present in the
server configuration. Use exact locations and expose no generic `location ~
\.php$` block on the Secure Send hostname.

The `log_format` and `map` directives belong in the `http` context, normally in
`nginx.conf` or an included file. The map accepts only the exact raw query
strings generated by Teampass:

```nginx
log_format secure_send '$remote_addr [$time_local] '
                       '"$request_method $uri $server_protocol" '
                       '$status $body_bytes_sent';

map "$request_method:$args" $secure_send_route {
    default 0;
    "~^GET:otv=1&code=[A-Za-z0-9]{1,100}&key=[A-Za-z0-9]{1,1024}&stamp=[0-9]{1,20}$" 1;
    "~^POST:otv=1$" 1;
}
```

Keep both entries as `~` regular expressions. Nginx compares plain string keys
in a `map` case-insensitively, so a literal `"POST:otv=1"` key would also accept
`?OTV=1`, which PHP does not route to the recipient page. `~*` has the same
problem.

This uses `$uri`, which excludes the query string. Do not use `$request` or
`$request_uri` in a Secure Send access log.

### HTTPS server

```nginx
server {
    listen 443 ssl;
    server_name secure.example.com;

    root /var/www/teampass/public;

    ssl_certificate     /etc/letsencrypt/live/secure.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/secure.example.com/privkey.pem;

    access_log /var/log/nginx/secure-send-access.log secure_send;
    error_log  /var/log/nginx/secure-send-error.log;

    add_header X-Content-Type-Options "nosniff" always;

    location = /plugins/adminlte/css/adminlte.min.css {
        limit_except GET {
            deny all;
        }
        try_files $uri =404;
    }

    location = /index.php {
        # Returning a status from an Nginx if block is safe in this context.
        if ($secure_send_route = 0) {
            return 404;
        }

        limit_except GET POST {
            deny all;
        }

        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param HTTPS on;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location / {
        return 404;
    }
}
```

Add `listen [::]:443 ssl;` only when the hostname has an intentional IPv6 route
and the IPv6 firewall and validation are equivalent to IPv4.

The PHP-FPM socket differs by distribution and PHP version. It is commonly
owned by `www-data` on Debian/Ubuntu and served through an `apache` or `nginx`
account on RHEL-family systems. Use the already tested pool and socket from the
normal Teampass server block.

### Deny unknown hostnames

When the listener serves more than this one hostname, configure a default
server that does not route to Teampass:

```nginx
server {
    listen 443 ssl default_server;
    server_name _;

    ssl_certificate     /etc/letsencrypt/live/secure.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/secure.example.com/privkey.pem;

    return 404;
}
```

On Nginx versions and architectures where rejecting the TLS handshake is
appropriate, `ssl_reject_handshake on` can replace the certificate and response
in the default server. Test compatibility with health checks and monitoring
before using it.

Validate and reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## 7. Reverse proxy and Docker deployments

For the official Docker image or any architecture where the Teampass backend
must not receive Internet connections directly, apply the allowlist at a
dedicated edge proxy:

```text
Internet
   -> dedicated Secure Send virtual host on the proxy
   -> private container network or backend address
   -> Teampass
```

Bind the container port to a private interface or container network, not to all
host interfaces. Restrict the backend firewall so only the proxy can connect.

### Nginx reverse-proxy example

Use both the query-free `secure_send` log format and the
`$secure_send_route` map shown above in the edge Nginx `http` context.

```nginx
server {
    listen 443 ssl;
    server_name secure.example.com;

    ssl_certificate     /etc/letsencrypt/live/secure.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/secure.example.com/privkey.pem;

    access_log /var/log/nginx/secure-send-access.log secure_send;
    error_log  /var/log/nginx/secure-send-error.log;

    location = /plugins/adminlte/css/adminlte.min.css {
        limit_except GET {
            deny all;
        }
        proxy_pass http://teampass_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location = /index.php {
        if ($secure_send_route = 0) {
            return 404;
        }

        limit_except GET POST {
            deny all;
        }

        proxy_pass http://teampass_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;

        proxy_no_cache 1;
        proxy_cache_bypass 1;
    }

    location / {
        return 404;
    }
}
```

The public `Host` header must reach Teampass because public links are bound to
the configured hostname. Do not replace it with the container name or an
internal backend hostname.

### Official Docker image backend safeguards

Images released after 3.2.2.6 need no change for Secure Send. The Nginx process
inside the container logs the request path without its query string and without
the `Referer` header, and it no longer overrides the `Referrer-Policy:
no-referrer` header that the recipient page sends.

The 3.2.2.6 image and earlier releases have two defects; upgrading is the
simplest fix:

- `docker/nginx/teampass.conf`, copied to `/etc/nginx/http.d/default.conf`,
  has a server-level `access_log` that does not name a format, so Nginx uses its
  built-in `combined` format and records the complete request line, including
  `code`, `key` and `stamp`. A safe edge log does not protect this internal log.
- The image's `nginx.conf` adds a second `Referrer-Policy:
  strict-origin-when-cross-origin` header. Browsers apply the last one, so the
  confirmation POST carries the complete link in its `Referer` header. Keep the
  `Referer` header out of every edge log; the `secure_send` format above
  already omits it.

If the image cannot be upgraded yet, make a reviewed copy of that release's
complete `teampass.conf` and apply one of these controls to the internal log.
The preferred option keeps useful request metadata without the query string.

Add a format before the existing `server` block; the file is included from the
Nginx `http` context:

```nginx
log_format teampass_no_query '$remote_addr [$time_local] '
                             '"$request_method $uri $server_protocol" '
                             '$status $body_bytes_sent';
```

Then replace the existing server-level log directive with:

```nginx
access_log /var/log/nginx/teampass-access.log teampass_no_query;
```

If an edge log already provides the required evidence, `access_log off;` is a
safer alternative. Do not merely remove the server directive: the image's
parent `http` configuration also defines a query-bearing access log.

Provide the complete adjusted file through a derived image or a read-only bind
mount at `/etc/nginx/http.d/default.conf`. Run `nginx -t` inside the container
before restarting it, and remove the override once the image is upgraded.

### TLS termination and the `Secure` session cookie

The session cookie receives `Secure` when PHP sees `HTTPS=on`. In releases
after 3.2.2.6 it also receives it when the request comes from a declared
trusted proxy that sends `X-Forwarded-Proto: https`:

1. Under **Settings → Options → Networks**, set **IP detection mode** to
   **Reverse proxy / WAF**.
2. In **Trusted proxies**, declare the IPv4 address or CIDR that the Teampass
   backend sees for the proxy. With the official Docker image, that is the
   address of the proxy as seen from the container, typically the proxy
   container or the Docker network gateway, not the public address.
3. Make the proxy overwrite the header with its own scheme, for example
   `proxy_set_header X-Forwarded-Proto $scheme;`.

In any other mode, or from any other source address, the header is ignored. It
can only add the `Secure` attribute, never remove it.

On 3.2.2.6 and earlier, or without that mode, make PHP see `HTTPS=on` itself.
For an Nginx-to-PHP-FPM backend, set `fastcgi_param HTTPS on;` as shown in the
direct example. In the official container, add it after
`include fastcgi_params;` in the PHP location of the reviewed `teampass.conf`
copy, and only when the container is reachable solely from the trusted TLS
terminator. For an Apache backend, set the variable only when both the source
address and proxy-supplied scheme identify the trusted TLS terminator, for
example:

```apache
SetEnvIfExpr "-R '192.0.2.10/32' && req('X-Forwarded-Proto') == 'https'" HTTPS=on
```

Replace the example address with the real proxy address or CIDR and prevent
direct client access to the backend. Never trust `X-Forwarded-Proto` from an
arbitrary source. Ensure Apache's `setenvif` module is enabled before using this
directive.

### Controls required on every proxy hop

- Do not log query strings, request bodies, `Cookie` headers or hidden form
  fields. The POST body contains the link credentials, confirmation token and
  optional passphrase.
- Disable caching for `/index.php`. Do not put the recipient page behind a CDN
  cache or a proxy that ignores the application's `Cache-Control: no-store`.
- Preserve GET and POST bodies and do not redirect the Secure Send request to
  the private vault hostname.
- Under **Settings → Options → Networks**, set **IP detection mode** to
  **Reverse proxy / WAF** and declare only the real proxy addresses as
  **Trusted proxies**. This protects client-IP detection and, in releases after
  3.2.2.6, lets the proxy's `X-Forwarded-Proto` mark the session cookie
  `Secure`. See [Network ACL — Reverse proxy setup](../manage/network-acl.md#reverse-proxy-setup).
- Configure the trusted backend transport as described above, then verify that
  the session cookie carries the `Secure`, `HttpOnly` and `SameSite=Lax`
  attributes.
- Do not rewrite the session cookie onto a broad parent domain such as
  `.example.com`. Keep the default host-only boundary between the public sharing
  host and the authenticated vault host.
- Inspect the backend web-server log as well as the edge log. A safe edge log
  does not prevent the Nginx or Apache process inside a container from logging
  the forwarded query string.

### Sessions and high availability

The GET confirmation token is stored in the recipient's PHP session and is
consumed by the POST. With more than one Teampass backend, use encrypted Redis
sessions or reliable session affinity. Database row locking protects view
counts, but it does not make a filesystem session available on another node.

See [Redis session storage](performance.md#4-redis-session-storage). Monitor it
as an availability dependency: Teampass can fall back to local filesystem
sessions silently on any request where the Redis connection fails, but that
fallback no longer provides cross-node GET-to-POST continuity.

Test failover between the GET and POST steps. A deployment that intermittently
shows **The confirmation has expired** is not ready for production.

The same invariants apply to HAProxy, Traefik, Caddy, cloud load balancers and
WAF products even when their configuration syntax differs.

---

## 8. Installation paths and operating-system differences

Teampass is documented for GNU/Linux and Docker. The security behaviour is
defined mainly by the web server and PHP configuration, not by the distribution.

| Area | Debian / Ubuntu | RHEL / Rocky / AlmaLinux / CentOS Stream | Official container |
|------|-------------------|-------------------------------------------|--------------------|
| Apache service | `apache2` | `httpd` | Not normally used |
| Apache site configuration | `/etc/apache2/sites-available/` | `/etc/httpd/conf.d/` | Not applicable |
| Apache validation | `apache2ctl configtest` | `httpd -t` | Not applicable |
| Common web account | `www-data` | `apache` or `nginx` | `nginx` |
| Common Nginx configuration | `/etc/nginx/sites-available/` or `conf.d/` | `/etc/nginx/conf.d/` | `/etc/nginx/http.d/default.conf` |
| PHP-FPM socket | `/run/php/php<VERSION>-fpm.sock` | `/run/php-fpm/www.sock` or pool-specific | Internal image socket/service |
| Mandatory access control | AppArmor where configured | SELinux commonly enforcing | Host and container runtime policy |
| Service reload | `systemctl reload apache2` or `nginx` | `systemctl reload httpd` or `nginx` | Proxy reload or container-safe reload procedure |

Do not copy package names, service accounts, sockets or SELinux commands from a
different distribution without verifying them locally. Never disable SELinux,
AppArmor or the host firewall merely to make Secure Send reachable.

For all deployment types:

- point the web root at Teampass `public/`, never the repository root;
- keep `app/`, `storage/` and `secrets/` outside the web root;
- keep application code read-only to the web account;
- expose neither the database nor internal WebSocket listener publicly;
- keep the encryption key and configuration volumes persistent in Docker.

See [Installation](installation.md), [Docker](docker.md) and
[File permissions](file-permissions.md) for the platform-specific baseline.

---

## 9. Subdirectory installations

An explicit public address may include a path:

```text
https://secure.example.com/teampass
```

The generated endpoint then becomes:

```text
/teampass/index.php?otv=1&code=<alphanumeric>&key=<alphanumeric>&stamp=<digits>
```

The root-level Apache and Nginx examples on this page do not cover that layout.
For the smallest and least error-prone public surface, prefer a dedicated
hostname whose Secure Send base is `/`.

When a path prefix is required, adjust all of these together:

- the Teampass public sharing address;
- the exact OTV route;
- the exact stylesheet route;
- the filesystem alias or proxy path mapping;
- the confirmation POST destination;
- the validation matrix.

Do not remove the prefix before forwarding unless the backend mapping has been
tested to reconstruct the exact Teampass route. A successful CSS request alone
does not prove that GET, session cookie and confirmation POST paths are correct.

---

## 10. Logging, monitoring and privacy

Secure Send URLs contain bearer-like credentials in the query string. The same
values are submitted as hidden POST fields during confirmation. A passphrase,
when present, is also in that POST body.

Review all of the following:

- edge proxy, load-balancer and WAF access and audit logs;
- backend Apache or Nginx access logs;
- PHP-FPM and application error logs;
- APM traces and request-capture tools;
- container stdout/stderr collection;
- SIEM pipelines and packet-capture tooling;
- support bundles and health dashboards.

Record the path, method, status, response size and a privacy-appropriate client
identifier. Do not record the query string, request body, cookies, passphrase,
decrypted content or link secret. Apply access controls and a defined retention
period to the remaining logs because client IP addresses can be personal data.

### Prove that the query is absent

Use a fake marker, never a real Secure Send URL:

```bash
curl -sS -o /dev/null \
  'https://secure.example.com/index.php?otv=1&code=TESTSECRETMUSTNOTBELOGGED&key=TEST&stamp=1'
```

Search every relevant logging layer for the marker. The request path may appear;
`TESTSECRETMUSTNOTBELOGGED` must not. The marker is alphanumeric so the request
matches the edge allowlist and reaches every backend logging layer.

### Rate limiting

Teampass destroys one link after five incorrect key or passphrase attempts. This
is per-link protection, not a general IP rate limit. Add conservative rate
limiting at the reverse proxy or WAF when appropriate, but account for recipients
behind shared corporate NAT addresses and for the normal GET-plus-POST flow.

For Nginx, an illustrative starting point is to declare a zone in the `http`
context and apply it only to the exact `/index.php` location:

```nginx
# http context; tune from observed legitimate traffic.
limit_req_zone $binary_remote_addr zone=secure_send_per_ip:10m rate=30r/m;

# Inside location = /index.php:
limit_req zone=secure_send_per_ip burst=20 nodelay;
limit_req_status 429;
```

These values are not universal. When Nginx is behind another proxy,
`$binary_remote_addr` is useful only after the real-IP module trusts the exact
proxy addresses and rejects client-supplied forwarding headers from anywhere
else. Otherwise the limit may group every recipient under the proxy address or
become trivial to bypass.

Monitor repeated invalid requests, unexpected methods, spikes in traffic,
certificate-renewal failures, newly exposed listeners and changes to the
allowlist. Never include the submitted URL or body in an alert.

---

## 11. Validation before exposure

Perform local validation before opening firewall or NAT rules. `curl --resolve`
tests the intended TLS SNI and HTTP `Host` value without depending on public DNS.

```bash
HOST=secure.example.com
IP=127.0.0.1
OTV_QUERY='otv=1&code=TEST&key=TEST&stamp=1'
```

Adapt the port when using a non-standard listener.

```bash
curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'ROOT: %{http_code}\n' \
  https://${HOST}/

curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'INDEX: %{http_code}\n' \
  https://${HOST}/index.php

curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'ADMIN: %{http_code}\n' \
  'https://'${HOST}'/index.php?page=admin'

curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'API: %{http_code}\n' \
  https://${HOST}/api/

curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'INSTALL: %{http_code}\n' \
  https://${HOST}/install/

curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'WS: %{http_code}\n' \
  https://${HOST}/ws/

curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'CSS: %{http_code}\n' \
  https://${HOST}/plugins/adminlte/css/adminlte.min.css

curl --resolve ${HOST}:443:${IP} -sS -I -o /dev/null -w 'CSS_HEAD: %{http_code}\n' \
  https://${HOST}/plugins/adminlte/css/adminlte.min.css

curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'OTV: %{http_code}\n' \
  "https://${HOST}/index.php?${OTV_QUERY}"

curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'OTV_BARE: %{http_code}\n' \
  "https://${HOST}/index.php?otv=1"

curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'OTV_CASE: %{http_code}\n' \
  "https://${HOST}/index.php?OTV=1"

curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'OTV_DUPLICATE: %{http_code}\n' \
  "https://${HOST}/index.php?otv=1&otv="

curl --resolve ${HOST}:443:${IP} -sS -o /dev/null -w 'OTV_ENCODED: %{http_code}\n' \
  "https://${HOST}/index.php?otv=1&%6Ftv="

curl --resolve ${HOST}:443:${IP} -sS -X POST -o /dev/null \
  -w 'OTV_POST_CASE: %{http_code}\n' "https://${HOST}/index.php?OTV=1"

curl --resolve ${HOST}:443:${IP} -sS -X PUT -o /dev/null \
  -w 'METHOD: %{http_code}\n' "https://${HOST}/index.php?${OTV_QUERY}"

curl --resolve ${HOST}:443:${IP} -sS -H 'Host: unexpected.example.com' \
  -o /dev/null -w 'HOST: %{http_code}\n' "https://${HOST}/index.php?${OTV_QUERY}"
```

Expected matrix:

| Request | Expected result |
|---------|-----------------|
| `/` | 404 |
| `/index.php` | 404 |
| `/index.php?page=admin` | 404 |
| `/api/` | 404 |
| `/install/` | 404 |
| `/ws/` | 404 |
| required stylesheet | 200 for GET/HEAD |
| well-formed fake OTV query | generic invalid-link recipient page, normally 200 |
| bare `/index.php?otv=1` | 404 |
| uppercase `/index.php?OTV=1` | 404 |
| duplicate `otv=1&otv=` | 404 |
| encoded duplicate `otv=1&%6Ftv=` | 404 |
| uppercase POST `/index.php?OTV=1` | 404 |
| unexpected method on a well-formed query | 404 (Apache) or 403 (Nginx `limit_except`) |
| wrong hostname | denied; never the Teampass login page |

Only the well-formed fake query should reach TeamPass and show its generic
invalid-link recipient page. Every malformed shape must be rejected at the
edge without displaying the normal login page, PHP source or a stack trace.

### Verify certificate and response properties

```bash
echo | openssl s_client \
  -connect secure.example.com:443 \
  -servername secure.example.com 2>/dev/null \
  | openssl x509 -noout -subject -issuer -dates -ext subjectAltName

curl -sS -D - -o /dev/null \
  'https://secure.example.com/index.php?otv=1&code=TEST&key=TEST&stamp=1'
```

Check for:

- a valid certificate for the public hostname;
- `Cache-Control: no-store, private, max-age=0`;
- `Referrer-Policy: no-referrer`, as the only `Referrer-Policy` header;
- `X-Robots-Tag: noindex, nofollow, noarchive`;
- `X-Frame-Options: DENY`;
- the expected Content Security Policy;
- a session cookie marked `Secure`, `HttpOnly` and `SameSite=Lax`.

Also verify that `/index.php` is executed as PHP and is never returned as source
or downloaded. This is especially important after adding a new Apache virtual
host or PHP-FPM pool mapping.

### Validate from outside the network

Repeat the route matrix from a genuinely external network. Test both IPv4 and
IPv6 when an `AAAA` record exists. Confirm that the full Teampass application
listener remains unavailable from the Internet when that is part of the design.

---

## 12. End-to-end acceptance test

Use fictitious data, never a real production credential, for the first test.

1. Enable OTV and configure the absolute public HTTPS address.
2. Create a Secure Send link for a test item.
3. Select **Use public address** and confirm the generated hostname.
4. Set one view, a short validity period and a test passphrase.
5. Send the passphrase through a separate test channel.
6. Open the link in a private browser session from an external network.
7. Confirm that GET displays the reveal form but does not consume the view.
8. Submit an incorrect passphrase once and confirm that no view is consumed.
9. Submit the correct passphrase and confirm that one view is consumed.
10. Confirm that the next reveal is refused when the link had one permitted view.
11. Create another link, revoke it from **My secure sends** and confirm that it
    can no longer be used.
12. Confirm that direct navigation to the forbidden routes remains blocked.
13. Search every logging layer for the fake link parameters and passphrase.
14. Repeat with and without an item TOTP profile when TOTP sharing is allowed.
15. If item automatic deletion is enabled, test its interaction with Secure Send
    on a disposable item.

Do not paste a real Secure Send URL into a shell command: it may enter shell
history and can be visible briefly in the process list.

---

## 13. Sender and recipient good practices

### Sender

- Confirm the address preview before generating the link.
- Use the public route only for recipients who need it.
- Keep the lifetime and view count as low as practical.
- Use a unique passphrase and a genuinely separate delivery channel.
- Do not shorten the URL or paste it into a ticket, shared document or messaging
  system that previews, rewrites or records links unless that system is an
  explicitly accepted part of the delivery path.
- Include TOTP only when the recipient explicitly needs both factors.
- Remember that the link is a snapshot. Revoke and recreate it after changing
  the source password, URL, login, description or TOTP configuration.
- Review **My secure sends** and revoke links as soon as they are no longer
  needed.
- Rotate the shared account credential after temporary third-party access when
  the business process permits it.

### Recipient

- Verify the expected Secure Send hostname and valid TLS certificate before
  entering the passphrase.
- Do not forward the link or store it in a shared ticket, chat or document.
- Reveal the content only on a trusted device and network.
- Prefer a private browser session when browser history or account-based history
  synchronisation would otherwise retain the URL.
- Copy only the information needed and close the page when finished.
- Report an unexpected or already-consumed link to the sender rather than asking
  for the same link to be made reusable.

---

## 14. Operational limitations and governance

### Snapshots can become stale

Item fields are copied when the link is generated. Later item edits do not
update that ciphertext. Current access is still checked, but the recipient may
receive an older password or description if the sender does not revoke and
recreate the link.

### Notes have a different lifecycle

An ad-hoc Secure Send note is self-contained and is not attached to an item or
folder. It becomes unavailable when its sender is disabled or deleted, but it
does not inherit later item, folder or role changes. Keep notes disabled unless
that separate lifecycle is understood and required.

### Automatic deletion affects the source item

When automatic deletion after a number of consultations is enabled on an item,
a successful Secure Send reveal consumes that budget. The final permitted
reveal can make the source item inactive and invalidate every other link to it.
Test this behaviour before combining both features in production.

### Expiration is not immediate purge

Expired links cannot be redeemed. Teampass also removes expired records during
certain authenticated Secure Send operations, and users can revoke links from
their active-link list. Do not describe expiration as guaranteed immediate
database erasure. Apply the organisation's encrypted backup and database
retention policy accordingly. Anyone who holds both a database backup and the
corresponding Teampass encryption material may retain a recovery path to the
snapshot until those backups expire.

### Audit scope

After applying the Secure Send audit migration, Teampass retains a dedicated
metadata journal for creations, successful server-side reveals, confirmed
credential failures, owner revocations, observed access invalidations and expired
link cleanup. Each event keeps the internal sharing identifier, original sender,
observation time, source item identifier (if any), sharing type, protection policy
and counters. Creation/revocation identify the authenticated sender; anonymous
recipients are not identified. No shared content, item label, note title, link
code, key, passphrase or complete URL is stored in this journal.

Journal writes share the operation's transaction. If the journal cannot be
written, the operation fails without returning a usable new URL or decrypted
content. The existing OTV item audit and automatic-deletion records remain.
When the existing syslog option is enabled, structured events are forwarded after
commit using `action=secure_send` and JSON metadata. Forwarding is best effort;
configure and monitor the central collector separately.

The journal survives link cleanup and account/item deletion. It starts with
operations observed after upgrade: past creations and reveals cannot be rebuilt.
`created_at` preserves the original link timestamp, while `occurred_at` is when
an event was recorded. Expiration is audited when cleanup observes and removes
the row, not by a scheduler exactly at the deadline. Cleanup processes at most
100 expired links per authenticated creation/list operation; remaining expired
links stay unusable and are omitted from the active list. Invalidations due to
permission/account changes are recorded when a confirmed reveal observes them.

A cleanup error rolls back that batch and is logged without blocking an otherwise
authorized creation or listing. Creation/reveal/revocation still require their own
atomic audit writes. Expired historical rows with invalid link/sender identifiers
are retained for investigation, diagnosed without their contents and excluded
before the cleanup batch limit; they cannot prevent valid rows from being cleaned.

This first audit change has no statistics, Reports export or audit-purge interface.
There is no automatic audit retention: plan capacity, protect database and backup
access, and define the organisation's retention and archiving policy. SQL access
can still alter evidence; a protected external collector is a separate control.
A successful reveal event proves the server committed a reveal, not that a named
recipient received, read or copied its response. Before relying on this evidence
for a regulated workflow, assess these limits and the surrounding controls.

Secure Send is enabled globally. Current item access is rechecked, but
organisations that require a separate role allowlist for issuing links should
verify the available policy controls before enabling the feature.

---

## 15. Upgrade and change management

The allowlist is intentionally narrow. That reduces exposure but means a future
recipient page may require a new route or static asset.

After every Teampass upgrade that touches Secure Send:

1. read the release notes before changing the allowlist;
2. validate Apache or Nginx configuration before reloading;
3. run the complete forbidden-route matrix;
4. create and redeem a new fictitious link;
5. inspect browser developer tools for blocked assets;
6. confirm that access logs still omit query strings and POST bodies;
7. verify session-cookie attributes and GET-to-POST continuity;
8. test IPv4, IPv6 and every proxy hop that is actually deployed.

If the page becomes visually incomplete, identify the exact missing asset and
allow only that resource. Do not restore the normal Teampass front controller,
generic PHP execution, `.htaccess` routing or unrestricted proxying on the
public hostname.

Changes to DNS, certificate, listener, public address or base path can invalidate
existing links. Include active-link revocation and user communication in the
change plan.

---

## 16. Rollback

Prepare rollback before public exposure:

1. remove or disable the public firewall/NAT rule;
2. disable the dedicated Secure Send virtual host;
3. validate and reload the web server;
4. disable OTV or remove the public sharing address before issuing new links;
5. verify that the public hostname no longer reaches Teampass;
6. communicate that existing links are unavailable and revoke them when the
   service is restored if their confidentiality is uncertain.

Keep a version-controlled copy of the web-server configuration and record the
last successful route matrix. Never roll back Teampass application files while
newer `item_v2` links remain active unless the target release is known to read
that format.

---

## 17. Production checklist

- [ ] Teampass is version 3.2.2.6 or later and the upgrade wizard completed.
- [ ] OTV was enabled only after the public route passed validation.
- [ ] The public sharing address is an explicit HTTPS URL.
- [ ] Vault and Secure Send use separate hostnames.
- [ ] TCP/443 is used unless a non-standard port is justified and tested.
- [ ] The certificate is valid and automatic renewal is tested.
- [ ] Certificate renewal reloads the web server safely.
- [ ] The public virtual host uses a deny-by-default allowlist.
- [ ] `/`, `/index.php`, `/api/`, `/install/` and `/ws/` are denied publicly.
- [ ] Only the exact generated OTV GET query, confirmation POST and currently
      required static assets are allowed.
- [ ] Case-changed, duplicate and encoded `otv` parameter names are denied.
- [ ] Unexpected hostnames and HTTP methods are denied.
- [ ] The public hostname never displays the normal Teampass login page.
- [ ] PHP source can never be served or downloaded.
- [ ] Query strings and POST bodies are absent from every logging layer.
- [ ] With the official container up to 3.2.2.6, its internal Nginx log is
      overridden safely or disabled.
- [ ] No logging layer records the `Referer` header.
- [ ] Caching is disabled for the recipient page at every proxy and CDN layer.
- [ ] The public `Host` header is preserved to Teampass.
- [ ] Behind a TLS-terminating proxy, the backend treats a request as HTTPS only
      when it comes from that proxy: declared trusted proxy in
      **Reverse proxy / WAF** mode, or `HTTPS=on` set on the trusted path.
- [ ] The recipient cookie is `Secure`, `HttpOnly` and `SameSite=Lax`.
- [ ] Multi-node deployments share sessions or have tested affinity.
- [ ] The backend is reachable only from intended proxy or trusted networks.
- [ ] IPv6 exposure matches IPv4 policy.
- [ ] Passphrases are mandatory for the intended public use case.
- [ ] The default view and expiration limits match organisational policy.
- [ ] Ad-hoc notes and TOTP sharing were explicitly accepted or remain disabled.
- [ ] A fictitious external end-to-end test succeeded.
- [ ] Revocation, expiration and automatic deletion were tested.
- [ ] Upgrade regression testing and periodic active-link review are scheduled.

---

## References

- [Teampass 3.2.2.6 release](https://github.com/nilsteampassnet/TeamPass/releases/tag/3.2.2.6)
- [Teampass item and Secure Send behaviour](../features/items.md#secure-send)
- [Public sharing address](../manage/settings.md#public-sharing-address)
- [Teampass security hardening](security-hardening.md)
- [Apache request logging](https://httpd.apache.org/docs/2.4/mod/mod_log_config.html)
- [Apache DirectoryIndex](https://httpd.apache.org/docs/2.4/mod/mod_dir.html#directoryindex)
- [Apache SetEnvIf](https://httpd.apache.org/docs/2.4/mod/mod_setenvif.html)
- [Nginx request processing](https://nginx.org/en/docs/http/request_processing.html)
- [Nginx map module](https://nginx.org/en/docs/http/ngx_http_map_module.html)
- [Nginx access logging](https://nginx.org/en/docs/http/ngx_http_log_module.html)
- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)
- [Let's Encrypt challenge types](https://letsencrypt.org/docs/challenge-types/)
