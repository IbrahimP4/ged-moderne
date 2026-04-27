import { api } from '@/lib/axios'
import type { AppNotification } from '@/store/realtimeStore'

// ── Notifications ─────────────────────────────────────────────────────────────

export interface NotificationsResponse {
  notifications: AppNotification[]
  unreadCount: number
}

export async function getNotifications(): Promise<NotificationsResponse> {
  const { data } = await api.get<NotificationsResponse>('/notifications')
  return data
}

export async function markAllNotificationsRead(): Promise<void> {
  await api.post('/notifications/read-all')
}

export async function markNotificationRead(id: string): Promise<void> {
  await api.patch(`/notifications/${id}/read`)
}

// ── Messages ──────────────────────────────────────────────────────────────────

export async function getUnreadMessageCount(): Promise<{ count: number }> {
  const { data } = await api.get<{ count: number }>('/messages/unread-count')
  return data
}

export interface ConversationSummary {
  partner: { id: string; username: string }
  lastMessage: {
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
  unreadCount: number
}

export async function getConversations(): Promise<ConversationSummary[]> {
  const { data } = await api.get<ConversationSummary[]>('/messages/conversations')
  return data
}

export interface MessageDTO {
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

export async function getConversation(partnerId: string): Promise<MessageDTO[]> {
  const { data } = await api.get<MessageDTO[]>(`/messages/conversation/${partnerId}`)
  return data
}

export async function sendMessage(payload: {
  recipient_id: string
  content: string
  document_id?: string
  document_title?: string
}): Promise<MessageDTO> {
  const { data } = await api.post<MessageDTO>('/messages', payload)
  return data
}

export interface GedUser {
  id: string
  username: string
  isAdmin: boolean
}

export async function getUsers(): Promise<GedUser[]> {
  const { data } = await api.get<GedUser[]>('/users')
  return data
}
