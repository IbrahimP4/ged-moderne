import type { DocumentDTO, DocumentVersionDTO, Folder, FolderContentsDTO } from '@/types'

interface BackendDocumentVersionDTO {
  id: string
  versionNumber: number
  mimeType: string
  fileSizeBytes: number
  originalFilename: string
  comment: string | null
  createdAt: string
}

interface BackendDocumentDTO {
  id: string
  title: string
  status: DocumentDTO['status']
  folderId: string
  folderName?: string
  latestVersion: BackendDocumentVersionDTO | null
  versions?: BackendDocumentVersionDTO[]
  comment: string | null
  createdAt: string
  updatedAt: string
  tags?: string[]
  isFavorite?: boolean
}

interface BackendFolderDTO {
  id: string
  name: string
  comment: string | null
  parentId: string | null
  createdAt: string
  restricted?: boolean
}

interface BackendFolderContentsDTO {
  folder: BackendFolderDTO
  subFolders: BackendFolderDTO[]
  documents: BackendDocumentDTO[]
  totalDocuments: number
  currentPage: number
  pageSize: number
}

function normalizeDocumentVersion(version: BackendDocumentVersionDTO): DocumentVersionDTO {
  return {
    id: version.id,
    versionNumber: version.versionNumber,
    originalFilename: version.originalFilename,
    mimeType: version.mimeType,
    fileSizeBytes: version.fileSizeBytes,
    comment: version.comment,
    uploadedAt: version.createdAt,
  }
}

export function normalizeDocument(document: BackendDocumentDTO): DocumentDTO {
  const latestVersion = document.latestVersion ? normalizeDocumentVersion(document.latestVersion) : null

  return {
    id: document.id,
    title: document.title,
    status: document.status,
    mimeType: latestVersion?.mimeType ?? 'application/octet-stream',
    fileSizeBytes: latestVersion?.fileSizeBytes ?? 0,
    originalFilename: latestVersion?.originalFilename ?? document.title,
    comment: document.comment,
    folderId: document.folderId,
    folderName: document.folderName,
    ownerId: '',
    createdAt: document.createdAt,
    updatedAt: document.updatedAt,
    latestVersion,
    versions: (document.versions ?? []).map(normalizeDocumentVersion),
    tags: document.tags ?? [],
    isFavorite: document.isFavorite ?? false,
  }
}

function normalizeFolder(folder: BackendFolderDTO): Folder {
  return {
    id: folder.id,
    name: folder.name,
    comment: folder.comment,
    parentId: folder.parentId,
    createdAt: folder.createdAt,
    updatedAt: folder.createdAt,
    restricted: folder.restricted ?? false,
  }
}

export function normalizeFolderContents(data: BackendFolderContentsDTO): FolderContentsDTO {
  return {
    folder: normalizeFolder(data.folder),
    subfolders: data.subFolders.map(normalizeFolder),
    documents: data.documents.map(normalizeDocument),
    totalDocuments: data.totalDocuments ?? 0,
    currentPage: data.currentPage ?? 1,
    pageSize: data.pageSize ?? 25,
  }
}
