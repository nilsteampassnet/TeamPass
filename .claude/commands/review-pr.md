# PR Review Agent

Analyse la Pull Request GitHub numéro $ARGUMENTS de ce dépôt.

## Étapes à suivre

1. Récupère les informations de la PR via `gh pr view $ARGUMENTS --json title,body,author,baseRefName,headRefName,files,reviews,comments`
2. Récupère le diff complet : `gh pr diff $ARGUMENTS`
3. Liste les fichiers modifiés : `gh pr view $ARGUMENTS --json files`

## Analyse à produire

Pour chaque fichier modifié, évalue :
- **Qualité du code** : lisibilité, complexité cyclomatique, duplication
- **Risques** : régressions potentielles, cas limites non couverts
- **Sécurité** : injections, exposition de secrets, permissions
- **Cohérence** : respect des conventions du projet (voir CLAUDE.md)

## Format de sortie

Produis un rapport structuré :
- Résumé exécutif (2-3 lignes)
- Score global /10
- Points bloquants (🔴)
- Points d'attention (🟡)  
- Points positifs (🟢)
- Suggestions concrètes avec exemples de code si pertinent
```

**Utilisation dans le terminal Claude Code :**
```
/review-pr 142