# GitHub Issue Analyzer — TeamPass

Analyse l'issue GitHub numéro $ARGUMENTS du dépôt TeamPass.

## 1. Collecte des données

```bash
gh issue view $ARGUMENTS \
  --repo nilsteampassnet/TeamPass \
  --json number,title,body,author,labels,comments,state,createdAt,updatedAt
```

Récupère également les commentaires complets :

```bash
gh issue view $ARGUMENTS \
  --repo nilsteampassnet/TeamPass \
  --comments
```

---

## 2. Classification de l'issue

Détermine la nature de l'issue parmi :

- 🐛 **Bug** — comportement anormal, erreur, régression
- 💡 **Feature request** — nouvelle fonctionnalité demandée
- ❓ **Question / Support** — usage ou configuration non compris
- 🔒 **Sécurité** — vulnérabilité potentielle (traiter avec discrétion)
- 📖 **Documentation** — manque ou erreur dans la doc
- 🔁 **Doublon** — vérifier via `gh issue list --search "mots clés"` si un issue similaire existe

---

## 3. Analyse selon le type

### Si c'est un Bug

**Évaluation de la reproductibilité :**
- Les étapes de reproduction sont-elles fournies ? Complètes ?
- La version de TeamPass est-elle précisée ?
- L'environnement est-il décrit (PHP, MySQL, serveur web, Docker ?) ?
- Y a-t-il un message d'erreur, un log, une stack trace ?

**Localisation probable dans le code :**
Cherche dans le code les fichiers/fonctions probablement impliqués en te basant sur les mots-clés du rapport.

```bash
grep -r "mot_clé" src/ --include="*.php" -l
```

**Évaluation de la sévérité :**
- 🔴 Critique — perte de données, sécurité, blocage total
- 🟠 Majeur — fonctionnalité principale cassée, pas de contournement
- 🟡 Mineur — dysfonctionnement avec contournement possible
- ⚪ Cosmétique — affichage, traduction, typo

---

### Si c'est une Feature Request

- La demande est-elle cohérente avec la philosophie de TeamPass (gestionnaire de mots de passe collaboratif) ?
- Existe-t-il déjà un mécanisme partiel répondant au besoin ?
- Quel serait l'impact sur l'architecture existante (chiffrement, droits, API) ?
- La demande est-elle réaliste pour un projet open-source maintenu par une seule personne ?

---

## 4. Pistes de solution

Si suffisamment d'informations sont disponibles, propose :

1. **Cause racine probable** — fichier(s), fonction(s), logique en cause
2. **Solution envisageable** — avec extrait de code si pertinent
3. **Risques / effets de bord** — ce qui pourrait être impacté par le fix

---

## 5. Questions à poser si le contexte est insuffisant

Génère un commentaire GitHub prêt à poster si des informations manquent.
Adapte les questions à ce qui est absent parmi :

```
Hi @{author},

Thanks for the report! To investigate further, could you please provide:

**Environment**
- [ ] TeamPass version (check Admin > About)
- [ ] PHP version (`php -v`)
- [ ] MySQL / MariaDB version
- [ ] Web server (Apache / Nginx) and version
- [ ] Installation type (standard / Docker)

**Reproduction**
- [ ] Step-by-step instructions to reproduce the issue
- [ ] Is it reproducible on a fresh TeamPass install?
- [ ] Does it affect all users or specific roles/groups?

**Error details**
- [ ] Full error message or stack trace (check browser console + server logs)
- [ ] Relevant entries from `includes/config/teampass-seclog.txt`
- [ ] Screenshot if the issue is visual

Thanks for helping make TeamPass better!
```

Inclure uniquement les blocs pertinents selon ce qui manque réellement dans le rapport.

---

## 6. Rapport de synthèse

Produis une synthèse structurée :

```
## Issue #$ARGUMENTS — [Titre]

**Type :** Bug / Feature / Question / ...
**Sévérité :** 🔴 / 🟠 / 🟡 / ⚪ (si bug)
**Reproductible tel quel :** Oui / Non / Informations insuffisantes

### Résumé
[2-3 phrases décrivant le problème et son contexte]

### Analyse
[Cause probable, fichiers concernés, logique impliquée]

### Solution proposée
[Approche recommandée avec référence aux fichiers/fonctions]

### Action recommandée
- [ ] Demander des compléments (voir commentaire généré)
- [ ] Corriger dans la branche release/3.1.6.x (hotfix)
- [ ] Planifier dans feature/... (développement)
- [ ] Clore comme doublon de #XXX
- [ ] Clore comme hors scope
```