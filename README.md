# GED Moderne

> **Système de Gestion Électronique de Documents** — Projet de stage développé pour l'entreprise **ENOF**, filiale du groupe **SONAREM**.

Remplacement moderne d'un ancien SeedDMS, construit sur une architecture hexagonale stricte (DDD + CQRS) côté backend et une SPA React moderne côté frontend.

---

## Stack Technique

| Couche | Technologie |
|---|---|
| Backend | PHP 8.4 · Symfony 7.4 LTS |
| API | API Platform 3.4 · JWT (Lexik) |
| Base de données | PostgreSQL 16 · Doctrine ORM 3 |
| Recherche | Meilisearch |
| Stockage fichiers | Flysystem (local / S3-ready) |
| Temps réel | SSE (Server-Sent Events) |
| File d'attente | Symfony Messenger |
| Frontend | React 19 · TypeScript · Vite 8 |
| State management | TanStack Query 5 · Zustand 5 |
| CSS | Tailwind CSS 4.2 |
| PDF | pdfjs-dist · pdf-lib · Signature Pad |
| Infra | Docker · Makefile |

---

## Prérequis

- **PHP** 8.4+
- **Composer** 2.x
- **Node.js** 20+ et **npm** (ou pnpm)
- **Docker** & **Docker Compose** (recommandé)
- **PostgreSQL** 16 (ou via Docker)

---

## Installation

### Avec Docker (recommandé)

```bash
# 1. Cloner le projet
git clone git@github.com:IbrahimP4/ged-moderne.git
cd ged-moderne

# 2. Copier la configuration
cp .env.example .env
# Éditer .env avec vos valeurs

# 3. Démarrer les containers
make up

# 4. Installer les dépendances et migrer
make install-docker
```

L'application est disponible sur **http://localhost:8000**

### Sans Docker (développement local)

```bash
# 1. Cloner le projet
git clone git@github.com:IbrahimP4/ged-moderne.git
cd ged-moderne

# 2. Configurer l'environnement
cp .env.example .env
# Éditer .env : DATABASE_URL, JWT keys, etc.

# 3. Installer les dépendances et configurer
make install

# 4. Lancer le serveur Symfony
symfony serve
```

#### Frontend

```bash
cd frontend
npm install
npm run dev
```

Le frontend démarre sur **http://localhost:5173**

---

## Variables d'environnement

Copiez `.env.example` en `.env` et renseignez les valeurs suivantes :

| Variable | Description | Exemple |
|---|---|---|
| `APP_SECRET` | Clé secrète Symfony (32 chars aléatoires) | `openssl rand -hex 16` |
| `DATABASE_URL` | Connexion PostgreSQL | `postgresql://user:pass@127.0.0.1:5432/ged` |
| `JWT_SECRET_KEY` | Chemin clé privée JWT | `%kernel.project_dir%/config/jwt/private.pem` |
| `JWT_PUBLIC_KEY` | Chemin clé publique JWT | `%kernel.project_dir%/config/jwt/public.pem` |
| `MAILER_DSN` | Transport email | `smtp://localhost:1025` |
| `MESSENGER_TRANSPORT_DSN` | File de messages | `doctrine://default?auto_setup=1` |
| `MEILISEARCH_URL` | URL Meilisearch | `http://localhost:7700` |
| `MEILISEARCH_API_KEY` | Clé API Meilisearch | — |

> **Ne jamais commiter `.env`** — seul `.env.example` (avec des valeurs fictives) doit être versionné.

---

## Commandes Make

```bash
make help           # Affiche toutes les commandes disponibles
```

### Docker
```bash
make up             # Démarre les containers (http://localhost:8000)
make down           # Arrête les containers
make logs           # Logs en temps réel
make shell          # Shell dans le container PHP
```

### Base de données
```bash
make migrate        # Applique les migrations
make migration      # Crée une nouvelle migration
make fixtures       # Charge les données de test
make db-reset       # Recrée la BDD (⚠ perte de données)
```

### Tests
```bash
make test           # Tous les tests
make test-unit      # Tests unitaires
make test-integration  # Tests d'intégration
make test-functional   # Tests fonctionnels (HTTP)
make coverage       # Rapport de couverture (min 85%)
make mutation       # Tests de mutation (Infection)
```

### Qualité de code
```bash
make qa             # Suite complète : cs + stan + tests
make stan           # Analyse statique PHPStan (level 9)
make cs-fix         # Correction de style automatique
make rector-fix     # Modernisation du code (Rector)
```

---

## Architecture Backend

Le backend suit une **architecture hexagonale** stricte (DDD + CQRS) :

```
src/
├── Domain/          # Entités, Value Objects, Interfaces repositories, Events
│   ├── Document/    # Agrégat Document (pivot central)
│   ├── Folder/      # Arborescence documentaire
│   ├── User/        # Gestion des utilisateurs
│   ├── Signature/   # Signatures électroniques
│   ├── AuditLog/    # Traçabilité des actions
│   ├── Messaging/   # Messagerie interne
│   ├── Notification/
│   └── Port/        # Interfaces (Storage, Search, Mail…)
├── Application/     # Handlers Commands/Queries, DTOs, Event Listeners
├── Infrastructure/  # Implémentations (Doctrine, Flysystem, Meilisearch)
└── UI/
    ├── Http/        # Contrôleurs JSON (minces, délèguent aux handlers)
    └── Console/     # Commandes CLI (migration legacy SeedDMS)
```

**Règles d'architecture :**
- `Domain` : zéro dépendance externe
- `Application` : orchestre, n'accède jamais à l'infrastructure directement
- Les controllers HTTP injectent les handlers et retournent du JSON
- CQRS : **Command** = mutation (pas de retour), **Query** = lecture (retourne un DTO)

---

## Architecture Frontend

```
frontend/src/
├── pages/       # Pages complètes (lazy-loaded via React Router)
├── features/    # Composants métier (DocumentList, FolderTree…)
├── components/  # UI génériques (Button, Modal, Badge…)
├── api/         # Fonctions d'appel API (axios)
├── store/       # État global Zustand (auth, thème, realtime)
├── hooks/       # Custom hooks React
├── types/       # Interfaces TypeScript (DTOs)
└── lib/
    └── axios.ts # Instance axios préconfigurée (Bearer token auto)
```

---

## Services disponibles (Docker)

| Service | URL |
|---|---|
| Application | http://localhost:8000 |
| Frontend (dev) | http://localhost:5173 |
| Mailpit (emails) | http://localhost:8025 |
| Meilisearch | http://localhost:7700 |

---

## Fonctionnalités principales

- 📁 **Gestion documentaire** : arborescence de dossiers, upload, versioning
- 🔍 **Recherche full-text** via Meilisearch (contenu PDF, Word indexé)
- ✍️ **Signature électronique** des documents
- 📋 **Workflow documentaire** (validation, révision, archivage)
- 🔔 **Notifications temps réel** via Server-Sent Events (SSE)
- 💬 **Messagerie interne**
- 📊 **Tableau de bord** avec statistiques (Recharts)
- 🔐 **Authentification JWT** avec contrôle des rôles
- 🪵 **Audit log** complet de toutes les actions

---

## Licence

Propriétaire — © ENOF / SONAREM. Tous droits réservés.
