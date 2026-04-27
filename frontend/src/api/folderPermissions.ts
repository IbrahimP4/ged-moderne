import { api } from '@/lib/axios'
import type { FolderPermissionsDTO, PermissionLevel } from '@/types'

export async function getFolderPermissions(folderId: string): Promise<FolderPermissionsDTO> {
  const { data } = await api.get(`/admin/folders/${folderId}/permissions`)
  return data
}

export async function setFolderPermission(
  folderId: string,
  userId: string,
  level: PermissionLevel,
): Promise<void> {
  await api.post(`/admin/folders/${folderId}/permissions`, { user_id: userId, level })
}

export async function removeFolderPermission(folderId: string, userId: string): Promise<void> {
  await api.delete(`/admin/folders/${folderId}/permissions/${userId}`)
}

export async function setFolderRestricted(folderId: string, restricted: boolean): Promise<void> {
  await api.patch(`/admin/folders/${folderId}/restrict`, { restricted })
}
