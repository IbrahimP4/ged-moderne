import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import {
  FileText, FolderOpen, Users, Clock, CheckCircle2,
  XCircle, TrendingUp, BarChart3, ChevronRight,
} from 'lucide-react'
import {
  AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip,
  PieChart, Pie, Cell, ResponsiveContainer, Legend,
} from 'recharts'
import { getDashboardStats, getUploadsByDay } from '@/api/dashboard'
import { PageSpinner } from '@/components/ui/Spinner'
import { useAuthStore } from '@/store/auth'
import { usePageTitle } from '@/hooks/usePageTitle'
import { STATUS_LABELS } from '@/lib/utils'

function SectionTitle({ icon, label }: { icon: React.ReactNode; label: string }) {
  return (
    <div className="flex items-center gap-2 mb-4">
      <div className="w-1 h-5 bg-[#F5A800] rounded-full" />
      <span className="text-[#888] shrink-0">{icon}</span>
      <h2 className="text-xs font-bold text-[#555] uppercase tracking-widest">{label}</h2>
    </div>
  )
}

function StatCard({ label, value, icon, accent, to }: {
  label: string; value: number; icon: React.ReactNode; accent: string; to?: string
}) {
  const inner = (
    <div className={`bg-white border border-[#E8E8E8] rounded-lg p-5 flex items-center gap-4 hover:shadow-md transition-all group ${to ? 'cursor-pointer' : ''}`}>
      <div className={`w-12 h-12 rounded-md flex items-center justify-center shrink-0 ${accent}`}>
        {icon}
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-2xl font-black text-[#1A1A1A] tracking-tight">{value.toLocaleString()}</p>
        <p className="text-xs text-[#888] font-medium uppercase tracking-wide mt-0.5">{label}</p>
      </div>
      {to && <ChevronRight size={16} className="text-[#CCC] group-hover:text-[#F5A800] transition-colors" />}
    </div>
  )
  return to ? <Link to={to}>{inner}</Link> : inner
}

const STATUS_PIE = [
  { key: 'approved',       color: '#16A34A', label: 'Approuvé' },
  { key: 'pending_review', color: '#F5A800', label: 'En révision' },
  { key: 'rejected',       color: '#DC2626', label: 'Rejeté' },
  { key: 'draft',          color: '#D0D0D0', label: 'Brouillon' },
]

export function DashboardPage() {
  const isAdmin = useAuthStore((s) => s.isAdmin)
  const username = useAuthStore((s) => s.username)
  usePageTitle('Tableau de bord')

  const { data, isLoading } = useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: getDashboardStats,
  })

  const { data: uploadsByDay = [] } = useQuery({
    queryKey: ['dashboard', 'uploads-by-day'],
    queryFn: getUploadsByDay,
  })

  if (isLoading) return <PageSpinner />

  const stats = data ?? {
    totalDocuments: 0, totalFolders: 0, totalUsers: 0,
    byStatus: { draft: 0, pending_review: 0, approved: 0, rejected: 0 },
  }

  const pieData = STATUS_PIE
    .map((s) => ({ ...s, value: stats.byStatus[s.key as keyof typeof stats.byStatus] ?? 0 }))
    .filter((d) => d.value > 0)

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 py-6 space-y-6">

      {/* ── Bandeau bienvenue ───────────────────────────────────────────── */}
      <div className="bg-[#141414] rounded-lg px-6 py-5 flex items-center justify-between overflow-hidden relative">
        <div className="absolute right-0 top-0 bottom-0 w-32 opacity-10"
          style={{
            backgroundImage: `repeating-linear-gradient(45deg, #F5A800 0px, #F5A800 1px, transparent 1px, transparent 12px)`,
          }}
        />
        <div>
          <p className="text-[#F5A800] text-xs font-bold uppercase tracking-widest mb-1">
            Groupe Sonarem — ENOF
          </p>
          <h1 className="text-xl font-black text-white tracking-tight">
            Bonjour, {username}
          </h1>
          <p className="text-gray-500 text-sm mt-0.5">Gestion Électronique de Documents</p>
        </div>
        <img src="/logoEnof.jpeg" alt="ENOF" className="w-14 h-14 rounded-lg object-cover opacity-80 hidden sm:block" />
      </div>

      {/* ── KPIs ────────────────────────────────────────────────────────── */}
      <div>
        <SectionTitle icon={<BarChart3 size={14} />} label="Vue d'ensemble" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <StatCard
            label="Documents"
            value={stats.totalDocuments}
            icon={<FileText size={22} className="text-[#F5A800]" />}
            accent="bg-[#FFF3CC]"
            to="/folders"
          />
          <StatCard
            label="Dossiers"
            value={stats.totalFolders}
            icon={<FolderOpen size={22} className="text-[#1A1A1A]" />}
            accent="bg-[#E8E8E8]"
            to="/folders"
          />
          {isAdmin && (
            <StatCard
              label="Utilisateurs"
              value={stats.totalUsers}
              icon={<Users size={22} className="text-white" />}
              accent="bg-[#1A1A1A]"
              to="/admin/users"
            />
          )}
        </div>
      </div>

      {/* ── Statuts ─────────────────────────────────────────────────────── */}
      <div>
        <SectionTitle icon={<CheckCircle2 size={14} />} label="Répartition des statuts" />
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { key: 'approved',       bg: 'bg-green-50',  border: 'border-green-100', text: 'text-green-700',  dot: 'bg-green-500', icon: <CheckCircle2 size={16} className="text-green-600" /> },
            { key: 'pending_review', bg: 'bg-[#FFF8E7]', border: 'border-[#F5D580]', text: 'text-[#A07000]',  dot: 'bg-[#F5A800]', icon: <Clock size={16} className="text-[#F5A800]" /> },
            { key: 'rejected',       bg: 'bg-red-50',    border: 'border-red-100',   text: 'text-red-700',    dot: 'bg-red-500',   icon: <XCircle size={16} className="text-red-600" /> },
            { key: 'draft',          bg: 'bg-[#F5F5F5]', border: 'border-[#E0E0E0]', text: 'text-[#555]',     dot: 'bg-[#CCC]',    icon: <FileText size={16} className="text-[#888]" /> },
          ].map(({ key, bg, border, text, icon }) => (
            <div key={key} className={`rounded-lg p-4 border ${bg} ${border}`}>
              <div className="flex items-center gap-2 mb-2">
                {icon}
                <span className={`text-xs font-bold uppercase tracking-wide ${text}`}>
                  {STATUS_LABELS[key as keyof typeof STATUS_LABELS]}
                </span>
              </div>
              <p className={`text-2xl font-black ${text}`}>
                {(stats.byStatus[key as keyof typeof stats.byStatus] ?? 0).toLocaleString()}
              </p>
            </div>
          ))}
        </div>

        {/* Barre de progression */}
        {stats.totalDocuments > 0 && (
          <div className="mt-3">
            <div className="flex h-2 rounded-full overflow-hidden bg-[#EBEBEB]">
              {pieData.map((d) => (
                <div
                  key={d.key}
                  style={{ width: `${(d.value / stats.totalDocuments) * 100}%`, background: d.color }}
                  className="transition-all"
                />
              ))}
            </div>
          </div>
        )}
      </div>

      {/* ── Graphiques ──────────────────────────────────────────────────── */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {/* Area chart */}
        <div className="lg:col-span-2 bg-white border border-[#E8E8E8] rounded-lg p-6">
          <SectionTitle icon={<TrendingUp size={14} />} label="Uploads — 30 derniers jours" />
          {uploadsByDay.length > 0 ? (
            <ResponsiveContainer width="100%" height={190}>
              <AreaChart data={uploadsByDay} margin={{ top: 4, right: 4, left: -20, bottom: 0 }}>
                <defs>
                  <linearGradient id="gradYellow" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%"  stopColor="#F5A800" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#F5A800" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#F0F0F0" />
                <XAxis dataKey="date" tick={{ fontSize: 10, fill: '#AAA' }} tickFormatter={(d) => d.slice(5)} />
                <YAxis tick={{ fontSize: 10, fill: '#AAA' }} allowDecimals={false} />
                <Tooltip
                  labelFormatter={(l) => `Date : ${l}`}
                  formatter={(v) => [`${v} upload(s)`, '']}
                  contentStyle={{ fontSize: 12, borderRadius: 6, border: '1px solid #E0E0E0' }}
                />
                <Area type="monotone" dataKey="count" stroke="#F5A800" strokeWidth={2} fill="url(#gradYellow)" dot={false} />
              </AreaChart>
            </ResponsiveContainer>
          ) : (
            <div className="flex items-center justify-center h-[190px] text-[#CCC] text-sm">
              Aucune donnée disponible
            </div>
          )}
        </div>

        {/* Pie chart */}
        <div className="bg-white border border-[#E8E8E8] rounded-lg p-6">
          <SectionTitle icon={<BarChart3 size={14} />} label="Statuts" />
          {stats.totalDocuments > 0 ? (
            <ResponsiveContainer width="100%" height={190}>
              <PieChart>
                <Pie
                  data={pieData}
                  cx="50%" cy="45%" innerRadius={48} outerRadius={72}
                  paddingAngle={2} dataKey="value"
                >
                  {pieData.map((entry) => (
                    <Cell key={entry.key} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip
                  formatter={(v, n) => [`${v}`, n]}
                  contentStyle={{ fontSize: 12, borderRadius: 6, border: '1px solid #E0E0E0' }}
                />
                <Legend iconType="circle" iconSize={8} wrapperStyle={{ fontSize: 11 }} />
              </PieChart>
            </ResponsiveContainer>
          ) : (
            <div className="flex items-center justify-center h-[190px] text-[#CCC] text-sm">
              Aucun document
            </div>
          )}
        </div>
      </div>

      {/* ── Actions rapides ─────────────────────────────────────────────── */}
      <div className="bg-white border border-[#E8E8E8] rounded-lg p-6">
        <SectionTitle icon={<TrendingUp size={14} />} label="Actions rapides" />
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          {[
            { to: '/folders', icon: <FolderOpen size={18} className="text-[#F5A800]" />, label: 'Parcourir les dossiers' },
            { to: '/search',  icon: <FileText size={18} className="text-[#1A1A1A]" />,   label: 'Rechercher un document' },
            ...(isAdmin ? [{ to: '/admin/audit', icon: <Clock size={18} className="text-[#888]" />, label: "Journal d'audit" }] : []),
          ].map((item) => (
            <Link
              key={item.to}
              to={item.to}
              className="flex items-center gap-3 p-3 rounded-md border border-[#E8E8E8] hover:border-[#F5A800] hover:bg-[#FFF8E7] transition-all group"
            >
              {item.icon}
              <span className="text-sm font-medium text-[#444] group-hover:text-[#1A1A1A]">{item.label}</span>
            </Link>
          ))}
        </div>
      </div>
    </div>
  )
}
