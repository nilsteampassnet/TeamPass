<!-- docs/install/docker.md -->

## Overview

Teampass can be deployed using Docker and Docker Compose. This is the recommended approach for quick evaluation, isolated environments, and containerised production deployments.

> The complete Docker documentation is maintained in the repository root:
>
> - **[DOCKER.md](https://github.com/nilsteampassnet/TeamPass/blob/master/docs/DOCKER.md)** — Full setup guide: quick start, SSL, environment variables, backup, troubleshooting, advanced usage.
> - **[DOCKER-MIGRATION.md](https://github.com/nilsteampassnet/TeamPass/blob/master/docs/DOCKER-MIGRATION.md)** — Upgrade procedures between Docker image versions.

---

## Requirements

- Docker Engine 20.10+ or Docker Desktop
- Docker Compose 2.0+
- At least 2 GB of free disk space

---

## Quick start

```bash
# 1. Clone the repository
git clone https://github.com/nilsteampassnet/TeamPass.git
cd TeamPass/docker/docker-compose

# 2. Create and configure the environment file
cp .env.example .env
# Edit .env and set DB_PASSWORD and MARIADB_ROOT_PASSWORD at minimum

# 3. Start the stack
docker compose up -d

# 4. Open http://localhost:8080 in your browser and run the installation wizard
#    (the master key is created in /var/www/html/secrets, on the teampass-secrets volume)

# 5. Restart the container once the wizard is done: it removes the install directory
docker compose restart teampass
```

---

## Key environment variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_PASSWORD` | — | **Required.** MariaDB password for the Teampass user |
| `MARIADB_ROOT_PASSWORD` | — | **Required.** MariaDB root password |
| `DB_HOST` | `db` | Database service hostname |
| `DB_NAME` | `teampass` | Database name |
| `DB_USER` | `teampass` | Database user |
| `TP_DOMAIN` | `localhost` | Public hostname (used for link generation) |

See `.env.example` in the repository for the full list.

---

## Volumes and file ownership

The image declares five paths as volumes. Everything TeamPass cannot rebuild lives there:

| Container path | Holds |
|---|---|
| `/var/www/html/secrets` | The master encryption key |
| `/var/www/html/storage/config` | `settings.php` (database connection) and `csrfp.config.php` |
| `/var/www/html/storage/files` | Encrypted attachments |
| `/var/www/html/storage/upload` | Temporary uploads |
| `/var/www/html/storage/sk` | Legacy saltkey |

The provided `docker-compose.yml` mounts a **named** volume on each of them. If you write your own compose file or stack, mount these exact paths too. A volume on a parent directory such as `/var/www/html/storage` is not enough: Docker still mounts an **anonymous** volume on top of each path the image declares, and anonymous volumes are left behind when the container is removed (`docker compose down`, a stack redeploy). Losing `storage/config` makes TeamPass believe it was never installed; losing `secrets` makes its data unrecoverable.

Check what backs each path:

```bash
docker inspect teampass-app --format '{{range .Mounts}}{{.Name}} -> {{.Destination}}{{println}}{{end}}'
```

A name made of 64 hexadecimal characters is an anonymous volume.

**File ownership.** PHP runs as `nginx` inside the image, and the image ships its files owned by `nginx`. Do not `chown` them to `www-data`, the web server account of Debian and Ubuntu hosts: it does not apply to the container. `docker compose up -d --force-recreate` restores the image's ownership; the container resets the ownership of its volumes at every start.

---

## Custom login logo and background

The login page takes its custom logo and background from `/var/www/html/public/assets/custom/` (see [Settings → General Info](../manage/settings.md#login-page-branding)). That folder is part of the image, so an image copied into a running container disappears the next time the container is recreated. Mount each image instead, read-only:

```yaml
services:
  teampass:
    volumes:
      - ./branding/logo.png:/var/www/html/public/assets/custom/logo.png:ro
      - ./branding/background.jpg:/var/www/html/public/assets/custom/background.jpg:ro
```

Then enter `logo.png` and `background.jpg` in the two settings. Mount the files one by one rather than the whole folder: a directory mounted over `public/assets/custom/` hides the `README.md` and `.htaccess` the image ships there, and the file integrity check then reports them as missing. The files only need to be readable by everyone (`chmod 644`).

---

## Upgrading

Refer to **[DOCKER-MIGRATION.md](https://github.com/nilsteampassnet/TeamPass/blob/master/docs/DOCKER-MIGRATION.md)** when you come from the 3.1.x Docker setup.

Between 3.2.x images:

```bash
# Back up first (see Backup below), then:
docker compose pull
docker compose up -d

# Check what the container did
docker compose logs teampass | head -60
```

The container applies the database migrations itself when it starts, then removes the install directory. `/install/upgrade.php` therefore answers **404** after a successful upgrade: that is expected. It stays available only when the log says an upgrade is pending, and then you finish it there.

> :warning: Never add `-v` to `docker compose down`, and do not run `docker volume prune` or `docker system prune --volumes` during an upgrade: they delete volumes, including the ones holding the master key and `settings.php`.

If the log says **`TeamPass is not configured yet`** on an instance that was already installed, its configuration was lost: follow [Recovering a lost configuration](#recovering-a-lost-configuration) and **do not run the installer**.

### Access logs

Since 3.2.2.7, the Nginx access logs of the container (`/var/log/nginx/access.log` and `/var/log/nginx/teampass-access.log`) record the request path **without its query string** and without the `Referer` header, because both carry secrets such as Secure Send link credentials. Each line holds the client address, the time, the method, the path, the status, the size, the user agent and `X-Forwarded-For`: adapt any tool that parsed the previous `combined` format. A custom `/etc/nginx/http.d/default.conf` that removed the query strings, as advised for 3.2.2.6 by the [Secure Send guide](secure-send.md), is no longer needed.

---

## Backup

Critical data to back up:

| What | How |
|------|-----|
| Database | `docker exec teampass-db sh -c 'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" teampass' > teampass-$(date +%Y%m%d).sql` |
| Master key, configuration and attachments | `docker exec teampass-app tar -C /var/www/html -czf - secrets storage/config storage/files > teampass-state-$(date +%Y%m%d).tar.gz` |

> 🔔 The master key in `secrets/` is required to decrypt all data. Losing it makes the database unrecoverable. Keep the database dump and the key together, in a safe place: together they open every secret.

---

## Recovering a lost configuration

**Symptoms:** on an instance that was installed, the root URL redirects to `install/install.php`, `install/upgrade.php` reports that it cannot find its configuration (older images: HTTP 500), and the container log says `TeamPass is not configured yet`. `docker exec teampass-app ls -la /var/www/html/storage/config/` shows no `settings.php`. Typically, the container was recreated while `storage/config` was not on a named volume.

The database and the master key are usually intact. `settings.php` is missing, and so are the attachments when `storage/files` was not on a named volume either.

> :warning: **Do not run the installer**: it would generate a new master key and make every existing secret unreadable. **Do not delete any Docker volume** before the end of this procedure: the previous copy of `settings.php` may still sit in an unused one.

**1. Put the five paths on named volumes.** If `docker inspect` (see [Volumes and file ownership](#volumes-and-file-ownership)) shows anonymous volumes, save their content first — the new named volumes start empty:

```bash
docker exec teampass-app tar -C /var/www/html -cf - secrets storage/config storage/files storage/upload storage/sk > teampass-state.tar
```

Add the five named volumes to your compose file, run `docker compose up -d`, then put the content back:

```bash
docker exec -i teampass-app tar -C /var/www/html -xf - < teampass-state.tar
```

**2. Look for the previous `settings.php`.** Run these commands on the Docker host, where you type `docker ...`, never inside the container. They only read.

On a Linux host, the volumes are plain directories under Docker's data directory (`/var/lib/docker` by default; `docker info -f '{{.DockerRootDir}}'` shows yours). This lists the configuration files they hold:

```bash
sudo sh -c 'ls -la --time-style=long-iso /var/lib/docker/volumes/*/_data/settings.php /var/lib/docker/volumes/*/_data/csrfp.config.php 2>/dev/null'
```

With Docker Desktop, the volumes live inside a virtual machine; use this loop instead. Its first run downloads the small `alpine` image, and Docker prints the download progress once:

```bash
for v in $(docker volume ls -q); do
  docker run --rm -v "$v":/v:ro alpine sh -c 'ls /v/settings.php /v/csrfp.config.php 2>/dev/null' | sed "s|^|$v: |"
done
```

To find the attachments as well, list every volume with its creation date, its number of entries and the container using it. The previous `storage/files` volume is an unused one (no container) created at the same time as the one holding `settings.php`. The image ships that directory empty, so every entry it holds is an attachment:

```bash
for v in $(docker volume ls -q); do
  printf '%s  %s  %s entries  %s\n' \
    "$(docker volume inspect -f '{{.CreatedAt}}' "$v" | cut -c1-16)" "$v" \
    "$(docker run --rm -v "$v":/v:ro alpine sh -c 'ls -A /v | wc -l')" \
    "$(docker ps -a --filter volume="$v" --format '{{.Names}}')"
done | sort
```

If `settings.php` is found, copy it back (and `csrfp.config.php` too when the same volume holds it), copy the attachments back if you found them, then restart:

```bash
docker run --rm -v <volume>:/v:ro alpine cat /v/settings.php \
  | docker exec -i teampass-app sh -c 'cat > /var/www/html/storage/config/settings.php'
docker run --rm -v <volume>:/v:ro alpine cat /v/csrfp.config.php \
  | docker exec -i teampass-app sh -c 'cat > /var/www/html/storage/config/csrfp.config.php'
docker run --rm -v <attachments-volume>:/v:ro alpine tar -C /v -cf - . \
  | docker exec -i teampass-app tar -C /var/www/html/storage/files -xf -
docker compose restart teampass
```

**3. Otherwise, rebuild it.** As long as `secrets/` still holds the master key, `settings.php` can be written again. Save the script below as `recover-config.php`, fill in the values of your `.env` file at the top, and run it inside the container. It checks that the database answers and that the key opens it, and only writes the files that are missing:

```php
<?php
// ---- Fill in the values of your .env file ----------------------------------
$db = [
    'host'     => 'db',
    'port'     => '3306',
    'name'     => 'teampass',
    'user'     => 'teampass',
    'password' => 'CHANGE_ME',
    'prefix'   => 'teampass_',
];
$https = true; // false when the browser reaches TeamPass over plain http://
// -----------------------------------------------------------------------------

$root = '/var/www/html';
$config = $root . '/storage/config';
require $root . '/app/vendor/autoload.php';

if (preg_match('/^[A-Za-z0-9_]*$/', $db['prefix']) !== 1) {
    exit("Invalid table prefix.\n");
}

// The master key is the only file in secrets/
$keyFiles = array_values(array_diff(scandir($root . '/secrets'), ['.', '..', '.gitkeep']));
if (count($keyFiles) !== 1) {
    exit('Expected one key file in secrets/, found ' . count($keyFiles) . ": stop and ask for help.\n");
}
$key = Defuse\Crypto\Key::loadFromAsciiSafeString(trim(file_get_contents($root . '/secrets/' . $keyFiles[0])));

// The database must answer, and the key must open what it encrypted
$link = new mysqli($db['host'], $db['user'], $db['password'], $db['name'], (int) $db['port']);
$row = $link->query(
    'SELECT valeur FROM `' . $db['prefix'] . "misc` WHERE is_encrypted = 1 AND valeur LIKE 'def%' LIMIT 1"
)->fetch_row();
if (is_array($row) === true) {
    try {
        Defuse\Crypto\Crypto::decrypt($row[0], $key);
    } catch (Throwable $e) {
        exit("This key does not decrypt the database: stop and ask for help.\n");
    }
}

if (file_exists($config . '/settings.php') === false) {
    $php = "<?php\n";
    foreach ([
        'DB_HOST' => $db['host'],
        'DB_USER' => $db['user'],
        'DB_PASSWD' => Defuse\Crypto\Crypto::encrypt($db['password'], $key),
        'DB_NAME' => $db['name'],
        'DB_PREFIX' => $db['prefix'],
        'DB_PORT' => $db['port'],
        'DB_ENCODING' => 'utf8mb4',
        'SECUREFILE' => $keyFiles[0],
    ] as $name => $value) {
        $php .= "define('" . $name . "', " . var_export($value, true) . ");\n";
    }
    $php .= "define('DB_SSL', false);\ndefine('DB_CONNECT_OPTIONS', [MYSQLI_OPT_CONNECT_TIMEOUT => 10]);\n";
    file_put_contents($config . '/settings.php', $php);
    echo "settings.php written\n";
}

if (file_exists($config . '/csrfp.config.php') === false) {
    $csrfp = file_get_contents($root . '/app/includes/libraries/csrfp/libs/csrfp.config.sample.php');
    $csrfp = str_replace('"CSRFP_TOKEN" => ""', '"CSRFP_TOKEN" => "' . bin2hex(random_bytes(25)) . '"', $csrfp);
    $csrfp = str_replace('"jsUrl" => ""', '"jsUrl" => "./assets/lib/csrfp/csrfprotector.js"', $csrfp);
    if ($https === false) {
        $csrfp = str_replace('"secure" => true', '"secure" => false', $csrfp);
    }
    file_put_contents($config . '/csrfp.config.php', $csrfp);
    echo "csrfp.config.php written\n";
}
```

```bash
docker exec -i teampass-app php < recover-config.php
docker compose restart teampass
rm recover-config.php   # it holds the database password
```

After the restart, the log must say `TeamPass is already configured`; the container then applies the pending database migrations by itself.
