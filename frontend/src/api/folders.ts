import { api } from '@/lib/axios'
import type { Folder, FolderContentsDTO } from '@/types'
import { normalizeFolderContents } from '@/api/normalizers'

export async function getRootFolder(page = 1): Promise<FolderContentsDTO> {
  const { data } = await api.get('/folders', { params: { page, page_size: 25 } })
  return normalizeFolderContents(data)
}

export async function getFolder(id: string, page = 1): Promise<FolderContentsDTO> {
  const { data } = await api.get(`/folders/${id}`, { params: { page, page_size: 25 } })
  return normalizeFolderContents(data)
}

export async function createFolder(name: string, parentId?: string): Promise<Folder> {
  const { data } = await api.post<Folder>('/folders', { name, parent_id: parentId })
  return data
}

export async function renameFolder(id: string, name: string): Promise<void> {
  await api.patch(`/folders/${id}`, { name })
}

export async function deleteFolder(id: string): Promise<void> {
  await api.delete(`/folders/${id}`)
}
