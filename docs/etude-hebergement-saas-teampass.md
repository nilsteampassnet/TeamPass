# Étude d'hébergement managé de TeamPass pour un client (offre « hosted »)

> Document d'avant-vente / architecture — rédigé le 2026-06-05
> Cible : héberger TeamPass 3.2.x pour un prospect sur un VPS OVH (ou équivalent), Linux.
> Périmètre : sécurité, SSO externe, packages, coûts, stratégies d'exploitation, niveau de difficulté.

---

## 0. Avertissement sur les chiffres

Les prix OVH, Hetzner, Scaleway cités sont **indicatifs (ordre de grandeur 2025-2026, HT)**.
Ils doivent être confirmés sur les grilles officielles au moment du devis. La méthodologie
(dimensionnement, postes de coût) reste valable quelles que soient les variations tarifaires.

---

## 1. Exigences techniques réelles de TeamPass 3.2.x

Tirées directement du dépôt (`composer.json`, `public/install/install.php`, `docker-compose.yml`).

### 1.1 Socle logiciel obligatoire

| Composant | Version requise | Source |
|---|---|---|
| PHP | **≥ 8.2** | `composer.json` (`"php": ">=8.2"`), `MIN_PHP_VERSION = 8.2` |
| MySQL / MariaDB | MySQL ≥ 5.7 / **MariaDB ≥ 10.7** (recommandé 10.11 LTS) | CLAUDE.md + schéma `ONLY_FULL_GROUP_BY` |
| Serveur web | Apache 2.4 **ou** Nginx + PHP-FPM | installeur (PHP-FPM = SAPI recommandée) |

### 1.2 Extensions PHP

**Obligatoires** (vérifiées par l'installeur) :
`mysqli`, `mbstring`, `openssl`, `bcmath`, `xml`, `curl`, `gd`, `iconv`

**Optionnelles mais recommandées en production** :
- `opcache` (Zend OPcache) — **fortement recommandé** (perf)
- `apcu` — cache des réglages `teampass_misc` (60 s)
- `redis` — stockage de session chiffré (sinon filesystem)
- `pcntl` + `posix` — **requis uniquement pour le daemon WebSocket** (extensions CLI)

### 1.3 Dépendances applicatives notables (Composer)

- **Crypto** : `defuse/php-encryption`, `phpseclib/phpseclib ^3.0` (+ v1 legacy embarqué)
- **SSO/Annuaire** : `directorytree/ldaprecord ^3.0` (LDAP/AD), `thenetworg/oauth2-azure ^2.2` (Entra ID/OAuth2)
- **MFA** : `robthree/twofactorauth`, `spomky-labs/otphp` (TOTP), `duosecurity/duo_universal_php` (Duo)
- **Temps réel** : `cboden/ratchet ^0.4.4` + `react/event-loop` → **daemon WebSocket séparé**
- **Mail** : `phpmailer/phpmailer`
- **Tâches planifiées** : `peppeocchi/php-cron-scheduler`, `tiben/crontab-manager`

### 1.4 Composants d'exécution à prévoir

1. **Serveur web + PHP-FPM** : l'application HTTP principale.
2. **Base de données** MariaDB.
3. **Cron** : `php scripts/background_tasks___handler.php` (génération des sharekeys, tâches lourdes).
4. **Daemon WebSocket** (optionnel mais recommandé pour la synchro temps réel) :
   `php app/websocket/bin/server.php` lancé via **systemd**, écoute sur `127.0.0.1:8080`,
   exposé via reverse-proxy en `wss://host/ws`.
5. **Redis** (optionnel) : sessions chiffrées.
6. **Secrets hors webroot** : `TEAMPASS_SECRETS/SECUREFILE` (clé maître Defuse) — **jamais** versionné, jamais dans le webroot.

---

## 2. Architecture cible (mono-client, mono-VPS)

Pour **un seul prospect**, une architecture mono-serveur bien durcie suffit largement.
TeamPass est un outil à faible volumétrie de requêtes (quelques dizaines à centaines d'utilisateurs).

```
                    Internet
                       │  443 (HTTPS) + wss
                       ▼
        ┌──────────────────────────────────┐
        │  VPS Linux (Debian 12 / Ubuntu 24)│
        │                                    │
        │  Nginx (reverse proxy + TLS)       │
        │    ├── PHP-FPM 8.3  (TeamPass web) │
        │    └── /ws → 127.0.0.1:8080 (WS)   │
        │  systemd: teampass-websocket       │
        │  cron:    background_tasks handler │
        │  MariaDB 10.11  (localhost)        │
        │  Redis     (localhost, sessions)   │
        │  Secrets:  /var/teampass-secrets/  │
        │            (hors webroot, 0600)    │
        └──────────────────────────────────┘
                       ▲
                       │ SSO (OIDC/OAuth2 / LDAPS)
              ┌────────┴─────────┐
              │ IdP externe      │  Entra ID / Keycloak /
              │ (Azure, Okta…)   │  Okta / Google Workspace
              └──────────────────┘
```

### Deux options de packaging

| Option | Description | Pour qui |
|---|---|---|
| **A. Installation native** (LEMP) | Nginx + PHP-FPM + MariaDB installés directement sur l'OS | Contrôle fin, perf maximale, durcissement granulaire — **recommandé pour un hébergement pro** |
| **B. Docker / docker-compose** | Image `teampass` + MariaDB en conteneurs (un `docker-compose.yml` existe déjà dans le dépôt) | Déploiement rapide, reproductible, isolation — bon compromis si vous gérez plusieurs clients |

> Le `docker-compose.yml` fourni utilise `jwilder/nginx-proxy` + `alpine-mariadb`. Il est fonctionnel
> mais à durcir (secrets en clair dans le compose, pas de TLS Let's Encrypt automatique, pas de Redis,
> pas de daemon WebSocket). À retravailler avant prod.

---

## 3. Dimensionnement & choix du VPS

### 3.1 Charge attendue

TeamPass est peu gourmand : la crypto RSA-4096 par utilisateur (lors des partages) est le
principal pic CPU, et elle est largement déportée en tâches de fond (cron). La RAM sert surtout
à PHP-FPM, MariaDB, OPcache et Redis.

| Taille du client | Utilisateurs | vCPU | RAM | Disque (SSD NVMe) |
|---|---|---|---|---|
| Petit | ≤ 25 | 2 | 4 Go | 40–80 Go |
| Moyen (recommandé par défaut) | 25–150 | 2–4 | 8 Go | 80–160 Go |
| Grand | 150–500+ | 4–8 | 16 Go | 160 Go+ |

### 3.2 Offres VPS (ordre de grandeur HT/mois)

| Fournisseur | Gamme indicative | vCPU / RAM | Prix ~ /mois |
|---|---|---|---|
| **OVHcloud** | VPS Comfort / Elite | 4 vCPU / 8 Go | ~12–20 € |
| OVHcloud | VPS Value | 2 vCPU / 4 Go | ~6–8 € |
| Hetzner | CX/CPX | 3–4 vCPU / 8 Go | ~8–14 € |
| Scaleway | DEV1-M/L, PRO2 | 3–4 vCPU / 8–16 Go | ~12–25 € |

**Recommandation** : démarrer sur **OVH VPS 4 vCPU / 8 Go / NVMe** (≈ 12–20 €/mois HT),
évolutif à chaud. OVH a l'avantage de la souveraineté (datacenters FR/EU, RGPD) — argument
de vente fort pour un gestionnaire de mots de passe.

### 3.3 Options OVH complémentaires à provisionner

- **Backup automatisé** (snapshot/Backup VPS) : ~quelques €/mois.
- **Additional Disk / Block Storage** pour les sauvegardes déportées.
- **IP de failover / DNS** : OVH fournit le DNS ; sinon Cloudflare.
- **Anti-DDoS** : inclus de base chez OVH (bon point).

---

## 4. Système d'exploitation & packages

### 4.1 Choix de l'OS

**Recommandé : Debian 12 (Bookworm)** ou **Ubuntu Server 24.04 LTS**.
- Stabilité, support long (LTS), large doc, PHP 8.3 packagé (Sury pour Debian).
- Éviter Alpine en natif (musl libc, parfois pénible avec certaines extensions PHP).

### 4.2 Packages à installer (exemple Debian 12 + PHP 8.3 via dépôt Sury)

```bash
# Base
apt update && apt install -y \
  nginx mariadb-server redis-server \
  certbot python3-certbot-nginx \
  ufw fail2ban unattended-upgrades \
  git unzip curl

# PHP 8.3 + FPM + extensions TeamPass
apt install -y \
  php8.3-fpm php8.3-cli \
  php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-gd php8.3-bcmath php8.3-opcache \
  php8.3-redis php8.3-apcu php8.3-intl

# pcntl/posix sont compilés dans le binaire CLI Debian par défaut (vérifier: php -m)
# Sinon le daemon WebSocket ne démarre pas.

# Composer (déjà fourni dans le dépôt: composer.phar)
```

### 4.3 Réglages PHP-FPM (`/etc/php/8.3/fpm/conf.d/`)

```ini
memory_limit = 256M          ; 512M si gros imports/exports
max_execution_time = 60      ; installeur exige >= 30s
upload_max_filesize = 25M    ; selon politique de pièces jointes
post_max_size = 30M
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
apc.enabled = 1
expose_php = Off
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
```

### 4.4 Réglages MariaDB

- `sql_mode` doit inclure `ONLY_FULL_GROUP_BY` (défaut depuis 5.7.5) — le code TeamPass est compatible.
- `innodb_buffer_pool_size` ≈ 50 % de la RAM dédiée à la DB.
- Compte applicatif dédié (pas root), privilèges limités à la base `teampass`.
- Bind sur `127.0.0.1` uniquement (jamais exposé au réseau).

---

## 5. Sécurité (le cœur du sujet pour un gestionnaire de secrets)

### 5.1 Réseau & accès

| Mesure | Détail |
|---|---|
| **Firewall (ufw/nftables)** | N'ouvrir que 443 (et 80 pour redirection→443 + ACME). SSH restreint par IP/VPN. |
| **SSH durci** | Clés uniquement (`PasswordAuthentication no`), pas de root login, port non standard optionnel, `fail2ban`. |
| **Accès admin restreint** | Idéalement TeamPass accessible derrière un **VPN** (WireGuard) ou liste blanche d'IP via Nginx (`allow/deny`) si le client a des IP fixes. |
| **Anti-DDoS** | Couche OVH + rate-limiting Nginx (`limit_req`). |

### 5.2 TLS / Chiffrement transport

- **Let's Encrypt** via certbot (renouvellement auto) ou certificat EV/OV fourni par le client.
- TLS 1.2 minimum, idéalement 1.3 ; suites modernes (config Mozilla « intermediate »).
- **HSTS** (`Strict-Transport-Security`), OCSP stapling.
- En-têtes de sécurité Nginx : `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: no-referrer`, CSP adaptée (TeamPass utilise déjà ces en-têtes côté API).

### 5.3 Sécurité applicative (déjà dans TeamPass, à activer/vérifier)

- **Secrets hors webroot** : placer `TEAMPASS_SECRETS/SECUREFILE` dans `/var/teampass-secrets/`,
  propriétaire `www-data`, `chmod 0600`. **Jamais** dans `/var/www`. **Ne jamais** versionner
  `includes/config/settings.php`.
- **Chiffrement multicouche natif** : Defuse (clé maître) + RSA-4096 par utilisateur + AES par objet.
  Rien à coder, mais à **comprendre pour la stratégie de sauvegarde/restauration** (cf. §7).
- **CSRF** (`owasp/csrf-protector-php`), **XSS** (`voku/anti-xss` + DOMPurify), requêtes
  paramétrées (MeekroDB) — déjà en place.
- **MFA** : imposer TOTP (Google Authenticator) ou Duo/Yubikey dans les réglages admin.
- **Anti-bruteforce** : seuils `nb_bad_authentication`, `bruteforce_lock_duration` (déjà présents).
- **Politique de mot de passe** : complexité, expiration, via réglages TeamPass.

### 5.4 Durcissement OS

- `unattended-upgrades` (patchs sécurité auto).
- AppArmor (Debian/Ubuntu) actif.
- Logs centralisés (`journald` + éventuellement export syslog vers le client / SIEM).
- `fail2ban` sur SSH + Nginx auth.
- Permissions fichiers : webroot `www-data`, secrets isolés, `storage/upload` en `0750`.

### 5.5 Conformité / contractuel (important en hébergement managé)

- **RGPD** : datacenter UE (OVH FR), registre des traitements, DPA (Data Processing Agreement) avec le client.
- **Hébergement de données sensibles** : définir contractuellement qui détient la clé maître,
  la responsabilité en cas de perte de clé (les données sont **irrécupérables** sans elle).
- **Engagement de service (SLA)** : disponibilité, RTO/RPO, fenêtres de maintenance.
- **Réversibilité** : prévoir l'export/migration des données si le client part.

---

## 6. Connectivité SSO externe

### 6.1 Ce que TeamPass supporte nativement (vérifié dans le code)

| Méthode | Support | Librairie |
|---|---|---|
| **Local** (DB) | Oui | natif |
| **LDAP / Active Directory** | Oui (LDAPS recommandé) | `directorytree/ldaprecord` |
| **OAuth2 / OpenID Connect** | Oui | `teampassclasses/oauth2controller` (générique) |
| **Microsoft Entra ID (Azure AD)** | Oui (cas le mieux supporté) | `thenetworg/oauth2-azure` |
| **SAML 2.0** | **Non natif** | — (cf. §6.4) |

> Le connecteur OAuth2 est **générique** : il fonctionne donc aussi avec
> **Keycloak, Okta, Google Workspace, Authentik, Auth0…** dès lors qu'ils exposent OIDC/OAuth2
> (endpoints authorize/token/userinfo). Entra ID bénéficie d'un support spécifique.

### 6.2 Mise en place OIDC/OAuth2 (cas le plus fréquent)

1. Côté IdP du client : créer une application/registration, définir le **redirect URI**
   (`https://votre-host/...` — l'endpoint de callback OAuth2 de TeamPass).
2. Récupérer `client_id`, `client_secret`, les endpoints (authorize/token/userinfo), le tenant.
3. Renseigner ces valeurs dans **Admin → réglages SSO/OAuth2** de TeamPass.
4. Mapper les attributs (email, login, nom) ; gérer la **création automatique de compte**
   au premier login (`auth_type = oauth2`, géré dans `identify.php`).
5. Définir le comportement : auto-provisioning, rôles par défaut, dossiers personnels.

### 6.3 Mise en place LDAP/AD

- Connexion **LDAPS (636)** vers l'annuaire du client (VPN/tunnel si l'AD n'est pas exposé).
- Compte de service en lecture seule, base DN, filtres utilisateurs/groupes.
- Synchronisation des groupes → rôles TeamPass possible.

### 6.4 Si le client exige SAML

TeamPass n'a pas de connecteur SAML natif. Deux stratégies :
- **Pont OIDC** : passer par Keycloak / Authentik en frontal qui parle SAML à l'IdP et OIDC à TeamPass (recommandé, propre).
- Reverse-proxy authentifiant (oauth2-proxy / Vouch) — plus fragile pour une app qui gère ses propres sessions. À éviter sauf besoin spécifique.

### 6.5 Point d'attention sécurité SSO

Avec SSO, **garder un compte admin local de secours** (break-glass) avec MFA, au cas où l'IdP
serait indisponible — sinon plus personne ne peut administrer l'instance.

---

## 7. Stratégies d'exploitation

### 7.1 Sauvegarde (critique — spécificité d'un coffre-fort)

> ⚠️ La sauvegarde DB **seule ne suffit pas**. Sans la **clé maître Defuse**
> (`TEAMPASS_SECRETS/SECUREFILE`) ET la cohérence des clés utilisateurs, les données chiffrées
> sont **inexploitables**. Sauvegarder les deux, **séparément**.

| Élément | Fréquence | Méthode |
|---|---|---|
| Dump MariaDB (`mysqldump`/`mariabackup`) | Quotidien (+ binlogs pour PITR) | chiffré au repos, déporté |
| `settings.php` + secrets Defuse | À chaque changement + sauvegarde initiale | **stockage séparé**, accès très restreint |
| `storage/upload` (pièces jointes) | Quotidien | rsync/snapshot |
| Snapshot VPS complet | Hebdo | option OVH Backup |

- **Chiffrer les sauvegardes** (GPG) et les **déporter** (autre datacenter / S3 compatible / coffre client).
- Tester régulièrement la **restauration complète** (DB + secrets) sur un environnement de staging.
- RPO cible : 24 h (ou < 1 h avec binlogs). RTO cible : quelques heures.

### 7.2 Mises à jour

- **TeamPass** : suivre les releases GitHub (sécurité), tester sur staging, appliquer via
  `/install/upgrade.php` + scripts `upgrade_run_*`. Vérifier les impacts install/upgrade.
- **OS/PHP/MariaDB** : patchs sécurité automatiques (unattended-upgrades), montées de version
  majeures planifiées en fenêtre de maintenance.
- Toujours **snapshot avant upgrade**.

### 7.3 Supervision / monitoring

- **Disponibilité** : Uptime Kuma / healthcheck HTTP externe.
- **Métriques** : Netdata / Prometheus + node_exporter (CPU, RAM, disque, MariaDB).
- **Logs** : journald + logs TeamPass (Admin → Logs), alertes fail2ban.
- **Certificat TLS** : alerte expiration.
- **Daemon WebSocket** : `systemctl` avec `Restart=always`, surveiller qu'il tourne.
- **Cron** : vérifier l'exécution des tâches de fond (sinon les partages de secrets ne se propagent pas).

### 7.4 Haute disponibilité (optionnel, surcoût)

Pour un seul prospect, le mono-serveur + bonnes sauvegardes suffit généralement.
Si SLA élevé exigé : 2e VPS en standby + réplication MariaDB + bascule DNS/IP failover.
→ double le coût d'infra et augmente nettement la complexité (réplication des secrets à gérer).

---

## 8. Estimation des coûts

### 8.1 Coûts récurrents mensuels (infra, ordre de grandeur HT)

| Poste | Coût mensuel |
|---|---|
| VPS OVH 4 vCPU / 8 Go NVMe | 12–20 € |
| Backup OVH / stockage déporté | 3–8 € |
| Nom de domaine (amorti) | ~1 € |
| TLS Let's Encrypt | 0 € |
| Monitoring (self-hosted) | 0 € (sinon SaaS 5–15 €) |
| **Total infra** | **~ 16–35 €/mois HT** |

### 8.2 Coûts récurrents masqués (les vrais coûts d'un service managé)

| Poste | Estimation |
|---|---|
| Exploitation / supervision / patchs | ~2–6 h/mois |
| Support client (N1/N2) | variable selon SLA |
| Astreinte (si SLA 24/7) | surcoût significatif |
| Tests de restauration | ~2–4 h/trimestre |

➡️ **C'est le temps humain, pas l'infra, qui domine le coût réel.** Le VPS coûte des dizaines
d'euros ; l'exploitation responsable d'un coffre-fort de secrets coûte des centaines d'euros/mois
en temps. À refléter dans le prix de vente.

### 8.3 Coût de mise en place (one-shot)

| Phase | Charge estimée |
|---|---|
| Provisionnement VPS + durcissement OS | 0,5–1 j |
| Installation LEMP + TeamPass + secrets | 0,5–1 j |
| Configuration SSO (OIDC/LDAP) + tests | 0,5–1,5 j (selon IdP) |
| Sauvegarde/restauration + monitoring | 0,5–1 j |
| Tests de recette + documentation | 0,5–1 j |
| **Total** | **~ 3 à 5,5 jours** |

### 8.4 Proposition de modèle tarifaire de revente

- **Frais d'installation** (one-shot) : couvrir les 3–5 j de mise en place.
- **Abonnement mensuel** : infra + marge d'exploitation + provision support/astreinte.
- Paliers selon nb d'utilisateurs et niveau de SLA (best-effort vs 24/7).

---

## 9. Niveau de difficulté

### Évaluation globale : **MOYEN** 🟠 (3/5)

| Aspect | Difficulté | Commentaire |
|---|---|---|
| Provisionnement VPS + LEMP | 🟢 Facile (2/5) | Procédure standard, bien documentée |
| Installation TeamPass | 🟢 Facile (2/5) | Installeur web guidé, prérequis clairs |
| Daemon WebSocket + cron | 🟠 Moyen (3/5) | systemd + reverse-proxy wss, pcntl/posix à vérifier |
| Durcissement sécurité | 🟠 Moyen (3/5) | Standard mais critique vu la sensibilité |
| SSO OIDC/Entra | 🟠 Moyen (3/5) | Dépend surtout de la coopération de l'IdP client |
| SSO LDAP via VPN | 🟠🔴 Moyen-élevé (3,5/5) | Tunnel vers l'AD du client = coordination réseau |
| SSO SAML (si exigé) | 🔴 Élevé (4/5) | Nécessite un pont Keycloak/Authentik |
| Sauvegarde/restauration des secrets | 🔴 Élevé (4/5) | **Le vrai point dur** : irréversibilité si clé perdue |
| Exploitation dans la durée (SLA) | 🔴 Élevé (4/5) | Responsabilité forte, astreinte, conformité |

**Synthèse** : techniquement, monter l'instance est **abordable pour un sysadmin/devops confirmé**
(quelques jours). La difficulté réelle n'est pas l'installation mais **l'exploitation responsable
d'un coffre-fort multi-tenant** : gestion des clés/sauvegardes (irréversibilité), SLA, conformité
RGPD, support, et l'intégration SSO qui dépend fortement de l'environnement du client.

---

## 10. Checklist de mise en production

- [ ] VPS OVH provisionné (Debian 12 / Ubuntu 24.04), backups activés
- [ ] OS durci : ufw, fail2ban, SSH clés only, unattended-upgrades, AppArmor
- [ ] LEMP installé : Nginx + PHP-FPM 8.3 (+ extensions), MariaDB 10.11, Redis
- [ ] `php -m` confirme mysqli, mbstring, openssl, bcmath, xml, curl, gd, iconv (+ pcntl/posix pour WS)
- [ ] TLS Let's Encrypt + HSTS + en-têtes de sécurité Nginx
- [ ] Secrets Defuse hors webroot (`/var/teampass-secrets/`, 0600, www-data)
- [ ] `settings.php` exclu du versioning, permissions strictes
- [ ] Installeur TeamPass exécuté, puis `/install` supprimé/verrouillé
- [ ] Cron `background_tasks___handler.php` planifié et vérifié
- [ ] Daemon WebSocket en systemd (`Restart=always`), reverse-proxy `/ws` → 8080
- [ ] SSO configuré (OIDC/LDAP) + **compte admin local break-glass + MFA**
- [ ] MFA imposé, politique de mot de passe, anti-bruteforce configurés
- [ ] Sauvegardes (DB + secrets + uploads) chiffrées, déportées, **restauration testée**
- [ ] Monitoring (uptime, métriques, certif TLS, daemon WS, cron) + alertes
- [ ] Documentation d'exploitation + DPA/SLA signés avec le client
```

