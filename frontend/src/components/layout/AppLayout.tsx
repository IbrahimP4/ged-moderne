import { useState } from 'react'
import { Outlet, Navigate } from 'react-router-dom'
import { Menu } from 'lucide-react'
import { Sidebar } from './Sidebar'
import { useAuthStore } from '@/store/auth'
import { useJwtExpiry } from '@/hooks/useJwtExpiry'
import { useRealtimeStream } from '@/hooks/useRealtimeStream'
import { NotificationBell } from '@/components/ui/NotificationBell'

export function AppLayout() {
  const token = useAuthStore((s) => s.token)
  const [sidebarOpen, setSidebarOpen] = useState(false)

  useJwtExpiry()
  useRealtimeStream()

  if (!token) return <Navigate to="/login" replace />

  return (
    <div className="flex h-screen overflow-hidden bg-page">
      <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />

      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black/60 z-30 md:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
        {/* Mobile top bar */}
        <header className="md:hidden shrink-0 flex items-center justify-between px-4 py-3 bg-[#141414] border-b border-white/10">
          <button
            onClick={() => setSidebarOpen(true)}
            className="p-1.5 rounded-md text-gray-400 hover:text-white hover:bg-card/10 transition-colors"
            aria-label="Ouvrir le menu"
          >
            <Menu size={20} />
          </button>
          <div className="flex items-center gap-2">
            <img src="/logoEnof.jpeg" alt="ENOF" className="w-7 h-7 rounded-md object-cover" />
            <div className="leading-tight">
              <p className="font-bold text-white text-xs tracking-widest uppercase">ENOF</p>
              <p className="text-[9px] text-[#F5A800] tracking-wider uppercase">Groupe Sonarem</p>
            </div>
          </div>
          <NotificationBell mobile />
        </header>

        <main className="flex-1 overflow-y-auto h-full">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
