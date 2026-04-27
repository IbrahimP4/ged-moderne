import { api } from '@/lib/axios'
import type { User } from '@/types'

export interface CreateUserPayload {
  username: string
  email: string
  password: string
  isAdmin: boolean
}

export async function listUsers(): Promise<User[]> {
  const { data } = await api.get<User[]>('/admin/users')
  return data
}

export async function createUser(payload: CreateUserPayload): Promise<{ id: string }> {
  const { data } = await api.post<{ id: string }>('/admin/users', {
    username: payload.username,
    email: payload.email,
    password: payload.password,
    is_admin: payload.isAdmin,
  })

  return data
}

export async function changeUserRole(userId: string, makeAdmin: boolean): Promise<void> {
  await api.patch(`/admin/users/${userId}/role`, { make_admin: makeAdmin })
}
