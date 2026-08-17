# GitHub PR Analyzer — TeamPass

Analyse la Pull Request GitHub numéro $ARGUMENTS du dépôt TeamPass.

## 1. Collecte des données

```bash
gh pr view $ARGUMENTS \
  --repo nilsteampassnet/TeamPass \
  --json number,title,body,author,baseRefName,headRefName,labels,state,createdAt,updatedAt,reviews,comments,additions,deletions,changedFiles
```

Récupère le diff complet et la liste des fichiers modifiés :

```bash
gh pr diff $ARGUMENTS --repo nilsteampassnet/TeamPass

gh pr view $ARGUMENTS --repo nilsteampassnet/TeamPass --json files
```

---

## 2. Contexte de la PR

Détermine la nature du changement :

- 🐛 **Bugfix** — correction d'un comportement anormal
- 💡 **Feature** — nouvelle fonctionnalité
- ♻️ **Refactor** — restructuration sans changement de comportement
- 🔒 **Sécurité** — patch de vulnérabilité (traiter avec attention)
- 📖 **Documentation / Config** — CLAUDE.md, README, workflows CI
- 🔧 **Maintenance** — dépendances, migration, cleanup

Vérifie également si la PR :
- Cible la bonne branche (hotfix → `release/x.x.x`, feature → `master` ou `feature/...`)
- Correspond à une issue existante (cherche un lien `Fixes #XXX` ou `Closes #XXX` dans le body)
- Respecte la convention de nommage de branche (`pr-XXXX` pour les PR externes)

---

## 3. Analyse du diff

Le diff doit se faire en fonction de la branche develop du projet local.

### 3.1 Qualité du code PHP

Pour chaque fichier `.php` modifié, évalue :

- **Strict types** : `declare(strict_types=1)` présent sur les nouveaux fichiers ?
- **Typage** : les paramètres et retours de nouvelles fonctions sont-ils typés ?
- **PHPStan niveau 4** : les types utilisés sont-ils compatibles ? Pas de `mixed` non justifié, pas d'accès à des propriétés potentiellement null sans vérification ?
- **Docblocks** : toute nouvelle fonction publique a-t-elle un docblock ?
- **Nommage** : variables en anglais uniquement ?
- **Debugging** : aucun `var_dump()`, `print_r()`, `error_log()` laissé en place ?

### 3.2 Qualité du code JavaScript

Pour chaque fichier `.js` ou `.js.php` modifié :

- **Conventions ESLint** : guillemets simples, pas de point-virgule, indentation 2 espaces, `const` > `let` > (jamais `var`)
- **Debugging** : aucun `console.log()` laissé en place ?
- **Sécurité XSS** : les sorties HTML utilisent-elles DOMPurify ou l'échappement approprié ?

### 3.3 Sécurité

- **SQL injection** : toutes les requêtes utilisent-elles les placeholders MeekroDB (`%i`, `%s`, `%l`) ? Aucune concaténation de variables dans une query ?
- **XSS** : les sorties côté serveur passent-elles par `htmlspecialchars()` ou `voku/anti-xss` ?
- **CSRF** : les opérations d'écriture valident-elles le token CSRF ?
- **Authentification** : les handlers AJAX vérifient-ils la session (`$session->has('user-id')`) ?
- **Chiffrement** : si des données sensibles sont manipulées, le modèle RSA + sharekeys est-il respecté ? `decryptUserObjectKeyWithMigration()` est-il utilisé à la place de `decryptUserObjectKey()` ?
- **Chemins de fichiers** : si des fichiers sont accédés, `realpath()` est-il utilisé pour éviter les path traversals ?

### 3.4 Compatibilité base de données

- Les requêtes SQL sont-elles compatibles avec le mode `ONLY_FULL_GROUP_BY` de MySQL 5.7+ ?
- Si un schéma est modifié, un script `upgrade_run_X.X.X.php` est-il inclus ?
- Si `teampass_misc` est modifié, `ConfigManager::invalidateCache()` est-il appelé ?

### 3.5 Architecture et conventions

- Le pattern AJAX standard est-il respecté (`sources/*.queries.php` → JSON response) ?
- Si des dossiers sont modifiés, la classe `NestedTree` est-elle utilisée (jamais `nleft`/`nright` en direct) ?
- Si des événements temps réel sont ajoutés, les helpers WebSocket sont-ils utilisés (`emitItemEvent`, `emitFolderEvent`, etc.) ?
- Les classes `ConfigManager` et `SessionManager` dupliquées sont-elles mises à jour dans les deux emplacements ?

### 3.6 Impact install / upgrade

- Y a-t-il des modifications de schéma sans script d'upgrade ou d'installation ?
- Les nouvelles constantes sont-elles ajoutées dans `includes/config/include.php` ?
- La version `TP_VERSION` doit-elle être incrémentée ?

---

## 4. Risques et régressions

Identifie les zones à risque :

- Fonctions ou fichiers critiques modifiés (`main.functions.php`, `identify.php`, `core.php`, `items.queries.php`)
- Impact sur l'encryption (toute modification des flux RSA/AES est à haut risque)
- Impact multi-utilisateur (les sharekeys sont-elles régénérées pour tous les utilisateurs concernés ?)
- Compatibilité avec plusieurs rôles (admin, user standard, read-only)
- Compatibilité PHP 8.1+ / 8.2+
- Régressions potentielles sur les fonctionnalités adjacentes

---

## 5. Rapport de synthèse

Produis un rapport structuré :

```
## PR #$ARGUMENTS — [Titre]

**Type :** Bugfix / Feature / Refactor / ...
**Branche cible :** [nom de la branche]
**Issue liée :** #XXX ou Aucune
**Fichiers modifiés :** X (+Y / -Z lignes)

### Résumé
[2-3 phrases décrivant l'objectif et l'approche]

### Verdict global : ✅ Approuvable / ⚠️ À corriger / 🔴 Bloquant

---

### 🔴 Points bloquants
[Problèmes à corriger avant merge : sécurité, bugs introduits, mauvais pattern]

### 🟡 Points d'attention
[Améliorations souhaitables mais non bloquantes]

### 🟢 Points positifs
[Ce qui est bien fait dans la PR]

---

### Compatibilité PHPStan niveau 4
[Problèmes de typage détectés ou RAS]

### Sécurité
[Observations spécifiques ou RAS]

### Impact install / upgrade
[Script d'upgrade nécessaire ? Migrations manquantes ?]

### Action recommandée
- [ ] Approuver tel quel
- [ ] Approuver après correction des points 🟡
- [ ] Demander des corrections (points 🔴)
- [ ] Refuser (hors scope / mauvaise approche)
```
