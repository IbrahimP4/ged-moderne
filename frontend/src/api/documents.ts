import { api } from '@/lib/axios'
import type { DocumentDTO } from '@/types'
import { normalizeDocument } from '@/api/normalizers'

export async function getDocument(id: string): Promise<DocumentDTO> {
  const { data } = await api.get(`/documents/${id}?versions=true`)
  return normalizeDocument(data)
}

export async function uploadDocument(
  folderId: string,
  file: File,
  title: string,
  comment?: string,
): Promise<{ id: string }> {
  const form = new FormData()
  form.append('file', file)
  form.append('title', title)
  form.append('folder_id', folderId)
  if (comment) form.append('comment', comment)

  const { data } = await api.post<{ id: string }>('/documents', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function deleteDocument(id: string): Promise<void> {
  await api.delete(`/documents/${id}`)
}

export async function submitForReview(id: string): Promise<void> {
  await api.patch(`/documents/${id}/submit`)
}

export async function approveDocument(id: string): Promise<void> {
  await api.patch(`/documents/${id}/approve`)
}

export async function rejectDocument(id: string, reason: string): Promise<void> {
  await api.patch(`/documents/${id}/reject`, { reason })
}

export async function addVersion(
  id: string,
  file: File,
  comment?: string,
): Promise<void> {
  const form = new FormData()
  form.append('file', file)
  if (comment) form.append('comment', comment)

  await api.post(`/documents/${id}/versions`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export async function updateDocumentTags(id: string, tags: string[]): Promise<void> {
  await api.patch(`/documents/${id}/tags`, { tags })
}

export async function renameDocument(id: string, title: string): Promise<void> {
  await api.patch(`/documents/${id}/rename`, { title })
}

export async function moveDocument(id: string, folderId: string): Promise<void> {
  await api.patch(`/documents/${id}/move`, { folder_id: folderId })
}

export function downloadUrl(id: string, version?: number): string {
  const base = `/api/documents/${id}/download`
  return version ? `${base}?version=${version}` : base
}

export interface TrashDocumentDTO {
  id: string
  title: string
  mimeType: string
  status: string
  deletedAt: string
}

export async function getTrash(): Promise<TrashDocumentDTO[]> {
  const { data } = await api.get<TrashDocumentDTO[]>('/documents/trash')
  return data
}

export async function restoreDocument(id: string): Promise<void> {
  await api.patch(`/documents/${id}/restore`)
}

export async function permanentDeleteDocument(id: string): Promise<void> {
  await api.delete(`/documents/${id}/permanent`)
}

export interface FavoriteDocumentDTO {
  id: string
  title: string
  mimeType: string
  status: string
  folderId: string | null
}

export async function getFavorites(): Promise<FavoriteDocumentDTO[]> {
  const { data } = await api.get<FavoriteDocumentDTO[]>('/documents/favorites')
  return data
}

export async function addFavorite(id: string): Promise<void> {
  await api.post(`/documents/${id}/favorite`)
}

export async function removeFavorite(id: string): Promise<void> {
  await api.delete(`/documents/${id}/favorite`)
}

export interface CommentDTO {
  id: string
  authorId: string
  username: string
  content: string
  createdAt: string
}

export async function getComments(documentId: string): Promise<CommentDTO[]> {
  const { data } = await api.get<CommentDTO[]>(`/documents/${documentId}/comments`)
  return data
}

export async function addComment(documentId: string, content: string): Promise<CommentDTO> {
  const { data } = await api.post<CommentDTO>(`/documents/${documentId}/comments`, { content })
  return data
}

export async function deleteComment(documentId: string, commentId: string): Promise<void> {
  await api.delete(`/documents/${documentId}/comments/${commentId}`)
}

export async function bulkDeleteDocuments(ids: string[]): Promise<void> {
  await Promise.all(ids.map((id) => api.delete(`/documents/${id}`)))
}

export async function bulkMoveDocuments(ids: string[], folderId: string): Promise<void> {
  await Promise.all(ids.map((id) => api.patch(`/documents/${id}/move`, { folder_id: folderId })))
}

export async function bulkExportDocuments(ids: string[], filename = 'export.zip'): Promise<void> {
  const response = await api.post(
    '/documents/bulk-export',
    { ids },
    { responseType: 'blob' },
  )
  const url = URL.createObjectURL(new Blob([response.data as BlobPart], { type: 'application/zip' }))
  const a   = document.createElement('a')
  a.href     = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}
