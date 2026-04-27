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
  return <Bell size={14} className="text-[#888] shrink-0" />
}

function notifBg(type: string): string {
  if (type === 'document_approved')       return 'bg-green-50 border-green-100'
  if (type === 'document_rejected')       return 'bg-red-50 border-red-100'
  if (type === 'document_pending_review') return 'bg-amber-50 border-amber-100'
  if (type === 'signature_requested')     return 'bg-[#FFF8E7] border-[#F5E4A0]'
  if (type === 'message_received')        return 'bg-sky-50 border-sky-100'
  return 'bg-[#F5F5F5] border-[#E0E0E0]'
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
            <p className="text-xs font-semibold text-[#1A1A1A] leading-snug">
              {notif.title}
            </p>
            <span className="text-[10px] text-[#888] shrink-0 mt-0.5">
              {timeAgo(notif.createdAt)}
            </span>
          </div>
          <p className="text-xs text-[#555] mt-0.5 leading-snug line-clamp-2">
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
        className="relative p-1.5 rounded-lg text-[#888] hover:text-[#F5A800] transition-colors"
        title="Notifications"
      >
        <Bell size={18} />
        {unreadNotifications > 0 && (
          <span className="absolute -top-1 -right-1 w-4 h-4 bg-[#F5A800] text-[#1A1A1A] text-[10px] font-bold rounded-full flex items-center justify-center">
            {unreadNotifications > 9 ? '9+' : unreadNotifications}
          </span>
        )}
      </button>

      {/* Panneau déroulant */}
      {open && (
        <div className={`fixed w-[360px] max-w-[calc(100vw-16px)] bg-white rounded-lg shadow-2xl border border-[#E8E8E8] z-50 overflow-hidden ${
          mobile
            ? 'top-14 right-2'
            : 'bottom-16 left-[256px]'
        }`}>
          {/* Header */}
          <div className="px-4 py-3 border-b border-[#F0F0F0] flex items-center justify-between bg-[#FAFAFA] border-l-4 border-l-[#F5A800]">
            <div className="flex items-center gap-2">
              <Bell size={15} className="text-[#F5A800]" />
              <span className="text-sm font-bold text-[#1A1A1A]">Notifications</span>
              {unreadNotifications > 0 && (
                <span className="bg-[#F5A800] text-[#1A1A1A] text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                  {unreadNotifications}
                </span>
              )}
            </div>
            <div className="flex items-center gap-3">
              {unreadNotifications > 0 && (
                <button
                  onClick={() => readAllMut.mutate()}
                  className="flex items-center gap-1 text-[11px] text-[#555] hover:text-[#F5A800] transition-colors"
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
                <Bell size={32} className="text-[#E0E0E0] mx-auto mb-3" />
                <p className="text-sm font-medium text-[#888]">Aucune notification</p>
                <p className="text-xs text-[#BBB] mt-1">Tout est à jour !</p>
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
