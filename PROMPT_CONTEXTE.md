# Contexte complet du projet GED Moderne

> **Ce fichier est le point d'entrée pour tout développeur ou IA intervenant sur ce projet.**  
> Lis-le intégralement avant de toucher à quoi que ce soit.

---

## 1. Vue d'ensemble

**GED Moderne** est un système de gestion électronique de documents (GED) full-stack, développé pour remplacer un ancien SeedDMS. Il est conçu avec une architecture hexagonale stricte (DDD + CQRS + Events) côté backend, et une SPA React moderne côté frontend.

| Couche | Technologie |
|---|---|
| Backend | PHP 8.4, Symfony 7.4 LTS |
| ORM | Doctrine ORM 3.3 |
| Frontend | React 19, TypeScript, Vite |
| State management | TanStack Query 5 + Zustand 5 |
| CSS | Tailwind CSS 4.2 |
| Base de données | SQLite (dev local) / MySQL 8.4 (Docker) |
| Auth | JWT (Lexik JWT Bundle) |
| Stockage fichiers | Flysystem (local ou S3) |
| Temps réel | SSE (Server-Sent Events) |
| Icônes | Lucide React |
| Notifications UI | Sonner (toast) |
| HTTP client | Axios |

**Branding :** jaune `#F5A800`, charbon `#1A1A1A`, fond `#F4F4F4`, cartes blanches `#FFFFFF`.

---

## 2. Structure des dossiers

```
ged-moderne/
├── src/                          # Backend Symfony (PHP)
│   ├── Domain/                   # Entités, Value Objects, Events, Interfaces repositories
│   ├── Application/              # Commandes, Queries, Handlers, DTOs, Event Listeners
│   ├── Infrastructure/           # Implémentations Doctrine, Flysystem, Messenger, Security
│   └── UI/
│       ├── Http/Controller/      # Contrôleurs Symfony (JSON API)
│       └── Console/              # Commandes CLI (migration legacy)
├── frontend/                     # Frontend React (TypeScript)
│   └── src/
│       ├── pages/                # Pages complètes (lazy-loaded)
│       ├── features/             # Composants métier
│       ├── components/           # UI génériques (Button, Modal, Badge…)
│       ├── api/                  # Fonctions d'appel API (axios)
│       ├── store/                # État global Zustand (auth, theme, realtime)
│       ├── hooks/                # Custom hooks React
│       ├── types/index.ts        # Interfaces TypeScript de tous les DTOs
│       ├── lib/axios.ts          # Instance axios configurée (Bearer token auto)
│       └── App.tsx               # Router React avec lazy loading
├── config/                       # Configuration Symfony
├── migrations/                   # Migrations Doctrine
└── Makefile                      # Commandes de développement
```

---

## 3. Architecture backend — règles impératives

### 3.1 Architecture hexagonale

```
Domain  ←  Application  ←  Infrastructure
                       ←  UI (HTTP Controllers)
```

- **Domain** : aucune dépendance externe. Entités, Value Objects, interfaces des repositories, events.
- **Application** : orchestration. Handlers de Commands/Queries, DTOs de sortie, Event Listeners.
- **Infrastructure** : implémentations concrètes (Doctrine, Flysystem, Messenger).
- **UI** : controllers HTTP minces — ils injectent les handlers, valident l'input, retournent du JSON.

### 3.2 CQRS

- **Command** = mutation (pas de retour de données métier).
- **Query** = lecture (retourne un DTO).
- Les handlers sont des services Symfony invocables (`__invoke`).
- Les controllers appellent directement `($this->someHandler)(new SomeCommand(...))`.

### 3.3 Value Objects UUID

Chaque agrégat a son propre type UUID custom Doctrine :

| Value Object | Type Doctrine |
|---|---|
| `DocumentId` | `document_id` |
| `FolderId` | `folder_id` |
| `UserId` | `user_id` |
| `SignatureRequestId` | `signature_request_id` |
| `FolderPermissionId` | `folder_permission_id` |
| `NotificationId` | `notification_id` |
| `MessageId` | `message_id` |
| `AuditLogId` | `audit_log_id` |

Tous héritent de `AbstractUuidType` dans `src/Infrastructure/Persistence/Doctrine/Type/`.

### 3.4 Sérialisation JSON

Le Symfony Serializer **n'est pas configuré** avec un convertisseur snake_case → camelCase.  
Les propriétés PHP `readonly public` sont sérialisées **telles quelles** (camelCase).  
→ Les DTOs doivent avoir des propriétés camelCase pour correspondre à ce qu'attend le frontend.

Exemple correct dans `SignatureRequestDTO` :
```php
public string $documentId,   // ✅ → "documentId" en JSON
public string $document_id,  // ❌ → "document_id" en JSON (ne correspond pas au frontend)
```

### 3.5 Voters de sécurité

- `DocumentVoter` : contrôle l'accès par propriétaire ou admin.
- `FolderVoter` : contrôle l'accès selon `FolderPermission` et le flag `restricted`.
- Les admins (`ROLE_ADMIN`) ont toujours accès à tout.

---

## 4. Entités et schéma de base de données

### users
```
id (CHAR 36, UUID)  username (VARCHAR 100, unique)  email (VARCHAR 255, unique)
hashed_password     is_admin (bool)                 is_active (bool)
created_at
```

### documents
```
id (CHAR 36)        title (VARCHAR 255)             comment (TEXT nullable)
status (ENUM: draft | pending_review | approved | rejected | archived | obsolete)
folder_id (FK)      owner_id (FK)                   tags (JSON)
created_at          updated_at                       deleted_at (soft delete)
```

**Workflow statuts :**
```
draft → pending_review → approved
                       → rejected → draft (corrigeable)
approved → archived → obsolete
```

### document_versions
```
id  document_id (FK)  version_number (int)  original_filename
mime_type             file_size_bytes        storage_path
comment (nullable)    uploaded_by (FK)       uploaded_at
```

### document_comments
```
id  document_id (FK)  author_id (FK)  content (LONGTEXT)  created_at
```

### document_favorites
```
id (auto-inc)  document_id (FK)  user_id (FK)  created_at
UNIQUE(document_id, user_id)
```

### folders
```
id (CHAR 36)  name (VARCHAR 255)  comment (TEXT nullable)
parent_id (FK nullable, auto-référentiel)  owner_id (FK)
restricted (bool)  created_at  updated_at
```

### folder_permissions
```
id (CHAR 36)  folder_id (FK)  user_id (FK)
level (ENUM: read | write)    granted_by (FK nullable)   granted_at
UNIQUE(folder_id, user_id)
```

### signature_requests
```
id (CHAR 36)        document_id (FK)    requester_id (FK)
signer_id (FK)      status (ENUM: pending | signed | declined)
message (TEXT nullable)   comment (TEXT nullable)
requested_at        resolved_at (nullable)
```

### profile_signatures
```
id (CHAR 36)  user_id (FK, UNIQUE)  data_url (LONGBLOB - base64 canvas)
created_at    updated_at
```

### company_stamp
```
id (int PK=1, singleton)  data_url (CLOB)  updated_at  updated_by (FK nullable)
```

### messages
```
id (CHAR 36)  sender_id (FK)  recipient_id (FK)
content (TEXT)  is_read (bool)  created_at
```

### notifications
```
id (CHAR 36)   user_id (FK)          type (VARCHAR)
title (VARCHAR)  content (TEXT)      related_entity_id (CHAR 36 nullable)
is_read (bool)   created_at
```

### audit_log
```
id (CHAR 36)  event_name (VARCHAR)    aggregate_type (VARCHAR)
aggregate_id (CHAR 36)  actor_id (CHAR 36 nullable)
payload (JSON)  occurred_at
```

---

## 5. Toutes les routes API

> Préfixe commun : `/api`  
> Auth : header `Authorization: Bearer {jwt_token}` sur toutes les routes sauf `/api/login`  
> Rôle admin requis sur les routes `/api/admin/*` et les actions de workflow document

```
POST   /api/login                                    Authentification JWT

# Documents
GET    /api/documents                                Liste (paginée, filtrable)
GET    /api/documents/trash                          Documents supprimés (soft delete)
GET    /api/documents/favorites                      Documents favoris
POST   /api/documents/bulk-export                   ZIP export (body: {"ids": [...]})
GET    /api/documents/{id}                           Détail + versions
POST   /api/documents                                Upload nouveau fichier
PATCH  /api/documents/{id}/submit                   Soumettre pour révision
PATCH  /api/documents/{id}/approve                  Approuver (admin)
PATCH  /api/documents/{id}/reject                   Rejeter (admin)
PATCH  /api/documents/{id}/rename                   Renommer
PATCH  /api/documents/{id}/move                     Déplacer (body: {"folder_id": "..."})
PATCH  /api/documents/{id}/tags                     Mettre à jour les tags (body: {"tags": [...]})
DELETE /api/documents/{id}                          Soft delete
PATCH  /api/documents/{id}/restore                  Restaurer depuis la corbeille
DELETE /api/documents/{id}/permanent               Suppression définitive (admin)
POST   /api/documents/{id}/favorite                Ajouter aux favoris
DELETE /api/documents/{id}/favorite                Retirer des favoris
GET    /api/documents/{id}/comments                Liste des commentaires
POST   /api/documents/{id}/comments               Ajouter un commentaire
DELETE /api/documents/{id}/comments/{commentId}  Supprimer un commentaire
GET    /api/documents/{id}/download               Télécharger le fichier actuel
POST   /api/documents/{id}/versions              Ajouter une nouvelle version

# Recherche
GET    /api/search?q=...&folderId=...&status=...&tags[]=...  Recherche full-text

# Dossiers
GET    /api/folders                              Dossiers racine
GET    /api/folders/{id}                         Contenu du dossier (sous-dossiers + docs)
POST   /api/folders                              Créer (body: {"name": "...", "parent_id": null|"..."})
PATCH  /api/folders/{id}                         Renommer
DELETE /api/folders/{id}                         Supprimer
GET    /api/folders/{id}/export                  Exporter en ZIP

# Permissions dossiers (admin uniquement)
GET    /api/admin/folders/{id}/permissions                        Lister les accès
POST   /api/admin/folders/{id}/permissions                        Accorder (body: {"user_id": "...", "level": "read|write"})
DELETE /api/admin/folders/{id}/permissions/{userId}              Révoquer
PATCH  /api/admin/folders/{id}/restrict                           Toggle restreint (body: {"restricted": true|false})

# Demandes de signature
POST   /api/signature-requests                                    Créer une demande
GET    /api/signature-requests                                    Lister (admin: tout, user: ses demandes)
GET    /api/signature-requests/pending-count                     Compteur badge
GET    /api/signature-requests/available-signers                Admins disponibles comme signataires
PATCH  /api/signature-requests/{id}/sign                         Signer (body: {"comment": null|"..."})
PATCH  /api/signature-requests/{id}/decline                     Refuser (body: {"comment": null|"..."})

# Signature personnelle
GET    /api/profile/signature                                     Récupérer sa signature
POST   /api/profile/signature                                     Sauvegarder (body: {"data_url": "data:image/png;base64,..."})
DELETE /api/profile/signature                                     Supprimer

# Tampon entreprise (admin)
GET    /api/company-stamp
POST   /api/company-stamp                                         body: {"data_url": "..."}
DELETE /api/company-stamp

# Messages
GET    /api/messages/conversations                                Liste des conversations
GET    /api/messages/conversation/{partnerId}                    Historique avec un utilisateur
POST   /api/messages                                              Envoyer (body: {"recipient_id": "...", "content": "..."})
GET    /api/messages/unread-count                                Compteur non-lus

# Notifications
GET    /api/notifications
GET    /api/notifications/unread-count
PATCH  /api/notifications/{id}/read                              Marquer une notification lue
POST   /api/notifications/read-all                               Tout marquer lu

# Utilisateurs
GET    /api/users                                                 Liste publique (pour dropdowns)
GET    /api/admin/users                                           Liste admin avec détails
POST   /api/admin/users                                           Créer un user
PATCH  /api/admin/users/{id}/role                                Changer le rôle (body: {"make_admin": true|false})

# Dashboard (admin)
GET    /api/dashboard/stats
GET    /api/dashboard/uploads-by-day

# Audit (admin)
GET    /api/admin/audit

# Temps réel
GET    /api/sse                                                   Server-Sent Events stream
```

---

## 6. Frontend — pages et composants

### Pages (toutes lazy-loaded avec React.lazy)

| Fichier | Route | Description |
|---|---|---|
| `pages/DashboardPage.tsx` | `/` | Statistiques, graphiques (recharts), activité récente |
| `pages/FolderPage.tsx` | `/folders/:id` | Navigateur de dossiers, tree sidebar, table docs avec bulk mode |
| `pages/DocumentPage.tsx` | `/documents/:id` | Détail doc, workflow, versions, commentaires, signatures |
| `pages/SearchPage.tsx` | `/search` | Recherche full-text, filtres statut, tri, Cmd+K |
| `pages/MySignaturesPage.tsx` | `/signatures` | Demandes reçues (avec boutons Signer/Refuser) + envoyées |
| `pages/FavoritesPage.tsx` | `/favorites` | Documents favoris |
| `pages/TrashPage.tsx` | `/trash` | Corbeille avec restauration |
| `pages/MessagesPage.tsx` | `/messages` | Messagerie interne |
| `pages/ProfilePage.tsx` | `/profile` | Profil, signature canvas, changement mot de passe |
| `pages/admin/UsersPage.tsx` | `/admin/users` | Gestion utilisateurs |
| `pages/admin/SignatureRequestsPage.tsx` | `/admin/signatures` | Toutes les demandes de signature |
| `pages/admin/AuditPage.tsx` | `/admin/audit` | Journal d'événements |
| `features/auth/LoginPage.tsx` | `/login` | Formulaire de connexion JWT |

### Composants features importants

| Fichier | Rôle |
|---|---|
| `features/documents/UploadModal.tsx` | Upload fichier avec drag & drop |
| `features/documents/MoveDocumentModal.tsx` | Déplacer un doc (picker de dossier) |
| `features/documents/DocumentRow.tsx` | Ligne de table avec checkbox bulk mode |
| `features/documents/DocumentPreviewModal.tsx` | Prévisualisation inline |
| `features/folders/FolderTree.tsx` | Arbre hiérarchique de dossiers |
| `features/folders/PermissionsModal.tsx` | Gestion accès restreints d'un dossier |
| `features/signatures/RequestSignatureModal.tsx` | Demander une signature |
| `features/signatures/PlaceSignatureModal.tsx` | Placer signature sur PDF |
| `features/signatures/SignatureCanvas.tsx` | Canvas dessin de signature |
| `features/documents/DocumentCommentSection.tsx` | Fil de commentaires |

### Composants UI génériques

`Button`, `Modal`, `Badge`, `TagBadge`, `TagEditor`, `ConfirmModal`, `Spinner`/`PageSpinner`, `NotificationBell`, `Pagination`

### API functions (frontend/src/api/)

| Fichier | Fonctions principales |
|---|---|
| `documents.ts` | `getDocuments`, `getDocument`, `uploadDocument`, `deleteDocument`, `moveDocument`, `bulkDeleteDocuments`, `bulkMoveDocuments`, `bulkExportDocuments` |
| `folders.ts` | `getFolders`, `getFolder`, `createFolder`, `renameFolder`, `deleteFolder` |
| `signatureRequests.ts` | `getSignatureRequests`, `signRequest`, `declineRequest`, `createSignatureRequest` |
| `folderPermissions.ts` | `getFolderPermissions`, `setFolderPermission`, `removeFolderPermission`, `setFolderRestricted` |
| `search.ts` | `searchDocuments(q, { folderId, status, tags })` |
| `adminUsers.ts` | `listUsers`, `createUser`, `changeUserRole` |
| `auth.ts` | `login(username, password)` → retourne `{ token, isAdmin, username }` |
| `normalizers.ts` | `normalizeDocument(raw)` — transforme les champs backend → types frontend |
| `realtimeApi.ts` | Connexion SSE, gestion reconnexion |

### Stores Zustand

```typescript
// auth.ts
{ token: string|null, isAdmin: boolean, username: string|null }
// → login(token, isAdmin, username), logout()

// theme.ts
{ theme: 'light'|'dark', toggle() }

// realtimeStore.ts
{ notifications: Notification[], unreadCount: number }
```

### Hooks custom

| Hook | Rôle |
|---|---|
| `usePageTitle(title)` | Met à jour `document.title` |
| `useJwtExpiry()` | Surveille expiration JWT, déclenche logout automatique |
| `useRealtimeStream()` | Écoute le flux SSE `/api/sse` |
| `useNotificationSound()` | Joue un son à chaque notification |

### Types TypeScript clés (frontend/src/types/index.ts)

```typescript
type SignatureRequestStatus = 'pending' | 'signed' | 'declined'
type DocumentStatus = 'draft' | 'pending_review' | 'approved' | 'rejected' | 'archived' | 'obsolete'
type PermissionLevel = 'read' | 'write'

interface DocumentDTO {
  id: string; title: string; status: DocumentStatus; tags: string[]
  folderId: string; folderName?: string; ownerId: string; ownerUsername: string
  createdAt: string; updatedAt: string; deletedAt: string|null
  currentVersion: DocumentVersionDTO | null; isFavorite: boolean; comment: string|null
}

interface SignatureRequestDTO {
  id: string; status: SignatureRequestStatus; statusLabel: string
  message: string|null; comment: string|null
  requestedAt: string; resolvedAt: string|null
  documentId: string; documentTitle: string
  requesterId: string; requesterUsername: string
  signerId: string; signerUsername: string
}

interface FolderPermissionsDTO {
  folderId: string; folderName: string; restricted: boolean
  permissions: FolderPermission[]
}

interface FolderPermission {
  userId: string; username: string; level: PermissionLevel; grantedAt: string
}
```

---

## 7. Conventions de code

### Backend PHP

- **Strict types** : `declare(strict_types=1)` dans tous les fichiers.
- **Readonly** : les entités domain utilisent `private readonly` sur les propriétés immuables.
- **Constructeurs privés** : les entités sont créées via des factory methods statiques (`create`, `grant`, `generate`).
- **Immutabilité** des Value Objects : jamais de setter, toujours retourner une nouvelle instance.
- **PHPStan niveau 9** : types stricts partout, pas de `mixed` sans raison.
- **Handlers invocables** : `public function __invoke(Command $c): void`.
- **Controllers minces** : validation → handler → JSON response. Pas de logique métier.

### Frontend TypeScript

- **Pas de `any`** — tous les types sont définis dans `types/index.ts`.
- **TanStack Query** pour tout appel API (pas de `useEffect` + `fetch` manuellement).
- **`queryKey` conventions** : `['documents']`, `['folder', id]`, `['signature-requests']`, `['signature-pending-count']`.
- **Mutations** : `useMutation` avec `onSuccess` qui appelle `qc.invalidateQueries(...)`.
- **Tailwind pur** : pas de CSS modules, pas de styled-components. Classes Tailwind directement dans le JSX.
- **Couleurs exactes du projet** : `#F5A800` (jaune), `#1A1A1A` (charbon), `#F4F4F4` (fond), `#E8E8E8` (bordure), `#555` (texte secondaire), `#888` (texte tertiaire).
- **Dark mode** : géré via CSS custom properties dans `index.css` + classe `.dark` sur `<html>`. Ne pas ajouter de classes `dark:` Tailwind inline — utiliser les variables CSS.

---

## 8. Flux typiques à connaître

### Upload d'un document
1. `POST /api/documents` (multipart/form-data : `file`, `folder_id`, `title`, `comment`, `tags[]`)
2. Backend : `UploadDocumentCommand` → crée `Document` + `DocumentVersion` + event `DocumentUploaded`
3. Listener async : indexation Meilisearch, notification au propriétaire du dossier

### Workflow document
```
Utilisateur : PATCH /api/documents/{id}/submit
Admin       : PATCH /api/documents/{id}/approve  ou  /reject
```
→ Chaque transition génère un event `DocumentStatusChanged` → notification push SSE

### Demande de signature
1. Utilisateur : `POST /api/signature-requests` (`{document_id, signer_id, message?}`)
2. Admin reçoit une notification SSE + badge dans la sidebar
3. Admin va dans **"Mes signatures"** (route `/signatures`) OU **"Admin → Signatures"** (route `/admin/signatures`)
4. Admin clique Signer → `PATCH /api/signature-requests/{id}/sign` (`{comment?}`)

### Permissions dossier restreint
1. Admin ouvre la modale permissions sur un dossier
2. Toggle "Accès restreint" → `PATCH /api/admin/folders/{id}/restrict` (`{restricted: true}`)
3. La section "Ajouter un accès" apparaît seulement quand `restricted === true`
4. Admin sélectionne un utilisateur + niveau → `POST /api/admin/folders/{id}/permissions`

### Opérations en masse (bulk)
- Activer le "bulk mode" dans `FolderPage` via le bouton Layers
- Cocher des documents → barre flottante apparaît en bas
- "Télécharger ZIP" → `POST /api/documents/bulk-export` (`{ids: [...]}`) → stream ZIP
- "Déplacer" → `MoveDocumentModal` → `PATCH /api/documents/{id}/move` pour chaque doc
- "Supprimer" → `ConfirmModal` → bulk delete

---

## 9. État actuel et fonctionnalités implémentées

✅ **Authentification JWT** — login, logout, auto-expiry, refresh  
✅ **Gestion des dossiers** — CRUD hiérarchique, arbre de navigation  
✅ **Gestion des documents** — upload, versioning, workflow complet  
✅ **Recherche** — full-text avec filtres (statut, dossier, tags), debounce 350ms, Cmd+K  
✅ **Permissions dossiers** — mode restreint, accès par utilisateur (read/write)  
✅ **Demandes de signature** — création, sign/decline avec commentaire, notifications  
✅ **Signatures numériques** — canvas dessin, tampon entreprise, placement sur PDF  
✅ **Commentaires** — fil par document  
✅ **Favoris** — star/unstar documents  
✅ **Corbeille** — soft delete + restauration + suppression définitive  
✅ **Messagerie interne** — conversations entre utilisateurs  
✅ **Notifications temps réel** — SSE, badge, son  
✅ **Dark mode** — toggle global, CSS variables  
✅ **Code splitting** — React.lazy sur toutes les pages (bundle principal 214 KB)  
✅ **Opérations en masse** — sélection multi-docs, ZIP, déplacer, supprimer  
✅ **Dashboard admin** — stats, graphique uploads/jour  
✅ **Audit log** — traçabilité de tous les événements  
✅ **Gestion utilisateurs** — créer, changer de rôle  

### Ce qui n'est PAS encore implémenté

❌ Prévisualisation PDF inline (le composant `DocumentPreviewModal.tsx` existe mais n'est pas complet)  
❌ Versioning avec diff visuel entre versions  
❌ Drag & drop pour l'upload (bouton classique actuellement)  
❌ Export audit log en CSV  
❌ Recherche full-text dans le contenu des fichiers (titre/méta seulement)  

---

## 10. Commandes de développement

```bash
# Démarrer le backend Symfony (depuis la racine)
symfony serve -d
# ou
php -S 127.0.0.1:8000 -t public/

# Démarrer le frontend (depuis frontend/)
cd frontend && npm run dev

# Build frontend pour vérifier les erreurs TypeScript
cd frontend && npm run build

# Vérifier toutes les routes API
php bin/console debug:router | grep api

# Synchroniser le schéma DB (si mapping désynchronisé)
php bin/console doctrine:schema:update --force

# Valider le schéma
php bin/console doctrine:schema:validate

# Vider le cache Symfony
php bin/console cache:clear

# Charger les fixtures de test
php bin/console doctrine:fixtures:load

# Docker (si on utilise Docker)
make up        # démarrer tous les services
make down      # arrêter
make shell     # shell PHP
make migrate   # migrations
```

---

## 11. Points d'attention et pièges connus

1. **Sérialisation camelCase** : le Symfony Serializer utilise les noms de propriétés PHP tels quels. Les DTOs doivent avoir des propriétés camelCase pour correspondre aux attentes du frontend TypeScript.

2. **Types Doctrine custom** : chaque Value Object UUID a son type Doctrine enregistré. Si tu ajoutes un nouvel agrégat, il faut créer le type dans `src/Infrastructure/Persistence/Doctrine/Type/` et l'enregistrer dans `config/packages/doctrine.yaml`.

3. **Schema SQLite** : `doctrine:schema:validate` peut signaler des erreurs "not in sync" pour les contraintes FK sur SQLite (limitation SQLite). Le `--force` a bien été exécuté et le schéma est fonctionnel.

4. **Invalidation de cache React Query** : après une mutation, toujours invalider les bons `queryKey`. Ex : après signer une demande, invalider `['my-signature-requests']` ET `['signature-pending-count']` ET `['signature-requests']`.

5. **Auth store** : contient uniquement `{ token, isAdmin, username }`. Il n'y a **pas** de `userId` dans le store. Pour comparer l'utilisateur courant dans les DTOs, utiliser `username` (ex : `req.signerUsername === username`).

6. **Dark mode** : utiliser les CSS custom properties (`var(--bg-page)`, `var(--text-primary)`, etc.) définies dans `index.css`. Ne pas ajouter de classes `dark:` Tailwind directement dans les composants.

7. **Bulk export** : endpoint `POST /api/documents/bulk-export` doit être déclaré **avant** la route `GET/DELETE /api/documents/{id}` dans le contrôleur pour éviter que Symfony ne matche `bulk-export` comme un `{id}`.

8. **SSE** : l'endpoint `/api/sse` garde la connexion ouverte. Ne pas appeler cet endpoint depuis des outils qui bufferisent la réponse.

---

*Dernière mise à jour : 23 avril 2026*
