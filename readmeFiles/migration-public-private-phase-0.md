# Migration Public/Private Structure — Phase 0: Preparation

> **Status:** In progress  
> **Branch:** `feature/public-private-structure`  
> **Reference doc:** `readmeFiles/security-public-private-structure.md`  
> **Date:** 2026-04-09  
> **Scope:** Audit exhaustif, recensement des dépendances de chemins, procédure de rollback

---

## 0. Objectif de la Phase 0

La Phase 0 ne modifie **aucun fichier fonctionnel**. Son rôle est de :

1. Confirmer que la branche de travail existe
2. Produire un inventaire précis et chiffré de tout ce qui devra changer
3. Identifier les effets de bord non évidents
4. Définir la procédure de rollback
5. Poser les prérequis pour que la Phase 1 (scaffolding) puisse démarrer en sécurité

---

## 1. État de la branche

- [x] Branche `feature/public-private-structure` créée (vérifiée au `2026-04-09`)
- Branche de base : `master`
- Aucune modification fonctionnelle sur cette branche à ce stade

---

## 2. Audit des chemins codés en dur

### 2.1 Résumé chiffré

| Catégorie | Occurrences | Criticité |
|---|---|---|
| `__DIR__` dans les fichiers PHP (hors `vendor/`) | **274** | Haute |
| Includes/requires avec `'../'` (montée relative) | **92** | Haute |
| Includes/requires avec `'./'` | **27** | Moyenne |
| Références AJAX à `sources/` via `cpassman_url` (variable) | **24** | Faible — déjà abstraites |
| Références AJAX à `sources/` codées en dur (sans variable) | **3** | Haute |
| Usages du paramètre `cpassman_dir` pour inclure pages/sources | **30+** | Haute |
| Constantes de chemins à redéfinir | **6** | Critique |

### 2.2 Constantes de chemins à redéfinir (critique)

Ces constantes sont définies dans `includes/config/include.php` et utilisées partout. Elles doivent être mises à jour **en priorité absolue** en Phase 2.

| Constante | Définition actuelle | Problème | Nouvelle définition cible |
|---|---|---|---|
| `TEAMPASS_ROOT_PATH` | `__DIR__.'/../../'` (ligne 88) | Chemin relatif fragile | `TEAMPASS_ROOT . '/'` |
| `LOG_TASKS_FILE` | `'../files/teampass_tasks.log'` (ligne 55) | Chemin relatif (depuis le répertoire courant d'exécution) | `TEAMPASS_STORAGE . '/logs/teampass_tasks.log'` |
| `TASKS_LOCK_FILE` | `''` (ligne 56) | Fallback dans le code vers `__DIR__.'/../files/...'` | `TEAMPASS_STORAGE . '/logs/teampass_background_tasks.lock'` |
| `TASKS_TRIGGER_FILE` | `''` (ligne 57) | Fallback dans le code vers `__DIR__.'/../files/...'` | `TEAMPASS_STORAGE . '/logs/teampass_background_tasks.trigger'` |
| `API_ROOT_PATH` | `__DIR__ . "/.."` (dans `api/inc/bootstrap.php`, ligne 28) | Relatif au script appelant | `TEAMPASS_ROOT . '/app/api'` |
| `TEAMPASS_ROOT` | N'existe pas encore | Absent — toute la hiérarchie est implicite | À créer dans `public/index.php` |

> **Effet de bord critique — `TASKS_LOCK_FILE` et `TASKS_TRIGGER_FILE`** : ces deux constantes ont pour valeur une chaîne vide. Le code de `scripts/background_tasks___handler.php` (lignes 74–76, 398, 425) et `sources/main.functions.php` (ligne 7155–7157) implémente un fallback explicite :
> ```php
> $lockFile = empty(TASKS_LOCK_FILE)
>     ? __DIR__ . '/../files/teampass_background_tasks.lock'
>     : TASKS_LOCK_FILE;
> ```
> Après la migration, `__DIR__` pointera vers `app/scripts/` et `/../files/` sera introuvable. Il faudra soit renseigner ces constantes, soit adapter les fallbacks.

### 2.3 Paramètre de base de données `cpassman_dir`

Ce paramètre est stocké dans `teampass_misc` et chargé par `ConfigManager`. Il est utilisé dans `index.php` pour inclure les pages et les scripts JS. Sa valeur est typiquement le chemin absolu vers la racine du projet.

**Fichiers utilisant `$SETTINGS['cpassman_dir']` pour construire des chemins :**

| Fichier | Usage | Impact migration |
|---|---|---|
| `index.php` (30+ lignes) | Inclut `pages/*.php`, `pages/*.js.php`, `includes/core/login.php`, `sources/core.php` | `cpassman_dir` devra pointer vers `TEAMPASS_ROOT`, non vers `TEAMPASS_APP`. Les sous-chemins `/pages/`, `/sources/` etc. devront être mis à jour ligne par ligne. |
| `sources/upload.files.php:169` | `realpath($SETTINGS['cpassman_dir'] . '/includes/avatars')` | `/includes/avatars/` → `/public/assets/avatars/` |
| `sources/items.queries.php:6805-6806` | Chemin FS et URL des avatars | Double impact : chemin filesystem ET URL HTTP |
| `sources/utilities.queries.php:3226` | `$SETTINGS['cpassman_dir'] . '/files'` pour les backups | `/files/` → `/storage/files/` |
| `pages/uploads.php:72`, `sources/upload.attachments.php:77`, `sources/upload.files.php:79` | `include $SETTINGS['cpassman_dir'] . '/error.php'` | `error.php` reste à la racine `/public/error.php` |
| `pages/profile.php:146` | URL des avatars via `cpassman_url` | URL uniquement — pas de chemin FS direct |

> **Recommandation :** mettre à jour la valeur de `cpassman_dir` en base de données via un script de migration (dans l'upgrade script), ET adapter les chemins relatifs codés dans le code source. Les deux modifications sont nécessaires.

### 2.4 Chemins vers `files/` et `upload/` (répertoires de stockage)

Ces répertoires doivent migrer vers `/storage/`. Voici tous les fichiers qui y font référence avec un chemin construit dynamiquement :

| Fichier | Ligne | Référence actuelle | Impact |
|---|---|---|---|
| `includes/config/include.php` | 55 | `'../files/teampass_tasks.log'` | Chemin relatif — à remplacer par constante |
| `scripts/background_tasks___handler.php` | 76 | `__DIR__ . '/../files/teampass_background_tasks.trigger'` | Fallback à adapter |
| `scripts/background_tasks___handler.php` | 398, 425 | `__DIR__ . '/../files/teampass_background_tasks.lock'` | Fallback à adapter |
| `sources/main.functions.php` | 7157 | `__DIR__ . '/../files/teampass_background_tasks.trigger'` | Fallback à adapter |
| `sources/utilities.queries.php` | 3226 | `$SETTINGS['cpassman_dir'] . '/files'` | Backup dir → `/storage/files/` |
| `install/upgrade_ajax.php` | 986 | `'../files/' . $backup_file_name` | Relatif — à corriger |
| `install/diagnose_transparent_recovery.php` | 100 | `__DIR__ . '/../files/recovery_secret.key'` | Outil de diagnostic |
| `install/upgrade_run_3.1.6.php` | 370, 382 | `__DIR__ . '/../files/teampass_background_tasks.*'` | Script d'upgrade historique |

> **Effet de bord — scripts d'upgrade historiques** : les fichiers `upgrade_run_*.php` ont des chemins codés en dur relatifs à leur emplacement dans `/install/`. Après la migration, ces scripts seront dans `/app/install/` et leurs `__DIR__ . '/../files/'` ne seront plus valides. Il faudra vérifier que ces scripts ne sont utilisés que lors d'une montée de version (pas rejouables), et les adapter si nécessaire.

### 2.5 Chemins vers `includes/avatars/`

Les avatars sont à la fois :
- Servis via HTTP (URL publique via `cpassman_url . '/includes/avatars/'`)
- Écrits par PHP (chemin FS via `cpassman_dir . '/includes/avatars'`)

Après la migration, ils résideront dans `/public/assets/avatars/`. Impact :

| Type | Avant | Après |
|---|---|---|
| Chemin FS | `$cpassman_dir/includes/avatars/` | `TEAMPASS_ROOT . '/public/assets/avatars/'` |
| URL HTTP | `$cpassman_url/includes/avatars/` | `$cpassman_url/assets/avatars/` |

**Fichiers à mettre à jour :**
- `sources/upload.files.php:169` (écriture FS)
- `sources/items.queries.php:6805-6806` (lecture FS + URL)
- `sources/main.queries.php:995` (URL)
- `pages/profile.php:146` (URL)

### 2.6 Chemins `__DIR__` dans les classes internes

| Fichier | Usage | Spécificité |
|---|---|---|
| `includes/libraries/teampassclasses/configmanager/src/ConfigManager.php` | `__DIR__ . '/../../../../includes/config/settings.php'` (lignes 92, 148) | **Dual location** : existe aussi dans `vendor/teampassclasses/configmanager/src/`. Les deux doivent être mis à jour. |
| `includes/libraries/teampassclasses/language/src/Language.php` | `__DIR__ . "/../../../../includes/language"` (constructeur) | Chemin relatif vers les fichiers de langue |
| `includes/libraries/phpseclibV1_autoload.php` | `__DIR__ . '/phpseclibV1/'` | Simple — la librairie est dans le même dossier |
| `includes/libraries/teampassclasses/performchecks/src/PerformChecks.php` | `__DIR__ . '/../includes/libraries/csrfp/...'` | Chemin incorrect même aujourd'hui (probablement un bug) |

> **Effet de bord critique — `ConfigManager` dual location** : comme documenté dans `CLAUDE.md`, `ConfigManager` existe en deux endroits qui doivent rester synchronisés :
> - `includes/libraries/teampassclasses/configmanager/src/ConfigManager.php`
> - `vendor/teampassclasses/configmanager/src/ConfigManager.php`
>
> La ligne `$settingsFile = __DIR__ . '/../../../../includes/config/settings.php'` navigue vers la racine du projet en comptant 4 niveaux de remontée. Après la migration de `settings.php` vers `app/config/settings.php`, ce calcul de chemin sera cassé dans les deux copies.

### 2.7 Références AJAX codées en dur (JavaScript)

3 références AJAX ne passent pas par la variable `cpassman_url` :

| Fichier | Ligne | URL codée en dur | Risque |
|---|---|---|---|
| `pages/items.js.php` | 216 | `'./sources/tree.php'` | Ne fonctionnera plus si les wrappers `/public/sources/` ne sont pas créés |
| `pages/items.js.php` | 235 | `'./sources/tree.php'` | Idem |
| `includes/core/load.js.php` | 2401 | `'sources/items.queries.php'` | Idem |

Les 24 autres références AJAX utilisent `$SETTINGS['cpassman_url'] . '/sources/...'` et fonctionneront automatiquement si les wrappers proxy sont en place dans `/public/sources/`.

> **Décision d'architecture pour la Phase 3** : l'option A du document de référence (wrappers proxy dans `/public/sources/`) est confirmée comme approche minimale. Les 3 URLs hardcodées ci-dessus devront être corrigées manuellement.

---

## 3. Inventaire des fichiers affectés par catégorie

### 3.1 Points d'entrée (entry points)

| Fichier | Chemin actuel | Chemin cible | Travail requis |
|---|---|---|---|
| `index.php` | `/index.php` | `/public/index.php` | Ajouter `TEAMPASS_ROOT`, `TEAMPASS_APP`, `TEAMPASS_STORAGE`. Adapter 30+ includes via `cpassman_dir`. |
| `error.php` | `/error.php` | `/public/error.php` | Adapter l'include de `sources/main.functions.php` |
| `self-unlock.php` | `/self-unlock.php` | `/public/self-unlock.php` | À vérifier |
| `api/index.php` | `/api/index.php` | `/public/api/index.php` | Adapter l'include de `bootstrap.php` |
| `api/inc/bootstrap.php` | `/api/inc/bootstrap.php` | `/app/api/inc/bootstrap.php` | Redéfinir `API_ROOT_PATH` |

### 3.2 Configuration (2 fichiers)

| Fichier | Travail requis |
|---|---|
| `includes/config/include.php` → `app/config/include.php` | Redéfinir `TEAMPASS_ROOT_PATH`, `LOG_TASKS_FILE`, `TASKS_LOCK_FILE`, `TASKS_TRIGGER_FILE` |
| `includes/config/settings.php` → `app/config/settings.php` | Mettre à jour `TEAMPASS_ROOT_PATH` si défini ici aussi ; vérifier `SECUREPATH` (déjà absolu, pas d'action requise) |

### 3.3 Sources (37 fichiers PHP)

Tous dans `/sources/` → `/app/sources/`. Les `require_once` internes entre fichiers sources utilisent `__DIR__` ou des chemins relatifs. Pas de chemin absolu vers des répertoires hors `/sources/` pour la plupart, sauf :

- `sources/main.functions.php` : references vers `files/`, avatars, trigger/lock files
- `sources/backup.functions.php` : utilisé depuis scripts et pages, chemin include relatif
- `sources/utilities.queries.php:3226` : backup dir → `/storage/files/`
- `sources/upload.files.php:169` : avatars

Pour chaque fichier source, un **wrapper proxy** doit être créé dans `/public/sources/` :
```php
<?php
// public/sources/items.queries.php
define('TEAMPASS_ROOT', dirname(__DIR__));
require_once TEAMPASS_ROOT . '/app/sources/items.queries.php';
```

**Nombre de wrappers à créer : 37**

### 3.4 Pages (81 fichiers)

- 54 fichiers `.php` → `/app/pages/`
- 27 fichiers `.js.php` → `/app/pages/`

Chemins HTML dans les templates (CSS/JS) : la majorité référence `includes/css/`, `includes/js/`, `plugins/`. Ces chemins URL changeront vers `assets/css/`, `assets/js/`, etc. Impact moyen mais **volume élevé**.

### 3.5 Scripts CLI (17 fichiers)

Tous dans `/scripts/` → `/app/scripts/`. Chaque script devra définir `TEAMPASS_ROOT` en en-tête. Les fallbacks vers `__DIR__.'/../files/'` devront être convertis en `TEAMPASS_STORAGE . '/logs/'`.

Cron jobs impactés :
```
# Avant
* * * * * php /var/www/html/TeamPass/scripts/background_tasks___handler.php

# Après
* * * * * php /var/www/html/TeamPass/app/scripts/background_tasks___handler.php
```

### 3.6 Installer (fichiers d'installation)

| Sous-répertoire | Nombre de fichiers | Spécificité |
|---|---|---|
| `install/install-steps/` | 6 fichiers | Chaque step fait `include __DIR__.'/../../includes/config/include.php'` et `include_once(__DIR__ . '/../tp.functions.php')` |
| `install/upgrade_run_*.php` | 8+ fichiers | Chemins hardcodés vers `files/`, `sources/`, `includes/` — **scripts historiques difficiles à tester** |
| `install/install.php` | 1 fichier | Entry point installer |
| `install/upgrade.php`, `install/upgrade_ajax.php` | 2 fichiers | `upgrade_ajax.php:986` a un `'../files/'` hardcodé |

> **Effet de bord — scripts d'upgrade historiques** : les scripts `upgrade_run_3.0.0.php`, `upgrade_run_3.1.5.php`, `upgrade_run_3.1.6.php`, `upgrade_run_3.1.7.php` contiennent des chemins hardcodés. Ils ne sont normalement rejoués qu'une fois lors d'une montée de version, mais si quelqu'un installe depuis zéro, ils seront tous exécutés. Il faut les adapter ou accepter qu'ils ne fonctionnent plus dans la nouvelle structure (auquel cas une note dans la doc d'installation est obligatoire).

### 3.7 API Controllers et Models

Utilisent `API_ROOT_PATH` (relatif à `api/`) et des remontées vers `sources/`. Une fois `API_ROOT_PATH` corrigé, la plupart des includes fonctionneront. Points de vigilance :
- `api/Model/FolderModel.php:119`, `AuthModel.php:54`, `ItemModel.php:174,462,629,835` : incluent `main.functions.php` via `API_ROOT_PATH . '/../sources/main.functions.php'` — chemin à mettre à jour
- `api/inc/jwt_utils.php:151` : inclut `encryption_utils.php` via `API_ROOT_PATH`

### 3.8 WebSocket

`/websocket/` → `/app/websocket/`. Le fichier `websocket/bin/server.php` et la configuration systemd (`teampass-websocket.service`) référencent le chemin absolu actuel. La directive `ExecStart` du service systemd devra être mise à jour.

---

## 4. Analyse des effets de bord non évidents

### 4.1 `cpassman_dir` vs `TEAMPASS_ROOT`

C'est **l'effet de bord le plus subtil**. La valeur de `cpassman_dir` dans la base de données est définie lors de l'installation et utilisée massivement dans `index.php` pour inclure des fichiers. Elle pointe vers la racine du projet.

Après la migration :
- `index.php` se trouvera dans `/public/`
- Les pages seront dans `/app/pages/`
- `cpassman_dir` devra être mis à jour en base (via un script d'upgrade) pour pointer vers `TEAMPASS_ROOT`
- Chaque include du type `$SETTINGS['cpassman_dir'] . '/pages/...'` devra être revu pour utiliser `TEAMPASS_APP . '/pages/...'`

Si `cpassman_dir` est mis à jour mais que les includes PHP ne sont pas adaptés, l'application sera cassée. Les deux doivent être faits ensemble (Phase 3).

### 4.2 Docker : document root non standard

Le nginx Docker actuel (`docker/nginx/teampass.conf`) a `root /var/www/html;` (pas `/var/www/html/TeamPass`). L'URL `/TeamPass/index.php` est donc l'accès actuel.

Après la migration, le `root` devrait devenir `/var/www/html/TeamPass/public`. Ce changement **casse l'URL d'accès actuelle** si elle incluait `/TeamPass/`. Il faut documenter ce point pour les utilisateurs Docker.

### 4.3 `ConfigManager` — localisation des deux copies

`ConfigManager` calcule le chemin vers `settings.php` avec :
```php
$settingsFile = __DIR__ . '/../../../../includes/config/settings.php';
```

Dans la nouvelle structure :
- `__DIR__` sera `app/includes/libraries/teampassclasses/configmanager/src/`
- Le calcul `../../../../` remonte à `app/`
- Il faudra `../../../../app/config/settings.php` ou adapter le calcul

Les deux copies doivent être mises à jour simultanément (cf. `CLAUDE.md` : "Always edit both locations together").

### 4.4 `phpseclibV1_autoload.php`

Ce fichier charge les classes phpseclib v1 depuis `__DIR__ . '/phpseclibV1/'`. Il se trouve dans `includes/libraries/`. Après la migration, ce chemin relatif reste valide si le fichier et la librairie migrent ensemble dans `/app/includes/libraries/`. Pas d'impact si la structure interne est préservée.

### 4.5 Répertoire `backups/`

Référencé dans la règle nginx `deny all` et dans le code pour les exports. Il n'est pas dans l'arborescence standard mais mérite d'être inventorié.
```bash
# À vérifier lors de l'audit en staging :
ls /var/www/html/TeamPass/backups/ 2>/dev/null
```

### 4.6 `SECUREPATH` / `SECUREFILE`

Déjà en chemin absolu externe au webroot (ex : `/var/www/TP_secrets/20220213/`). **Aucune modification requise.** À confirmer en staging.

### 4.7 Permissions PHP sur `/app/`

Dans la nouvelle structure, `www-data` doit pouvoir **lire** `/app/` mais pas écrire. Actuellement, si des fichiers sont créés dynamiquement dans `/includes/` ou `/sources/` (cas edge d'un plugin ou d'une mise à jour en ligne), cela ne sera plus possible. À vérifier : aucun code ne doit écrire dans ces répertoires.

### 4.8 `files_reference.txt`

`sources/admin.queries.php:3780` appelle `verifyFileHashes($SETTINGS['cpassman_dir'], __DIR__.'/../files_reference.txt')`. Ce fichier de checksums référence des chemins relatifs de fichiers de l'application. Après la migration, les checksums seront invalides jusqu'à régénération.

### 4.9 `_tools/` et `licences/`

Ces répertoires ne font pas partie de l'application web mais contiennent des scripts de développement. Ils ne sont pas migrés dans `/public/` ni dans `/app/` — ils resteront à la racine. Leurs `__DIR__` sont des chemins de développement uniquement.

---

## 5. Configuration web server actuelle — état des protections

### nginx (Docker)

```nginx
root /var/www/html;  # Attention : inclut TeamPass/ dans l'URL

# Protections en place :
location ~ /\. { deny all; }
location ~ /(includes/config|backups|install/upgrade_scripts|vendor)/ { deny all; return 404; }
location ~ /includes/libraries/.*\.php$ { deny all; return 404; }
location ~ /(\.|composer\.json|composer\.lock|package\.json|README\.md|CHANGELOG\.md) { deny all; return 404; }
```

**Protections manquantes** :
- `/sources/` n'est pas protégé → accès HTTP direct possible aux query handlers
- `/scripts/` n'est pas protégé → accès HTTP direct aux scripts CLI
- `/api/` en dehors du bloc `location /api/` peut être atteint

### `.htaccess` en place

| Fichier | Protection |
|---|---|
| `includes/config/.htaccess` | `Deny from all` (Apache uniquement, ignoré par nginx) |
| `files/.htaccess` | Désactive l'exécution PHP (Apache uniquement) |
| `upload/.htaccess` | Idem |
| `includes/avatars/.htaccess` | Idem |

> Toutes ces protections `.htaccess` sont **silencieusement ignorées par nginx**. La seule protection réelle côté nginx est la règle `deny` sur `includes/config`. Les répertoires `files/`, `upload/`, `sources/`, `scripts/` ne sont protégés par nginx que de manière implicite (pas de règle `deny`, mais le routing `try_files` empêche l'exécution directe dans certains cas).

---

## 6. Procédure de rollback

### 6.1 Principe

La migration se fait en branches Git séparées par phase. À tout moment, un rollback consiste à :

1. Revenir sur la branche `master`
2. Redéployer les fichiers depuis `master`
3. Restaurer le document root nginx à la valeur d'origine

### 6.2 Points de sauvegarde avant chaque phase

Avant de démarrer chaque phase, créer un **tag Git** :

```bash
git tag migration-phase-0-ready    # avant Phase 1
git tag migration-phase-1-ready    # avant Phase 2
# etc.
git push origin --tags
```

### 6.3 Rollback Phase 1–3 (scaffolding + bootstrap + sources)

```bash
# Revenir au dernier tag stable
git checkout master

# Restaurer le document root nginx
# docker/nginx/teampass.conf : root /var/www/html;
# (pas de changement à ce stade — le root nginx n'est modifié qu'en Phase 7)

# Redémarrer nginx
docker-compose restart nginx   # ou systemctl reload nginx
```

### 6.4 Rollback Phase 7 (changement document root nginx)

C'est la phase la plus risquée. Si le document root est changé et que quelque chose est cassé :

```bash
# Restaurer teampass.conf
git checkout master -- docker/nginx/teampass.conf

# Recharger nginx
docker-compose exec nginx nginx -s reload
```

> **Recommandation** : faire un snapshot VM/container avant la Phase 7.

### 6.5 Rollback base de données (`cpassman_dir`)

Si la valeur de `cpassman_dir` a été mise à jour en base et qu'un rollback est nécessaire :

```sql
UPDATE teampass_misc SET valeur = '/var/www/html/TeamPass' 
WHERE intitule = 'cpassman_dir' AND type = 'admin';
```

> Adapter la valeur selon l'installation. Documenter la valeur **avant** tout changement.

---

## 7. Environnement de staging

### 7.1 Recommandations

- Utiliser un container Docker dédié avec un fork de la branche `feature/public-private-structure`
- Ne pas travailler directement sur l'environnement de production
- Configurer un DNS ou entrée `/etc/hosts` de type `teampass-staging.local`
- Utiliser une base de données clonée (pas la production)

### 7.2 Checklist staging avant Phase 1

- [ ] Container Docker démarré avec la branche de travail
- [ ] Accès à l'interface web vérifié (`http://teampass-staging.local/TeamPass/`)
- [ ] Connexion utilisateur testée (admin + utilisateur standard)
- [ ] Background tasks testées (cron accessible)
- [ ] Valeur de `cpassman_dir` en base notée : ______________________
- [ ] Valeur de `cpassman_url` en base notée : ______________________
- [ ] Valeur de `SECUREPATH` notée (dans `settings.php`) : ______________________

---

## 8. Prérequis avant le démarrage de la Phase 1

Les points suivants doivent être résolus ou décidés avant de passer à la Phase 1 (scaffolding).

### 8.1 Décisions architecturales à valider

| Question | Recommandation du doc de référence | Décision |
|---|---|---|
| Strategy AJAX wrappers | Option A : proxies dans `/public/sources/` | ✅ **Confirmé et implémenté** — 37 wrappers créés dans `public/sources/` |
| `backups/` directory | Migration vers `/storage/backups/` ? | À décider |
| Scripts d'upgrade historiques | Adapter ou documenter comme non supportés post-migration | À décider |
| Valeur de `cpassman_dir` après migration | Pointer vers `TEAMPASS_ROOT`, pas `TEAMPASS_APP` | ✅ **Confirmé** — fallback dans `public/index.php` mis à jour |

### 8.2 Outillage PHPStan

Vérifier que PHPStan passe au niveau 4 avant toute modification :

```bash
vendor/bin/phpstan analyse
```

S'il y a des erreurs pre-existantes, les documenter pour ne pas les confondre avec des régressions introduites pendant la migration.

### 8.3 Inventaire des crontabs actifs

```bash
# Sur le serveur hébergeant TeamPass :
crontab -l -u www-data
# ou
cat /etc/cron.d/teampass 2>/dev/null
```

Documenter tous les chemins absolus utilisés dans les crons.

### 8.4 Inventaire du service WebSocket

```bash
systemctl status teampass-websocket 2>/dev/null
cat /var/www/html/TeamPass/websocket/config/teampass-websocket.service
```

---

## 9. Plan de tests de non-régression

À exécuter après **chaque phase** et impérativement avant la mise en production.

### 9.1 Tests fonctionnels minimaux

- [ ] Connexion admin (credentials locaux)
- [ ] Connexion LDAP (si activé)
- [ ] Connexion OAuth2/Azure (si activé)
- [ ] Création d'un item avec mot de passe
- [ ] Lecture et déchiffrement d'un item
- [ ] Upload d'une pièce jointe
- [ ] Download d'une pièce jointe
- [ ] Création d'un dossier
- [ ] Export CSV/HTML
- [ ] Import CSV
- [ ] Background tasks : lancement manuel et vérification log
- [ ] API REST : `GET /api/item/get?id=X` avec JWT valide
- [ ] WebSocket : connexion et reception d'un événement

### 9.2 Tests de sécurité minimaux

Après la Phase 7 (changement du document root nginx) :

```bash
# Ces URLs doivent toutes retourner 404 ou être vides
curl -I http://teampass-staging.local/sources/items.queries.php
curl -I http://teampass-staging.local/app/config/settings.php
curl -I http://teampass-staging.local/vendor/autoload.php
curl -I http://teampass-staging.local/scripts/background_tasks___handler.php
curl -I http://teampass-staging.local/includes/config/settings.php
```

---

## 10. Checklist de clôture de la Phase 0

- [x] Branche `feature/public-private-structure` créée
- [x] Audit des chemins `__DIR__` : **274 occurrences** recensées
- [x] Audit des includes relatifs `../` : **92 occurrences** recensées
- [x] Audit des URLs AJAX : **3 URLs hardcodées**, 24 via variable
- [x] Constantes de chemin identifiées : **6 constantes à redéfinir**
- [x] Effets de bord documentés : ConfigManager dual-location, cpassman_dir, fallbacks TASKS_*_FILE, upgrade scripts historiques, docker root, avatars
- [x] Protections nginx actuelles auditées
- [x] Procédure de rollback définie
- [ ] Staging environment opérationnel
- [ ] Valeurs de `cpassman_dir` et `cpassman_url` en base documentées
- [ ] PHPStan niveau 4 vérifié (baseline pré-migration)
- [ ] Crontabs et service WebSocket inventoriés
- [ ] Décisions architecturales validées (cf. §8.1)
- [ ] Tag Git `migration-phase-0-ready` posé

---

## 11. Travaux de correction des chemins réalisés (2026-04-12)

> Ces corrections dépassent le périmètre de la Phase 0 (audit seul). Elles ont été appliquées directement sur la branche `feature/public-private-structure` après le déplacement des répertoires (commit `19a26da34`).

### 11.1 `public/index.php` — Point d'entrée web

- ✅ Ajout des constantes `TEAMPASS_ROOT`, `TEAMPASS_APP`, `TEAMPASS_STORAGE` (avec guards `!defined()`) avant le premier `require_once`
- ✅ Tous les `require_once __DIR__.'/includes/...'` → `TEAMPASS_APP . '/...'`
- ✅ Tous les `require_once $SETTINGS['cpassman_dir'] . '/sources/...'` → `TEAMPASS_APP . '/sources/...'`
- ✅ Tous les `include $SETTINGS['cpassman_dir'] . '/pages/...'` → `TEAMPASS_APP . '/pages/...'`
- ✅ `include $SETTINGS['cpassman_dir'] . '/includes/core/login.php'` → `TEAMPASS_APP . '/core/login.php'`
- ✅ `include $SETTINGS['cpassman_dir'] . '/error.php'` → `include __DIR__ . '/error.php'`
- ✅ `include './includes/core/otv.php'` (×2) → `TEAMPASS_APP . '/core/otv.php'`
- ✅ `include_once 'includes/core/phpseclibv3_migration_modal.php'` → `TEAMPASS_APP . '/core/...'`
- ✅ Fallback `$SETTINGS['cpassman_dir'] = __DIR__` → `= TEAMPASS_ROOT`
- ✅ `file_exists(__DIR__ . '/includes/css/custom.css')` + href → `assets/css/custom.css`

**Reste à faire (phase HTML/templates) :** les URLs HTTP dans les `<link>` et `<script>` (`includes/css/`, `includes/js/`, `plugins/`, `includes/images/`) — chemins web, non PHP, à traiter lors de la mise à jour des templates.

### 11.2 `app/config/include.php` — Constantes critiques

- ✅ Ajout du bloc d'auto-définition des 3 constantes (permet l'utilisation depuis les scripts CLI sans passer par `public/index.php`)
- ✅ `TEAMPASS_ROOT_PATH` : `__DIR__.'/../../'` → `TEAMPASS_ROOT . '/'`
- ✅ `LOG_TASKS_FILE` : `'../files/teampass_tasks.log'` → `TEAMPASS_STORAGE . '/logs/teampass_tasks.log'`
- ✅ `TASKS_LOCK_FILE` : `''` → `TEAMPASS_STORAGE . '/logs/teampass_background_tasks.lock'`
- ✅ `TASKS_TRIGGER_FILE` : `''` → `TEAMPASS_STORAGE . '/logs/teampass_background_tasks.trigger'`

### 11.3 `public/sources/` — 37 wrappers proxy

- ✅ Répertoire `public/sources/` créé
- ✅ 37 fichiers proxy générés (un par fichier dans `app/sources/`) — chaque proxy définit `TEAMPASS_ROOT` et délègue via `require_once`
- ✅ `public/includes/core/logout.php` créé (proxy pour `app/core/logout.php`) — nécessaire car l'URL `./includes/core/logout.php` est référencée côté navigateur

### 11.4 `ConfigManager` — 2 copies

| Copie | Chemin | Correction |
|---|---|---|
| `app/includes/libraries/teampassclasses/configmanager/src/` | `settings.php` | `../../../../includes/config/` → `../../../../../config/` (5 niveaux) |
| idem | `meekrodb` | `/../../../sergeytsalkov/` → `/../../../../../vendor/sergeytsalkov/` |
| `app/vendor/teampassclasses/configmanager/src/` | `settings.php` | `../../../../includes/config/` → `../../../../../app/config/` (5 niveaux) |

### 11.5 Fallbacks `files/` dans les scripts

- ✅ `app/scripts/background_tasks___handler.php` : fallback trigger file → `TEAMPASS_STORAGE . '/logs/'`
- ✅ `app/scripts/background_tasks___handler.php` : fallback lock file (×2) → `TEAMPASS_STORAGE . '/logs/'`
- ✅ `app/sources/main.functions.php` : fallback trigger file dans `triggerBackgroundHandler()` → `TEAMPASS_STORAGE . '/logs/'`

### 11.6 `app/api/inc/bootstrap.php` — API

- ✅ Ajout des 3 constantes (`dirname(__DIR__, 3)` pour remonter à la racine depuis `app/api/inc/`)
- ✅ `require API_ROOT_PATH . '/../sources/main.functions.php'` → `require TEAMPASS_APP . '/sources/main.functions.php'`

### 11.7 Infrastructure

- ✅ Répertoire `storage/logs/` créé (requis pour les fichiers lock, trigger et log des tâches)

---

### Points non traités (phases ultérieures)

| Sujet | Section doc | Statut |
|---|---|---|
| URLs HTTP templates (`includes/css/`, `includes/js/`) | §3.4 | Phase 3 — HTML templates |
| `cpassman_dir` en base de données | §2.3, §4.1 | Phase 3 — script d'upgrade |
| Avatars (FS + URL) | §2.5 | Phase 3 |
| Scripts d'upgrade historiques (`upgrade_run_*.php`) | §2.4, §3.6 | Phase 3 |
| Configuration nginx (document root → `public/`) | §4.2 | Phase 7 |
| 3 URLs AJAX hardcodées en JS | §2.7 | Phase 3 (templates) |
| `cpassman_dir` / `cpassman_url` en base documentés | §10 | Prérequis Phase 1 encore ouvert |

---

*Ce document est la référence pour la Phase 0. Une fois la checklist complète, passer à `migration-public-private-phase-1.md`.*
