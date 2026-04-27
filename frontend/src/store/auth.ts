import { create } from 'zustand'
import { persist } from 'zustand/middleware'

interface AuthState {
  token: string | null
  isAdmin: boolean
  username: string | null
  userId: string | null
  login: (token: string, isAdmin: boolean, username: string, userId: string) => void
  logout: () => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      isAdmin: false,
      username: null,
      userId: null,
      login: (token, isAdmin, username, userId) => set({ token, isAdmin, username, userId }),
      logout: () => set({ token: null, isAdmin: false, username: null, userId: null }),
    }),
    { name: 'ged-auth' },
  ),
)
