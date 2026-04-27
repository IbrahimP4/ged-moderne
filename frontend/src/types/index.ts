// ── Auth ──────────────────────────────────────────────────────────────────────

export interface LoginResponse {
  token: string
}

// ── User ─────────────────────────────────────────────────────────────────────

export interface User {
  id: string
  username: string
  email: string
  isAdmin: boolean
}

// ── Folder ───────────────────────────────────────────────────────────────────

export type PermissionLevel = 'read' | 'write'

export interface FolderPermission {
  userId: string
  username: string
  level: PermissionLevel
  grantedAt: string
}

export interface FolderPermissionsDTO {
  folderId: string
  folderName: string
  restricted: boolean
  permissions: FolderPermission[]
}

export interface Folder {
  id: string
  name: string
  comment: string | null
  parentId: string | null
  createdAt: string
  updatedAt: string
  restricted: boolean
}

export interface FolderContentsDTO {
  folder: Folder
  subfolders: Folder[]
  documents: DocumentDTO[]
  totalDocuments: number
  currentPage: number
  pageSize: number
}

// ── Document ─────────────────────────────────────────────────────────────────

export type DocumentStatus =
  | 'draft'
  | 'pending_review'
  | 'approved'
  | 'rejected'
  | 'archived'

export interface DocumentVersionDTO {
  id: string
  versionNumber: number
  originalFilename: string
  mimeType: string
  fileSizeBytes: number
  comment: string | null
  uploadedAt: string
}

export interface DocumentDTO {
  id: string
  title: string
  status: DocumentStatus
  mimeType: string
  fileSizeBytes: number
  originalFilename: string
  comment: string | null
  folderId: string
  folderName?: string
  ownerId: string
  createdAt: string
  updatedAt: string
  latestVersion: DocumentVersionDTO | null
  versions?: DocumentVersionDTO[]
  tags: string[]
  isFavorite?: boolean
}

// ── Search ────────────────────────────────────────────────────────────────────

export interface SearchResponse {
  results: DocumentDTO[]
  total: number
  query: string
}

// ── Signature ─────────────────────────────────────────────────────────────────

export type SignatureRequestStatus = 'pending' | 'signed' | 'declined'

export interface SignatureRequestDTO {
  id: string
  status: SignatureRequestStatus
  statusLabel: string
  message: string | null
  comment: string | null
  requestedAt: string
  resolvedAt: string | null
  documentId: string
  documentTitle: string
  requesterId: string
  requesterUsername: string
  signerId: string
  signerUsername: string
}

export interface AvailableSigner {
  id: string
  username: string
}

// ── Audit ─────────────────────────────────────────────────────────────────────

export interface AuditLogEntry {
  id: string
  eventName: string
  aggregateType: string
  aggregateId: string
  actorId: string | null
  actorUsername: string | null
  payload: Record<string, unknown>
  occurredAt: string
}

// ── API Errors ────────────────────────────────────────────────────────────────

export interface ApiError {
  error: string
}
