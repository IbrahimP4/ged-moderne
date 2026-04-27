import { api } from '@/lib/axios'

export interface DashboardStats {
  totalDocuments: number
  totalFolders: number
  totalUsers: number
  byStatus: {
    draft: number
    pending_review: number
    approved: number
    rejected: number
  }
}

export async function getDashboardStats(): Promise<DashboardStats> {
  const { data } = await api.get<DashboardStats>('/dashboard/stats')
  return data
}

export interface UploadsByDayDTO {
  date: string
  count: number
}

export async function getUploadsByDay(): Promise<UploadsByDayDTO[]> {
  const { data } = await api.get<UploadsByDayDTO[]>('/dashboard/uploads-by-day')
  return data
}
