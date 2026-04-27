import { api } from '@/lib/axios'

export interface SearchResult {
  id: string
  title: string
  status: string
  statusLabel: string
  folderId: string
  folderName: string
  ownerUsername: string
  versionCount: number
  mimeType: string | null
  fileSizeBytes: number
  createdAt: string
  updatedAt: string
  tags: string[]
  snippet: string | null
  matchedInContent: boolean
}

export interface SearchResponse {
  results: SearchResult[]
  total: number
  query: string
}

export async function searchDocuments(
  q: string,
  folderId?: string,
  limit = 100,
  status?: string,
  tags?: string[],
): Promise<SearchResponse> {
  const params: Record<string, string | number | string[]> = { q, limit }
  if (folderId) params.folder_id = folderId
  if (status)   params.status    = status
  if (tags?.length) params['tags[]'] = tags

  const { data } = await api.get<SearchResponse>('/search', { params })
  return data
}
