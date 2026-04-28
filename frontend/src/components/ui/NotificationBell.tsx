import { useState, useRef, useEffect } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  Bell, FileSignature, CheckCircle2, XCircle,
  ChevronRight, FileText, MessageCircle, Check,
} from 'lucide-react'
import { Link, useNavigate } from 'react-router-dom'
import { markAllNotificationsRead, markNotificationRead } from '@/api/realtimeApi'
import { useRealtimeStore } from '@/store/realtimeStore'
import { useAuthStore } from '@/store/auth'
import type { AppNotification } from '@/store/realtimeStore'

// ── Icône par type de notification ────────────────────────────────────────────
function NotifIcon({ type }: { type: string }) {
  if (type === 'document_approved')
    return <CheckCircle2 size={14} className="text-green-500 shrink-0" />
  if (type === 'document_rejected')
    return <XCircle size={14} className="text-red-500 shrink-0" />
  if (type === 'document_pending_review')
    return <FileText size={14} className="text-amber-500 shrink-0" />
  if (type === 'signature_requested')
    return <FileSignature size={14} className="text-[#F5A800] shrink-0" />
  if (type === 'message_received')
    return <MessageCircle size={14} className="text-sky-500 shrink-0" />
  return <Bell size={14} className="text-muted shrink-0" />
}

function notifBg(type: string): string {
  const variants: Record<string, string> = {
    document_approved:       'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800/40',
    document_rejected:       'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800/40',
    document_pending_review: 'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800/40',
    signature_requested:     'bg-amber-50/80 border-amber-200 dark:bg-amber-900/15 dark:border-amber-800/30',
    message_received:        'bg-sky-50 border-sky-200 dark:bg-sky-900/20 dark:border-sky-800/40',
  }
  return variants[type] ?? 'bg-muted border-strong dark:bg-[#242424] dark:border-[#333]'
}

function timeAgo(iso: string): string {
  const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (diff < 60)    return 'à l\'instant'
  if (diff < 3600)  return `${Math.floor(diff / 60)} min`
  if (diff < 86400) return `${Math.floor(diff / 3600)} h`
  return `${Math.floor(diff / 86400)} j`
}

// ── Carte notification ─────────────────────────────────────────────────────────
function NotifCard({
  notif,
  onClose,
}: {
  notif: AppNotification
  onClose: () => void
}) {
  const navigate  = useNavigate()
  const markLocal = useRealtimeStore((s) => s.markNotificationRead)

  const handleClick = async () => {
    if (!notif.read) {
      markLocal(notif.id)
      await markNotificationRead(notif.id).catch(() => {})
    }
    onClose()
    if (notif.link) navigate(notif.link)
  }

  return (
    <button
      onClick={handleClick}
      className={`w-full text-left rounded-lg border p-3 mb-1.5 transition-all hover:shadow-sm cursor-pointer ${notifBg(notif.type)} ${!notif.read ? 'ring-1 ring-[#F5A800]/40' : 'opacity-70'}`}
    >
      <div className="flex items-start gap-2.5">
        <div className="mt-0.5">
          <NotifIcon type={notif.type} />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-1">
            <p className="text-xs font-semibold text-primary dark:text-[#EFEFEF] leading-snug">
              {notif.title}
            </p>
            <span className="text-[10px] text-muted dark:text-[#707070] shrink-0 mt-0.5">
              {timeAgo(notif.createdAt)}
            </span>
          </div>
          <p className="text-xs text-secondary dark:text-[#A8A8A8] mt-0.5 leading-snug line-clamp-2">
            {notif.body}
          </p>
        </div>
        {!notif.read && (
          <div className="w-2 h-2 rounded-full bg-[#F5A800] shrink-0 mt-1.5" />
        )}
      </div>
    </button>
  )
}

// ── Composant principal ───────────────────────────────────────────────────────
export function NotificationBell({ mobile = false }: { mobile?: boolean }) {
  const [open, setOpen] = useState(false)
  const ref             = useRef<HTMLDivElement>(null)
  const qc              = useQueryClient()
  const isAdmin         = useAuthStore((s) => s.isAdmin)

  const { notifications, unreadNotifications, markAllNotificationsRead: markAllLocal } =
    useRealtimeStore()

  // Ferme si clic en dehors
  useEffect(() => {
    function handle(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', handle)
    return () => document.removeEventListener('mousedown', handle)
  }, [])

  const readAllMut = useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: () => {
      markAllLocal()
      qc.invalidateQueries({ queryKey: ['realtime', 'init'] })
    },
  })

  return (
    <div ref={ref} className="relative">
      {/* Bouton cloche */}
      <button
        onClick={() => setOpen((v) => !v)}
        className="relative p-1.5 rounded-lg text-muted hover:text-[#F5A800] transition-colors"
        title="Notifications"
      >
        <Bell size={18} />
        {unreadNotifications > 0 && (
          <span className="absolute -top-1 -right-1 w-4 h-4 bg-[#F5A800] text-primary text-[10px] font-bold rounded-full flex items-center justify-center">
            {unreadNotifications > 9 ? '9+' : unreadNotifications}
          </span>
        )}
      </button>

      {/* Panneau déroulant */}
      {open && (
        <div className={`fixed w-[360px] max-w-[calc(100vw-16px)] bg-card dark:bg-[#1A1A1A] rounded-lg shadow-2xl border border-base dark:border-[#2E2E2E] z-50 overflow-hidden ${
          mobile
            ? 'top-14 right-2'
            : 'bottom-16 left-[256px]'
        }`}>
          {/* Header */}
          <div className="px-4 py-3 border-b border-muted dark:border-[#2E2E2E] flex items-center justify-between bg-subtle dark:bg-[#1E1E1E] border-l-4 border-l-[#F5A800]">
            <div className="flex items-center gap-2">
              <Bell size={15} className="text-[#F5A800]" />
              <span className="text-sm font-bold text-primary dark:text-[#EFEFEF]">Notifications</span>
              {unreadNotifications > 0 && (
                <span className="bg-[#F5A800] text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                  {unreadNotifications}
                </span>
              )}
            </div>
            <div className="flex items-center gap-3">
              {unreadNotifications > 0 && (
                <button
                  onClick={() => readAllMut.mutate()}
                  className="flex items-center gap-1 text-[11px] text-secondary dark:text-[#A8A8A8] hover:text-[#F5A800] transition-colors"
                >
                  <Check size={11} /> Tout lire
                </button>
              )}
              <Link
                to={isAdmin ? '/admin/signatures' : '/my-signatures'}
                onClick={() => setOpen(false)}
                className="flex items-center gap-1 text-[11px] text-[#F5A800] hover:text-[#D4920A] font-medium transition-colors"
              >
                Signatures <ChevronRight size={11} />
              </Link>
            </div>
          </div>

          {/* Corps */}
          <div className="p-3 max-h-[420px] overflow-y-auto">
            {notifications.length === 0 ? (
              <div className="text-center py-10">
                <Bell size={32} className="text-[#E0E0E0] dark:text-primary mx-auto mb-3" />
                <p className="text-sm font-medium text-muted dark:text-[#707070]">Aucune notification</p>
                <p className="text-xs text-faint dark:text-[#484848] mt-1">Tout est à jour !</p>
              </div>
            ) : (
              notifications.map((n) => (
                <NotifCard key={n.id} notif={n} onClose={() => setOpen(false)} />
              ))
            )}
          </div>
        </div>
      )}
    </div>
  )
}
