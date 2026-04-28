import { useState } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import {
  FolderOpen, Users, ScrollText, LogOut,
  Search, LayoutDashboard, FileSignature, UserCircle, MessageCircle, X,
  Trash2, Star, Moon, Sun,
} from 'lucide-react'
import { useAuthStore } from '@/store/auth'
import { useRealtimeStore } from '@/store/realtimeStore'
import { useThemeStore } from '@/store/theme'
import { cn } from '@/lib/utils'
import { NotificationBell } from '@/components/ui/NotificationBell'

interface SidebarProps {
  isOpen?: boolean
  onClose?: () => void
}

function NavItem({
  to,
  icon,
  label,
  badge,
  onClick,
}: {
  to: string
  icon: React.ReactNode
  label: string
  badge?: number
  onClick?: () => void
}) {
  return (
    <NavLink
      to={to}
      onClick={onClick}
      className={({ isActive }) =>
        cn(
          'flex items-center gap-3 pl-4 pr-3 py-2.5 text-sm font-medium transition-all duration-150 relative',
          isActive ? 'sidebar-active' : 'sidebar-inactive',
        )
      }
    >
      <span className="shrink-0">{icon}</span>
      <span className="flex-1">{label}</span>
      {badge != null && badge > 0 && (
        <span className="bg-[#F5A800] text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center">
          {badge > 99 ? '99+' : badge}
        </span>
      )}
    </NavLink>
  )
}

export function Sidebar({ isOpen = false, onClose }: SidebarProps) {
  const { isAdmin, username, logout } = useAuthStore()
  const navigate = useNavigate()
  const [q, setQ] = useState('')
  const { dark, toggle: toggleDark } = useThemeStore()
  const unreadMessages = useRealtimeStore((s) => s.unreadMessages)

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    const trimmed = q.trim()
    if (trimmed.length >= 2) {
      navigate(`/search?q=${encodeURIComponent(trimmed)}`)
      onClose?.()
    }
  }

  const close = () => onClose?.()

  return (
    <aside
      className={cn(
        'w-64 shrink-0 flex flex-col h-screen bg-[#141414] text-gray-100 select-none',
        'md:relative md:translate-x-0 md:z-auto',
        'fixed top-0 left-0 z-40 transition-transform duration-300 md:transition-none',
        isOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
      )}
    >
      {/* ── En-tête logo ────────────────────────────────────────────────── */}
      <div className="px-4 py-4 border-b border-white/10 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <img
            src="/logoEnof.jpeg"
            alt="ENOF"
            className="w-9 h-9 rounded-md object-cover shrink-0"
          />
          <div className="leading-tight">
            <p className="font-bold text-white text-sm tracking-widest uppercase">ENOF</p>
            <p className="text-[10px] text-[#F5A800] tracking-wider font-medium uppercase">
              Groupe Sonarem
            </p>
          </div>
        </div>
        <button
          onClick={onClose}
          className="md:hidden p-1.5 rounded-md text-gray-500 hover:text-white hover:bg-card/10 transition-colors"
          aria-label="Fermer"
        >
          <X size={16} />
        </button>
      </div>

      {/* ── Barre de recherche ───────────────────────────────────────────── */}
      <div className="px-3 py-3 border-b border-white/10">
        <form onSubmit={handleSearch}>
          <div className="relative">
            <Search size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
            <input
              type="text"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Rechercher…"
              className="w-full bg-[#1E1E1E] text-gray-200 text-sm pl-8 pr-3 py-2 rounded-md border border-white/10 placeholder-gray-600
                         focus:outline-none focus:ring-1 focus:ring-[#F5A800] focus:border-[#F5A800] transition-all"
            />
          </div>
        </form>
      </div>

      {/* ── Navigation ──────────────────────────────────────────────────── */}
      <nav className="flex-1 py-3 overflow-y-auto space-y-0.5">

        <div className="px-4 pb-2 pt-1">
          <p className="text-[10px] font-bold text-gray-600 uppercase tracking-widest">Principal</p>
        </div>

        <NavItem to="/dashboard" icon={<LayoutDashboard size={17} />} label="Tableau de bord" onClick={close} />
        <NavItem to="/folders"   icon={<FolderOpen size={17} />}      label="Documents"       onClick={close} />
        <NavItem to="/favorites" icon={<Star size={17} />}            label="Favoris"          onClick={close} />
        <NavItem to="/trash"     icon={<Trash2 size={17} />}          label="Corbeille"        onClick={close} />

        <div className="px-4 pb-2 pt-4">
          <p className="text-[10px] font-bold text-gray-600 uppercase tracking-widest">Personnel</p>
        </div>

        <NavItem
          to="/messages"
          icon={<MessageCircle size={17} />}
          label="Messages"
          badge={unreadMessages}
          onClick={close}
        />
        <NavItem to="/my-signatures" icon={<FileSignature size={17} />} label="Mes signatures" onClick={close} />
        <NavItem to="/profile"       icon={<UserCircle size={17} />}    label="Mon profil"      onClick={close} />

        {isAdmin && (
          <>
            <div className="px-4 pb-2 pt-4">
              <p className="text-[10px] font-bold text-gray-600 uppercase tracking-widest">Administration</p>
            </div>
            <NavItem to="/admin/signatures" icon={<FileSignature size={17} />} label="Signatures"     onClick={close} />
            <NavItem to="/admin/users"      icon={<Users size={17} />}         label="Utilisateurs"   onClick={close} />
            <NavItem to="/admin/audit"      icon={<ScrollText size={17} />}    label="Journal d'audit" onClick={close} />
          </>
        )}
      </nav>

      {/* ── Footer utilisateur ───────────────────────────────────────────── */}
      <div className="border-t border-white/10 px-3 py-3">
        {/* Sonarem branding subtile */}
        <div className="flex items-center gap-2 px-2 pb-3 mb-2 border-b border-white/10">
          <img src="/logoSonarem.png" alt="Sonarem" className="w-5 h-5 rounded-full object-cover" />
          <span className="text-[10px] text-gray-600 font-medium tracking-wide">Groupe Sonarem — GED</span>
        </div>

        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2 min-w-0">
            <div className="w-7 h-7 rounded-md bg-[#F5A800] flex items-center justify-center text-xs font-bold text-primary shrink-0">
              {username?.[0]?.toUpperCase() ?? '?'}
            </div>
            <span className="text-sm text-gray-300 truncate">{username}</span>
          </div>
          <div className="flex items-center gap-1">
            <button
              onClick={toggleDark}
              className="p-1.5 rounded-md text-gray-500 hover:text-white hover:bg-card/10 transition-colors"
              title={dark ? 'Mode clair' : 'Mode sombre'}
            >
              {dark ? <Sun size={15} /> : <Moon size={15} />}
            </button>
            <span className="hidden md:flex">
              <NotificationBell />
            </span>
            <button
              onClick={logout}
              className="p-1.5 rounded-md text-gray-500 hover:text-red-400 hover:bg-red-900/20 transition-colors"
              title="Déconnexion"
            >
              <LogOut size={15} />
            </button>
          </div>
        </div>
      </div>
    </aside>
  )
}
