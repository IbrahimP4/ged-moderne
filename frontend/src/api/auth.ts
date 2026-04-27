import { api } from '@/lib/axios'
import type { LoginResponse } from '@/types'

export async function login(username: string, password: string): Promise<LoginResponse> {
  const { data } = await api.post<LoginResponse>('/login', { username, password })
  return data
}
