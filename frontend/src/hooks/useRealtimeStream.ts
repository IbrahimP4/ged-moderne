import { useRef } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useAuthStore } from '@/store/auth'
import { useRealtimeStore } from '@/store/realtimeStore'
import { useNotificationSound } from '@/hooks/useNotificationSound'
import { getNotifications, getUnreadMessageCount } from '@/api/realtimeApi'

const POLL_INTERVAL = 4_000 // 4 secondes — quasi temps réel, compatible php -S

/**
 * Hook racine monté une seule fois dans AppLayout.
 *
 * Stratégie : smart polling toutes les 4s (compatible avec le serveur PHP
 * built-in mono-thread). Compare l'état précédent pour détecter les nouveaux
 * items et jouer un son uniquement sur les vraies nouveautés.
 */
export function useRealtimeStream() {
  const token       = useAuthStore((s) => s.token)
  const { playSound } = useNotificationSound()

  const {
    setNotifications,
    setUnreadMessages,
  } = useRealtimeStore()

  // Garde les compteurs précédents pour détecter les nouveautés
  const prevNotifCount = useRef<number>(0)
  const prevMsgCount   = useRef<number>(0)

  // ── Polling notifications ─────────────────────────────────────────────────
  useQuery({
    queryKey: ['realtime', 'notifications'],
    queryFn: async () => {
      const data = await getNotifications()
      setNotifications(data.notifications, data.unreadCount)

      // Son si nouvelles notifications depuis le dernier poll
      if (data.unreadCount > prevNotifCount.current) {
        playSound('notification')
      }
      prevNotifCount.current = data.unreadCount
      return data
    },
    enabled:        !!token,
    refetchInterval: POLL_INTERVAL,
    staleTime:       0,
    refetchOnWindowFocus: true,
  })

  // ── Polling messages non-lus ──────────────────────────────────────────────
  useQuery({
    queryKey: ['realtime', 'unread-messages'],
    queryFn: async () => {
      const data = await getUnreadMessageCount()
      setUnreadMessages(data.count)

      // Son si nouveaux messages
      if (data.count > prevMsgCount.current) {
        playSound('message')
      }
      prevMsgCount.current = data.count
      return data
    },
    enabled:        !!token,
    refetchInterval: POLL_INTERVAL,
    staleTime:       0,
    refetchOnWindowFocus: true,
  })
}
