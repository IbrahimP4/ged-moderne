import { useState, useMemo, useCallback, useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  Download, Search, Filter, X, Shield, Clock,
  Upload, Trash2, CheckCircle, XCircle, History,
  RefreshCw, FileText, User, AlertTriangle, ChevronDown,
  ChevronLeft, ChevronRight,
} from 'lucide-react'
import { api } from '@/lib/axios'
import { useAuthStore } from '@/store/auth'
import { toast } from 'sonner'
import { PageSpinner } from '@/components/ui/Spinner'
import { usePageTitle } from '@/hooks/usePageTitle'
import type { AuditLogEntry } from '@/types'

// ── Types ────────────────────────────────────────────────────────────────────

interface AuditLogEntryEnriched extends AuditLogEntry {
  actorUsername: string | null
}

interface AuditPageResponse {
  data: AuditLogEntryEnriched[]
  pagination: {
    page: number
    per_page: number
    total: number
    pages: number
  }
}

interface EventMeta {
  label: string
  description: (payload: Record<string, unknown>, actor: string) => string
  icon: React.ReactNode
  color: string
  dotColor: string
  category: 'document' | 'user' | 'signature' | 'folder' | 'system'
}

// ── Métadonnées événements ─────────────────────────────────────────────────────

const EVENT_META: Record<string, EventMeta> = {
  'document.uploaded': {
    label: 'Document importé',
    description: (p, actor) => `${actor} a importé le document "${p.title ?? '—'}"`,
    icon: <Upload size={14} />,
    color: 'bg-blue-50 text-blue-700 border border-blue-200',
    dotColor: '#3B82F6',
    category: 'document',
  },
  'document.status_changed': {
    label: 'Statut modifié',
    description: (p, actor) => {
      const from = STATUS_FR[p.from as string] ?? p.previousStatus ?? p.from
      const to   = STATUS_FR[p.to as string]   ?? p.newStatus ?? p.to
      return `${actor} a changé le statut de "${p.title ?? '—'}" : ${from} → ${to}`
    },
    icon: <RefreshCw size={14} />,
    color: 'bg-amber-50 text-amber-700 border border-amber-200',
    dotColor: '#F5A800',
    category: 'document',
  },
  'document.deleted': {
    label: 'Document supprimé',
    description: (p, actor) => `${actor} a supprimé le document "${p.title ?? '—'}"`,
    icon: <Trash2 size={14} />,
    color: 'bg-red-50 text-red-700 border border-red-200',
    dotColor: '#EF4444',
    category: 'document',
  },
  'document.version_added': {
    label: 'Nouvelle version',
    description: (p, actor) => `${actor} a ajouté la version ${p.versionNumber ?? ''} du document "${p.title ?? '—'}"`,
    icon: <History size={14} />,
    color: 'bg-purple-50 text-purple-700 border border-purple-200',
    dotColor: '#8B5CF6',
    category: 'document',
  },
  'document.restored': {
    label: 'Document restauré',
    description: (p, actor) => `${actor} a restauré le document "${p.title ?? '—'}" depuis la corbeille`,
    icon: <RefreshCw size={14} />,
    color: 'bg-green-50 text-green-700 border border-green-200',
    dotColor: '#22C55E',
    category: 'document',
  },
  'document.permanently_deleted': {
    label: 'Suppression définitive',
    description: (p, actor) => `${actor} a définitivement supprimé "${p.title ?? '—'}"`,
    icon: <Trash2 size={14} />,
    color: 'bg-red-100 text-red-800 border border-red-300',
    dotColor: '#DC2626',
    category: 'document',
  },
  'signature.requested': {
    label: 'Signature demandée',
    description: (p, actor) => `${actor} a demandé une signature pour le document "${p.documentTitle ?? '—'}"`,
    icon: <FileText size={14} />,
    color: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
    dotColor: '#6366F1',
    category: 'signature',
  },
  'signature.signed': {
    label: 'Document signé',
    description: (p, actor) => `${actor} a signé le document "${p.documentTitle ?? '—'}"`,
    icon: <CheckCircle size={14} />,
    color: 'bg-green-50 text-green-700 border border-green-200',
    dotColor: '#16A34A',
    category: 'signature',
  },
  'signature.declined': {
    label: 'Signature refusée',
    description: (p, actor) => `${actor} a refusé de signer le document "${p.documentTitle ?? '—'}"`,
    icon: <XCircle size={14} />,
    color: 'bg-red-50 text-red-700 border border-red-200',
    dotColor: '#DC2626',
    category: 'signature',
  },
  'user.created': {
    label: 'Compte créé',
    description: (p, actor) => `${actor} a créé le compte utilisateur "${p.username ?? '—'}"`,
    icon: <User size={14} />,
    color: 'bg-teal-50 text-teal-700 border border-teal-200',
    dotColor: '#14B8A6',
    category: 'user',
  },
  'user.role_changed': {
    label: 'Rôle modifié',
    description: (p, actor) =>
      `${actor} a changé le rôle de "${p.username ?? '—'}" en ${p.makeAdmin ? 'Administrateur' : 'Utilisateur'}`,
    icon: <Shield size={14} />,
    color: 'bg-orange-50 text-orange-700 border border-orange-200',
    dotColor: '#F97316',
    category: 'user',
  },
}

const STATUS_FR: Record<string, string> = {
  draft: 'Brouillon', pending_review: 'En révision',
  approved: 'Approuvé', rejected: 'Rejeté',
  archived: 'Archivé', obsolete: 'Obsolète',
}

const CATEGORIES = [
  { key: 'all',       label: 'Tous' },
  { key: 'document',  label: 'Documents' },
  { key: 'signature', label: 'Signatures' },
  { key: 'user',      label: 'Utilisateurs' },
  { key: 'folder',    label: 'Dossiers' },
]

const PER_PAGE = 50

// ── Helpers ────────────────────────────────────────────────────────────────────

function formatDateGroup(iso: string): string {
  const d = new Date(iso)
  const today     = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(today.getDate() - 1)
  if (d.toDateString() === today.toDateString()) return "Aujourd'hui"
  if (d.toDateString() === yesterday.toDateString()) return 'Hier'
  return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

function getDateGroup(iso: string): string {
  return new Date(iso).toDateString()
}

function humanizeEvent(entry: AuditLogEntryEnriched): string {
  const meta  = EVENT_META[entry.eventName]
  const actor = entry.actorUsername ?? 'Le système'
  if (meta) return meta.description(entry.payload as Record<string, unknown>, actor)
  const parts  = entry.eventName.split('.')
  const action = parts[1]?.replace(/_/g, ' ') ?? entry.eventName
  return `${actor} a effectué l'action "${action}" sur ${entry.aggregateType.toLowerCase()}`
}

function getMeta(eventName: string): EventMeta {
  return (
    EVENT_META[eventName] ?? {
      label: eventName.replace(/\./g, ' › ').replace(/_/g, ' '),
      description: (_, actor) => `${actor} — ${eventName}`,
      icon: <AlertTriangle size={14} />,
      color: 'bg-gray-100 text-gray-600 border border-gray-200',
      dotColor: '#9CA3AF',
      category: 'system' as const,
    }
  )
}

// ── Composant pagination ───────────────────────────────────────────────────────

function ServerPagination({
  page, pages, total, perPage, onPageChange,
}: {
  page: number
  pages: number
  total: number
  perPage: number
  onPageChange: (p: number) => void
}) {
  if (pages <= 1) return null
  const from = (page - 1) * perPage + 1
  const to   = Math.min(page * perPage, total)

  return (
    <div className="flex items-center justify-between mt-5 flex-wrap gap-3">
      <p className="text-xs text-muted">
        Entrées <strong className="text-primary">{from}–{to}</strong> sur{' '}
        <strong className="text-primary">{total}</strong>
      </p>
      <div className="flex items-center gap-1">
        <button
          onClick={() => onPageChange(page - 1)}
          disabled={page <= 1}
          className="w-8 h-8 rounded-lg flex items-center justify-center text-muted hover:bg-border-muted disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronLeft size={15} />
        </button>

        {Array.from({ length: Math.min(pages, 7) }, (_, i) => {
          let p = i + 1
          if (pages > 7) {
            if (page <= 4) p = i + 1
            else if (page >= pages - 3) p = pages - 6 + i
            else p = page - 3 + i
          }
          return (
            <button
              key={p}
              onClick={() => onPageChange(p)}
              className={`w-8 h-8 rounded-lg text-xs font-semibold transition-all ${
                p === page
                  ? 'bg-[#1A1A1A] text-white'
                  : 'text-secondary hover:bg-border-muted'
              }`}
            >
              {p}
            </button>
          )
        })}

        <button
          onClick={() => onPageChange(page + 1)}
          disabled={page >= pages}
          className="w-8 h-8 rounded-lg flex items-center justify-center text-muted hover:bg-border-muted disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronRight size={15} />
        </button>
      </div>
    </div>
  )
}

// ── Page principale ────────────────────────────────────────────────────────────

export function AuditPage() {
  usePageTitle("Journal d'audit")

  const [search,   setSearch]   = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [category, setCategory] = useState('all')
  const [page,     setPage]     = useState(1)
  const [expanded, setExpanded] = useState<Set<string>>(new Set())

  // Debounce la recherche (évite un appel API à chaque frappe)
  useEffect(() => {
    const t = setTimeout(() => {
      setDebouncedSearch(search)
      setPage(1)
    }, 400)
    return () => clearTimeout(t)
  }, [search])

  const { data, isLoading, refetch, isFetching } = useQuery<AuditPageResponse>({
    queryKey: ['admin', 'audit', page, debouncedSearch, category],
    queryFn: async () => {
      const params: Record<string, string | number> = {
        page,
        per_page: PER_PAGE,
      }
      if (debouncedSearch) params.search   = debouncedSearch
      if (category !== 'all') params.category = category

      const { data } = await api.get<AuditPageResponse>('/admin/audit', { params })
      return data
    },
    staleTime: 15_000,
  })

  const entries    = data?.data ?? []
  const pagination = data?.pagination

  // Grouper par jour
  const grouped = useMemo(() => {
    const groups: Map<string, AuditLogEntryEnriched[]> = new Map()
    for (const entry of entries) {
      const key = getDateGroup(entry.occurredAt)
      if (!groups.has(key)) groups.set(key, [])
      groups.get(key)!.push(entry)
    }
    return Array.from(groups.entries())
  }, [entries])

  const toggleExpanded = useCallback((id: string) => {
    setExpanded(prev => {
      const next = new Set(prev)
      next.has(id) ? next.delete(id) : next.add(id)
      return next
    })
  }, [])

  // Export CSV via l'API backend (inclut tous les filtres actifs)
  const handleExportCsv = useCallback(async () => {
    const params: Record<string, string | number> = { limit: 5000 }
    if (debouncedSearch) params.search   = debouncedSearch
    if (category !== 'all') params.category = category

    const url = new URL('/api/admin/audit/export-csv', window.location.origin)
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, String(v)))

    const token = useAuthStore.getState().token ?? ''
    const res = await fetch(url.toString(), {
      headers: { Authorization: `Bearer ${token}` },
    })
    if (!res.ok) {
      toast.error('Erreur lors de l\'export CSV.')
      return
    }
    const blob = await res.blob()
    const href = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = href
    a.download = `journal_audit_${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(href)
  }, [debouncedSearch, category])

  if (isLoading) return <PageSpinner />

  return (
    <div className="min-h-screen bg-page">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 py-6 sm:py-8">

        {/* ── Header ── */}
        <div className="mb-6">
          <div className="flex items-center gap-3 mb-1">
            <div className="w-1 h-6 bg-[#F5A800] rounded-full" />
            <h1 className="text-xl font-black text-primary tracking-tight">Journal d'activité</h1>
          </div>
          <p className="text-sm text-muted ml-4">
            Historique complet des actions effectuées dans l'application
          </p>
        </div>

        {/* ── Toolbar ── */}
        <div className="bg-card border border-base rounded-xl p-3 mb-5 flex flex-col sm:flex-row gap-3 shadow-sm">
          {/* Recherche */}
          <div className="relative flex-1">
            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
            <input
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Rechercher une action, un utilisateur…"
              className="w-full pl-9 pr-8 py-2 rounded-lg border border-base text-sm bg-subtle focus:outline-none focus:ring-2 focus:ring-[#F5A800] text-primary placeholder-[#CCC]"
            />
            {search && (
              <button
                onClick={() => setSearch('')}
                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-faint hover:text-secondary"
              >
                <X size={13} />
              </button>
            )}
          </div>

          {/* Filtre catégorie */}
          <div className="flex items-center gap-1 flex-wrap">
            <Filter size={13} className="text-faint shrink-0 mr-1" />
            {CATEGORIES.map(cat => (
              <button
                key={cat.key}
                onClick={() => { setCategory(cat.key); setPage(1) }}
                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                  category === cat.key
                    ? 'bg-[#1A1A1A] text-white'
                    : 'bg-muted text-muted hover:bg-border hover:text-secondary'
                }`}
              >
                {cat.label}
              </button>
            ))}
          </div>

          {/* Actions */}
          <div className="flex items-center gap-2 shrink-0">
            <button
              onClick={() => refetch()}
              disabled={isFetching}
              className="p-2 rounded-lg text-muted hover:bg-muted hover:text-secondary transition-colors disabled:opacity-50"
              title="Actualiser"
            >
              <RefreshCw size={15} className={isFetching ? 'animate-spin' : ''} />
            </button>
            <button
              onClick={handleExportCsv}
              className="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-[#1A1A1A] text-white text-xs font-semibold hover:bg-[#F5A800] hover:text-primary transition-all"
              title="Exporter en CSV (Excel)"
            >
              <Download size={13} />
              <span className="hidden sm:inline">Exporter CSV</span>
            </button>
          </div>
        </div>

        {/* ── Résumé ── */}
        {pagination && (
          <div className="flex items-center gap-2 mb-4 text-sm text-muted">
            <Clock size={13} />
            <span>
              <strong className="text-primary">{pagination.total}</strong> événement{pagination.total !== 1 ? 's' : ''}
              {debouncedSearch && ` correspondant à "${debouncedSearch}"`}
              {category !== 'all' && ` dans la catégorie "${CATEGORIES.find(c => c.key === category)?.label}"`}
              {pagination.pages > 1 && (
                <span className="ml-2 text-xs bg-muted border border-base rounded-full px-2 py-0.5">
                  Page {pagination.page}/{pagination.pages}
                </span>
              )}
            </span>
          </div>
        )}

        {/* ── Timeline groupée par jour ── */}
        {grouped.length === 0 ? (
          <div className="bg-card border border-base rounded-xl text-center py-20 shadow-sm">
            <AlertTriangle size={36} className="mx-auto text-[#D0D0D0] mb-3" />
            <p className="text-muted font-medium text-sm">Aucune activité trouvée</p>
            {search && (
              <button onClick={() => setSearch('')} className="text-xs text-[#F5A800] hover:underline mt-1">
                Effacer la recherche
              </button>
            )}
          </div>
        ) : (
          <div className="space-y-6">
            {grouped.map(([dateKey, dayEntries]) => (
              <div key={dateKey}>
                {/* Séparateur de jour */}
                <div className="flex items-center gap-3 mb-3">
                  <div className="h-px flex-1 bg-border" />
                  <span className="text-[11px] font-bold text-faint uppercase tracking-widest px-2">
                    {formatDateGroup(dayEntries[0].occurredAt)}
                  </span>
                  <div className="h-px flex-1 bg-border" />
                </div>

                <div className="space-y-2">
                  {dayEntries.map(entry => {
                    const meta    = getMeta(entry.eventName)
                    const isOpen  = expanded.has(entry.id)
                    const hasMore = Object.keys(entry.payload as object).length > 0

                    return (
                      <div
                        key={entry.id}
                        className="bg-card border border-base rounded-xl overflow-hidden hover:border-strong hover:shadow-sm transition-all"
                      >
                        <div className="flex items-start gap-4 px-4 py-3.5">
                          <div
                            className="w-8 h-8 rounded-xl flex items-center justify-center text-white shrink-0 mt-0.5"
                            style={{ background: meta.dotColor }}
                          >
                            {meta.icon}
                          </div>

                          <div className="flex-1 min-w-0">
                            <div className="flex items-center justify-between gap-2 mb-1 flex-wrap">
                              <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold ${meta.color}`}>
                                {meta.label}
                              </span>
                              <span className="text-[11px] text-faint shrink-0 flex items-center gap-1">
                                <Clock size={11} />
                                {new Date(entry.occurredAt).toLocaleTimeString('fr-FR', {
                                  hour: '2-digit', minute: '2-digit',
                                })}
                              </span>
                            </div>

                            <p className="text-sm text-primary font-medium leading-snug">
                              {humanizeEvent(entry)}
                            </p>

                            <div className="flex items-center gap-3 mt-1.5 flex-wrap">
                              <span className="flex items-center gap-1 text-xs text-muted">
                                <User size={11} />
                                {entry.actorUsername
                                  ? <strong className="text-secondary">{entry.actorUsername}</strong>
                                  : <em className="text-faint">Action automatique</em>
                                }
                              </span>
                            </div>
                          </div>

                          {hasMore && (
                            <button
                              onClick={() => toggleExpanded(entry.id)}
                              className="shrink-0 p-1.5 rounded-lg text-faint hover:text-secondary hover:bg-muted transition-all"
                              title={isOpen ? 'Masquer les détails' : 'Voir les détails techniques'}
                            >
                              <ChevronDown
                                size={15}
                                className={`transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                              />
                            </button>
                          )}
                        </div>

                        {hasMore && isOpen && (
                          <div className="border-t border-muted bg-subtle px-4 py-3">
                            <p className="text-[10px] font-bold text-faint uppercase tracking-widest mb-2">
                              Détails techniques
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1">
                              {Object.entries(entry.payload as Record<string, unknown>).map(([k, v]) => (
                                <div key={k} className="flex items-start gap-2 text-xs">
                                  <span className="text-faint shrink-0 capitalize min-w-[80px]">
                                    {k.replace(/_/g, ' ')}
                                  </span>
                                  <span className="text-secondary font-medium break-all">
                                    {typeof v === 'object' ? JSON.stringify(v) : String(v)}
                                  </span>
                                </div>
                              ))}
                              <div className="flex items-start gap-2 text-xs col-span-full mt-1 pt-1 border-t border-[#EBEBEB]">
                                <span className="text-faint shrink-0 min-w-[80px]">ID objet</span>
                                <span className="text-ghost font-mono text-[10px]">{entry.aggregateId}</span>
                              </div>
                            </div>
                          </div>
                        )}
                      </div>
                    )
                  })}
                </div>
              </div>
            ))}
          </div>
        )}

        {/* ── Pagination ── */}
        {pagination && (
          <ServerPagination
            page={pagination.page}
            pages={pagination.pages}
            total={pagination.total}
            perPage={pagination.per_page}
            onPageChange={setPage}
          />
        )}

      </div>
    </div>
  )
}
