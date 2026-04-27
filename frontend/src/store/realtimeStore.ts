import { create } from 'zustand'

// ── Types ─────────────────────────────────────────────────────────────────────

export interface AppNotification {
  id: string
  type: string
  title: string
  body: string
  link: string | null
  payload: Record<string, unknown> | null
  read: boolean
  createdAt: string
}

export interface ChatMessage {
  id: string
  senderId: string
  senderUsername: string
  recipientId: string
  content: string
  documentId: string | null
  documentTitle: string | null
  read: boolean
  sentAt: string
}

// ── State ─────────────────────────────────────────────────────────────────────

interface RealtimeState {
  notifications: AppNotification[]
  unreadNotifications: number
  unreadMessages: number

  // Initialise depuis l'API REST (load initial)
  setNotifications: (notifications: AppNotification[], unread: number) => void
  setUnreadMessages: (count: number) => void

  // Appelé par le flux SSE
  pushNotification: (n: AppNotification) => void
  pushMessage: (m: ChatMessage) => void

  // Actions utilisateur
  markAllNotificationsRead: () => void
  markNotificationRead: (id: string) => void
  decrementUnreadMessages: (by: number) => void
}

export const useRealtimeStore = create<RealtimeState>()((set) => ({
  notifications: [],
  unreadNotifications: 0,
  unreadMessages: 0,

  setNotifications: (notifications, unread) =>
    set({ notifications, unreadNotifications: unread }),

  setUnreadMessages: (count) =>
    set({ unreadMessages: count }),

  pushNotification: (n) =>
    set((state) => ({
      notifications: [n, ...state.notifications].slice(0, 50),
      unreadNotifications: n.read ? state.unreadNotifications : state.unreadNotifications + 1,
    })),

  pushMessage: (_m) =>
    set((state) => ({
      unreadMessages: state.unreadMessages + 1,
      notifications: state.notifications,
    })),

  markAllNotificationsRead: () =>
    set((state) => ({
      notifications: state.notifications.map((n) => ({ ...n, read: true })),
      unreadNotifications: 0,
    })),

  markNotificationRead: (id) =>
    set((state) => {
      const target = state.notifications.find((n) => n.id === id)
      if (!target || target.read) return state
      return {
        notifications: state.notifications.map((n) =>
          n.id === id ? { ...n, read: true } : n,
        ),
        unreadNotifications: Math.max(0, state.unreadNotifications - 1),
      }
    }),

  decrementUnreadMessages: (by) =>
    set((state) => ({
      unreadMessages: Math.max(0, state.unreadMessages - by),
    })),
}))
