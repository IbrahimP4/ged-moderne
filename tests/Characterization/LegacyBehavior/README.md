# Tests de Caractérisation — Golden Master

## Principe

Ces tests capturent le comportement **actuel** du legacy SeedDMS, qu'il soit bon ou mauvais.
Ils forment le filet de sécurité de la migration : si le nouveau code change le comportement
observable, le test échoue immédiatement.

## Utilisation

### Étape 1 — Capturer le golden master (une seule fois)

```bash
# Démarrer le legacy SeedDMS
cd /Users/ibrahim/Desktop/modele && php -S localhost:8080

# Capturer le comportement actuel
LEGACY_GED_URL=http://localhost:8080 \
GOLDEN_MASTER_MODE=capture \
vendor/bin/pest tests/Characterization
```

Les fichiers `.txt` générés dans `golden_master/` sont à **committer dans git**.
Ils représentent le contrat de comportement du système legacy.

### Étape 2 — Vérifier les régressions (à chaque changement)

```bash
LEGACY_GED_URL=http://localhost:8080 \
vendor/bin/pest tests/Characterization
```

## Fichiers générés

| Fichier | Ce qu'il capture |
|---|---|
| `login_echec.txt` | HTML retourné sur un login invalide |
| `folder_id_invalide.txt` | Comportement sur folderid=abc |
| `document_inexistant.txt` | Comportement sur un document 404 |

## Important

- Ces tests **ne prouvent pas** que le comportement est correct.
- Ils prouvent qu'il est **stable**.
- Quand on migre une feature vers le nouveau système, on déplace les assertions
  vers `tests/Functional/` avec les vraies assertions métier.
