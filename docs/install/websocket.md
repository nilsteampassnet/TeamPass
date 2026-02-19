<!-- docs/install/websocket.md -->


## WebSocket Server

TeamPass includes an optional WebSocket server that provides real-time notifications to connected users. When enabled, users receive instant updates when items or folders are created, modified or deleted by other users, without needing to refresh the page.

> :bulb: **Note:** The WebSocket feature is optional. TeamPass works perfectly without it. Enable it only if you need real-time collaboration features.


## 1. Prerequisites

### PHP extensions

The WebSocket server requires two additional PHP extensions:

* `pcntl`
* `posix`

These are **CLI-only extensions** (not available in Apache/FPM). You must check them from the command line:

```
php -m | grep -E "(pcntl|posix)"
```

If missing, install them (Debian/Ubuntu example):

```
sudo apt install php8.2-pcntl php8.2-posix
```

> :bulb: **Note:** Adapt the PHP version number to your environment.

### Web server modules

The WebSocket server binds to `127.0.0.1:8080` (local only). Your web server must proxy the `/ws` route to it.

**Apache** - enable the required modules:

```
sudo a2enmod proxy proxy_wstunnel rewrite
sudo systemctl restart apache2
```

**Nginx** - no extra module is needed (proxy support is built-in).


## 2. Reverse Proxy Configuration

### Apache

Add the following to your VirtualHost block (typically in your SSL vhost):

```apache
<VirtualHost *:443>
    # ... existing SSL configuration ...

    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/ws/?(.*)$ ws://127.0.0.1:8080/$1 [P,L]

    ProxyPass /ws/ ws://127.0.0.1:8080/
    ProxyPassReverse /ws/ ws://127.0.0.1:8080/
</VirtualHost>
```

Restart Apache:

```
sudo systemctl restart apache2
```

### Nginx

Add a `location` block inside your `server` block:

```nginx
location /ws {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 86400;
}
```

Reload Nginx:

```
sudo systemctl reload nginx
```


## 3. Enable WebSocket in TeamPass

1. Log in as Admin
2. Go to **Administration > Settings**
3. Locate the **WebSocket** section
4. Set **Enable WebSocket** to On
5. Adjust **Host** and **Port** if needed (defaults: `127.0.0.1` / `8080`)
6. Save settings


## 4. Start the Server

### Option A: Systemd service (recommended)

Install the service file:

```
sudo cp /path/to/teampass/websocket/config/teampass-websocket.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable teampass-websocket
sudo systemctl start teampass-websocket
```

> :bulb: **Note:** If your TeamPass installation path differs from `/var/www/html/TeamPass`, edit the service file and update `WorkingDirectory` and `ExecStart` before enabling it.

Check status:

```
sudo systemctl status teampass-websocket
```

### Option B: Manual start

```
sudo -u www-data php /path/to/teampass/websocket/bin/server.php
```

Press `Ctrl+C` to stop.

### Option C: From the admin panel

Go to **Administration > Dashboard**. The health check section shows the WebSocket status with a Start/Stop button.


## 5. Verify the Installation

### Check the server is listening

```
ss -tlnp | grep 8080
```

You should see a `LISTEN` entry on `127.0.0.1:8080`.

### Check the reverse proxy

```
curl -i -N \
  -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" \
  -H "Sec-WebSocket-Key: $(openssl rand -base64 16)" \
  https://your-teampass-host/ws
```

Expected response: `HTTP/1.1 101 Switching Protocols`.

### Check the admin dashboard

Go to **Administration > Dashboard**. The WebSocket status badge should be green with "Running".


## 6. Logs and Troubleshooting

### Log files

Application log:

```
tail -f /path/to/teampass/websocket/logs/websocket.log
```

Systemd journal:

```
journalctl -u teampass-websocket -f
```

### Common issues

| Problem | Cause | Solution |
|---------|-------|----------|
| Port 8080 already in use | Another service uses the port | Change the port in **Settings** and in `websocket/config/websocket.php` |
| 502 Bad Gateway on `/ws` | WebSocket server is not running | Start the server (see section 4) |
| Connection refused in browser console | Reverse proxy not configured | Configure Apache or Nginx (see section 2) |
| Server starts but stops immediately | Missing PHP extension | Install `pcntl` and `posix` (see section 1) |

### Advanced configuration

The file `websocket/config/websocket.php` contains advanced options:

| Parameter | Default | Description |
|-----------|---------|-------------|
| `poll_interval_ms` | `200` | Database polling interval in milliseconds |
| `max_connections_per_user` | `5` | Maximum simultaneous connections per user |
| `rate_limit_messages` | `10` | Maximum client messages per second |
| `ping_interval_sec` | `30` | Heartbeat interval in seconds |
| `pong_timeout_sec` | `60` | Connection timeout if no heartbeat response |
| `log_level` | `info` | Log verbosity: `debug`, `info`, `warning`, `error` |
| `event_retention_hours` | `24` | How long processed events are kept in database |
