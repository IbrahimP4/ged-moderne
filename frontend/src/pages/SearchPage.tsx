import { useState, useEffect, useRef, useCallback } from 'react'
import { useSearchParams, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  Search, FileText, FileImage, File, FolderOpen, ChevronRight, X,
  Filter, SortAsc, SortDesc, Clock, CheckCircle2, XCircle,
  FileEdit, Layers, Eye,
} from 'lucide-react'
import { searchDocuments } from '@/api/search'
import { Badge } from '@/components/ui/Badge'
import { TagBadge } from '@/components/ui/TagBadge'
import { DocumentPreviewModal } from '@/features/documents/DocumentPreviewModal'
import { formatBytes, formatDate, STATUS_LABELS } from '@/lib/utils'
import { usePageTitle } from '@/hooks/usePageTitle'
import type { DocumentDTO, DocumentStatus } from '@/types'

// ── Types locaux ─────────────────────────────────────────────────────────────────────

interface SearchResultItem {
  id: string
  title: string
  status: DocumentStatus
  statusLabel: string
  folderId: string
  folderName: string
  ownerUsername: string
  versionCount: number
  mimeType: string | null
  fileSizeBytes: number
  createdAt: string
  updatedAt: string
  tags: string[]
  snippet: string | null
  matchedInContent: boolean
}

// ── Constants ─────────────────────────────────────────────────────────────────

const STATUS_OPTS: { value: DocumentStatus | ''; label: string; icon?: React.ReactNode }[] = [
  { value: '',               label: 'Tous les statuts' },
  { value: 'draft',          label: STATUS_LABELS.draft,          icon: <FileEdit size={12} /> },
  { value: 'pending_review', label: STATUS_LABELS.pending_review, icon: <Clock size={12} /> },
  { value: 'approved',       label: STATUS_LABELS.approved,       icon: <CheckCircle2 size={12} /> },
  { value: 'rejected',       label: STATUS_LABELS.rejected,       icon: <XCircle size={12} /> },
]

// ── Helpers ────────────────────────────────────────────────────────────────────

function FileIcon({ mimeType }: { mimeType: string | null }) {
  if (!mimeType) return <FileText size={20} className="text-faint" />
  if (mimeType.startsWith('image/')) return <FileImage size={20} className="text-violet-500" />
  if (mimeType === 'application/pdf') return <FileText size={20} className="text-red-500" />
  if (mimeType.includes('word') || mimeType.includes('document')) return <FileText size={20} className="text-blue-500" />
  return <File size={20} className="text-muted" />
}

/**
 * Surligne les termes de recherche dans un texte.
 * Retourne un array de fragments {text, highlight}.
 */
function highlightTerms(text: string, query: string): { text: string; highlight: boolean }[] {
  if (!query.trim()) return [{ text, highlight: false }]

  const words = query
    .trim()
    .split(/\s+/)
    .filter(w => w.length >= 2)
    .map(w => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))

  if (words.length === 0) return [{ text, highlight: false }]

  const regex = new RegExp(`(${words.join('|')})`, 'gi')
  const parts = text.split(regex)

  return parts.map(part => ({
    text: part,
    highlight: regex.test(part),
  }))
}

function HighlightedText({ text, query }: { text: string; query: string }) {
  const parts = highlightTerms(text, query)
  return (
    <>
      {parts.map((p, i) =>
        p.highlight ? (
          <mark
            key={i}
            className="bg-[#FFF3CC] text-[#8B5A00] rounded-sm px-0.5 font-semibold not-italic"
            style={{ textDecoration: 'none' }}
          >
            {p.text}
          </mark>
        ) : (
          <span key={i}>{p.text}</span>
        ),
      )}
    </>
  )
}

// ── Composant carte de résultat ────────────────────────────────────────────────

function SearchResultCard({
  result,
  query,
  onPreview,
}: {
  result: SearchResultItem
  query: string
  onPreview: (result: SearchResultItem) => void
}) {
  const navigate = useNavigate()

  return (
    <div
      className="group bg-card rounded-xl border border-base hover:border-[#F5A800] hover:shadow-md transition-all overflow-hidden cursor-pointer"
      onClick={() => navigate(`/documents/${result.id}`)}
    >
      <div className="flex items-start gap-4 p-4">
        {/* Icône fichier */}
        <div className="w-10 h-10 bg-muted group-hover:bg-[#FFF3CC] rounded-lg flex items-center justify-center shrink-0 transition-colors mt-0.5">
          <FileIcon mimeType={result.mimeType} />
        </div>

        {/* Contenu principal */}
        <div className="flex-1 min-w-0">
          {/* Titre + badge statut */}
          <div className="flex items-start justify-between gap-3 mb-1">
            <h3 className="text-sm font-semibold text-primary group-hover:text-[#A07000] transition-colors leading-snug">
              <HighlightedText text={result.title} query={query} />
            </h3>
            <div className="flex items-center gap-2 shrink-0">
              <Badge className={({
                draft: 'bg-gray-100 text-gray-700',
                pending_review: 'bg-amber-100 text-amber-700',
                approved: 'bg-green-100 text-green-700',
                rejected: 'bg-red-100 text-red-700',
                archived: 'bg-slate-100 text-slate-600',
                obsolete: 'bg-stone-100 text-stone-600',
              } as Record<string, string>)[result.status] ?? 'bg-gray-100 text-gray-700'}>
                {result.statusLabel}
              </Badge>
            </div>
          </div>

          {/* Snippet de contenu (si match dans le contenu) */}
          {result.snippet && (
            <div className="mt-2 mb-2">
              <div className="flex items-center gap-1 mb-1">
                <Layers size={11} className="text-[#6366F1]" />
                <span className="text-[10px] font-semibold text-[#6366F1] uppercase tracking-wide">
                  Trouvé dans le contenu
                </span>
              </div>
              <p className="text-xs text-secondary bg-[#F8F8FF] border border-[#E8E8FF] rounded-lg px-3 py-2 leading-relaxed font-mono-like italic">
                <HighlightedText text={result.snippet} query={query} />
              </p>
            </div>
          )}

          {/* Métadonnées */}
          <div className="flex items-center gap-3 text-xs text-muted flex-wrap mt-1.5">
            <button
              onClick={e => { e.stopPropagation(); navigate(`/folders/${result.folderId}`) }}
              className="flex items-center gap-1 hover:text-[#F5A800] transition-colors"
            >
              <FolderOpen size={11} />
              {result.folderName}
            </button>
            <span>{formatBytes(result.fileSizeBytes)}</span>
            <span className="flex items-center gap-1">
              <Clock size={10} />
              {formatDate(result.updatedAt)}
            </span>
            {result.versionCount > 1 && (
              <span className="flex items-center gap-1 text-muted">
                <Clock size={10} />
                v{result.versionCount}
              </span>
            )}
          </div>

          {/* Tags */}
          {result.tags.length > 0 && (
            <div className="flex gap-1 flex-wrap mt-1.5">
              {result.tags.map(tag => (
                <TagBadge key={tag} tag={tag} />
              ))}
            </div>
          )}
        </div>

        {/* Actions */}
        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
          <button
            onClick={e => { e.stopPropagation(); onPreview(result) }}
            className="p-1.5 rounded-lg text-muted hover:text-[#6366F1] hover:bg-indigo-50 transition-colors"
            title="Aperçu rapide"
          >
            <Eye size={15} />
          </button>
          <ChevronRight size={15} className="text-ghost group-hover:text-[#F5A800] transition-colors" />
        </div>
      </div>
    </div>
  )
}

// ── Page principale ────────────────────────────────────────────────────────────

export function SearchPage() {
  const [searchParams, setSearchParams] = useSearchParams()

  const qParam      = searchParams.get('q') ?? ''
  const statusParam = (searchParams.get('status') ?? '') as DocumentStatus | ''

  const [inputValue, setInputValue]   = useState(qParam)
  const [status, setStatus]           = useState<DocumentStatus | ''>(statusParam)
  const [sortDesc, setSortDesc]       = useState(true)
  const [showFilters, setShowFilters] = useState(false)
  const [previewDoc, setPreviewDoc]   = useState<SearchResultItem | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  usePageTitle(qParam ? `Recherche : ${qParam}` : 'Recherche')

  // Debounce: update URL after 350ms pause
  useEffect(() => {
    const timer = setTimeout(() => {
      const params: Record<string, string> = {}
      if (inputValue.trim().length >= 2) params.q = inputValue.trim()
      if (status) params.status = status
      setSearchParams(params, { replace: true })
    }, 350)
    return () => clearTimeout(timer)
  }, [inputValue, status, setSearchParams])

  // Cmd/Ctrl+K focuses search
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault()
        inputRef.current?.focus()
        inputRef.current?.select()
      }
    }
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  }, [])

  const { data, isLoading, isFetching } = useQuery<{
    results: SearchResultItem[]
    total: number
    query: string
  }>({
    queryKey: ['search', qParam, status],
    queryFn: () => searchDocuments(qParam, undefined, 100, status || undefined) as unknown as Promise<{ results: SearchResultItem[]; total: number; query: string }>,
    enabled: qParam.length >= 2,
  })

  const hasQuery = qParam.length >= 2

  const sorted = [...(data?.results ?? [])].sort((a, b) => {
    const diff = new Date(b.updatedAt).getTime() - new Date(a.updatedAt).getTime()
    return sortDesc ? diff : -diff
  })

  const contentMatches = sorted.filter(r => r.matchedInContent)

  const clearSearch = useCallback(() => {
    setInputValue('')
    setStatus('')
    setSearchParams({}, { replace: true })
    inputRef.current?.focus()
  }, [setSearchParams])

  // Pour l'aperçu rapide, construire un objet DocumentDTO minimal
  const previewDocDTO: DocumentDTO | null = previewDoc ? {
    id: previewDoc.id,
    title: previewDoc.title,
    status: previewDoc.status as DocumentStatus,
    statusLabel: previewDoc.statusLabel,
    folderId: previewDoc.folderId,
    folderName: previewDoc.folderName,
    ownerId: '',
    ownerUsername: previewDoc.ownerUsername,
    versionCount: previewDoc.versionCount,
    latestVersion: null,
    versions: [],
    comment: null,
    createdAt: previewDoc.createdAt,
    updatedAt: previewDoc.updatedAt,
    tags: previewDoc.tags,
    mimeType: previewDoc.mimeType ?? 'application/octet-stream',
    fileSizeBytes: previewDoc.fileSizeBytes,
    originalFilename: previewDoc.title,
  } as unknown as DocumentDTO : null

  return (
    <div className="min-h-screen bg-page">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 py-6 sm:py-8">

        {/* ── Barre de recherche ─────────────────────────────────────────── */}
        <div className="bg-card rounded-xl border border-base shadow-sm mb-5 overflow-hidden">
          <div className="flex items-center gap-3 px-5 py-4">
            <Search size={20} className="text-[#F5A800] shrink-0" />
            <input
              ref={inputRef}
              autoFocus
              type="text"
              value={inputValue}
              onChange={e => setInputValue(e.target.value)}
              placeholder="Rechercher dans les titres et le contenu des documents…"
              className="flex-1 text-sm bg-transparent text-primary placeholder-[#AAA] focus:outline-none"
            />
            <div className="flex items-center gap-2 shrink-0">
              {(inputValue || status) && (
                <button
                  onClick={clearSearch}
                  className="p-1 rounded-md text-faint hover:text-secondary hover:bg-muted transition-colors"
                >
                  <X size={15} />
                </button>
              )}
              <button
                onClick={() => setShowFilters(!showFilters)}
                className={`flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors ${
                  showFilters || status
                    ? 'bg-[#FFF3CC] text-[#A07000] border border-[#F5D580]'
                    : 'text-muted hover:bg-muted'
                }`}
              >
                <Filter size={13} />
                Filtres
                {status && <span className="w-1.5 h-1.5 rounded-full bg-[#F5A800]" />}
              </button>
              <kbd className="hidden sm:flex items-center gap-1 px-1.5 py-0.5 bg-muted border border-strong rounded text-[10px] text-muted font-mono">
                ⌘K
              </kbd>
            </div>
          </div>

          {/* Filter bar */}
          {showFilters && (
            <div className="border-t border-muted px-5 py-3 bg-subtle flex items-center gap-3 flex-wrap">
              <span className="text-xs font-semibold text-muted uppercase tracking-wide">Statut :</span>
              <div className="flex items-center gap-1.5 flex-wrap">
                {STATUS_OPTS.map(opt => (
                  <button
                    key={opt.value}
                    onClick={() => setStatus(opt.value)}
                    className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-all ${
                      status === opt.value
                        ? 'bg-[#F5A800] text-primary shadow-sm'
                        : 'bg-card border border-strong text-secondary hover:border-[#F5A800] hover:text-[#A07000]'
                    }`}
                  >
                    {opt.icon}
                    {opt.label}
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* ── Info ligne + tri ──────────────────────────────────────────────── */}
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <div className="w-1 h-4 bg-[#F5A800] rounded-full" />
            {hasQuery ? (
              <div className="flex items-center gap-2 flex-wrap">
                <p className="text-xs font-bold text-secondary uppercase tracking-widest">
                  {isFetching
                    ? 'Recherche en cours…'
                    : `${data?.total ?? 0} résultat${(data?.total ?? 0) !== 1 ? 's' : ''}`
                  }
                </p>
                {!isFetching && (data?.total ?? 0) > 0 && contentMatches.length > 0 && (
                  <span className="text-[11px] text-[#6366F1] bg-indigo-50 border border-indigo-200 rounded-full px-2 py-0.5 font-medium">
                    {contentMatches.length} dans le contenu
                  </span>
                )}
                {status && (
                  <span className="text-xs normal-case font-normal text-[#F5A800]">
                    — {STATUS_OPTS.find(o => o.value === status)?.label}
                  </span>
                )}
              </div>
            ) : (
              <p className="text-xs font-bold text-secondary uppercase tracking-widest">Recherche full-text</p>
            )}
          </div>

          {hasQuery && (data?.total ?? 0) > 0 && (
            <button
              onClick={() => setSortDesc(!sortDesc)}
              className="flex items-center gap-1.5 text-xs font-medium text-muted hover:text-secondary transition-colors"
            >
              {sortDesc ? <SortDesc size={14} /> : <SortAsc size={14} />}
              {sortDesc ? 'Plus récents' : 'Plus anciens'}
            </button>
          )}
        </div>

        {/* ── État vide ─────────────────────────────────────────────────────── */}
        {!hasQuery && (
          <div className="bg-card rounded-xl border border-base flex flex-col items-center justify-center py-24 text-center">
            <div className="w-20 h-20 bg-gradient-to-br from-[#FFF3CC] to-[#FFE08A] rounded-2xl flex items-center justify-center mb-5 shadow-sm">
              <Search size={32} className="text-[#F5A800]" />
            </div>
            <h2 className="text-base font-bold text-primary mb-1">Recherche intelligente</h2>
            <p className="text-sm text-muted max-w-xs">
              Tapez au moins 2 caractères pour chercher dans les <strong>titres</strong> et le <strong>contenu</strong> de vos documents (PDF, Word, texte…)
            </p>
            <div className="mt-5 flex items-center gap-2 flex-wrap justify-center text-xs text-faint">
              <span className="px-2.5 py-1 bg-muted rounded-full">Recherche multi-mots</span>
              <span className="px-2.5 py-1 bg-muted rounded-full">Contenu des PDFs</span>
              <span className="px-2.5 py-1 bg-muted rounded-full">Documents Word</span>
              <span className="px-2.5 py-1 bg-muted rounded-full">Fichiers texte</span>
            </div>
          </div>
        )}

        {/* ── Chargement ────────────────────────────────────────────────────── */}
        {hasQuery && isLoading && (
          <div className="flex flex-col items-center justify-center py-16">
            <div className="w-10 h-10 border-2 border-[#F5A800] border-t-transparent rounded-full animate-spin mb-3" />
            <p className="text-sm text-muted">Recherche dans les titres et contenus…</p>
          </div>
        )}

        {/* ── Aucun résultat ────────────────────────────────────────────────── */}
        {hasQuery && !isLoading && data && data.results.length === 0 && (
          <div className="bg-card rounded-xl border border-base flex flex-col items-center justify-center py-20 text-center">
            <div className="w-16 h-16 bg-muted rounded-xl flex items-center justify-center mb-4">
              <FileText size={28} className="text-ghost" />
            </div>
            <p className="text-sm font-semibold text-secondary">Aucun document trouvé</p>
            <p className="text-xs mt-1 text-muted max-w-xs">
              Aucun titre ni contenu ne contient « <strong>{qParam}</strong> »
            </p>
            {status && (
              <button
                onClick={() => setStatus('')}
                className="mt-3 text-xs text-[#F5A800] hover:underline"
              >
                Supprimer le filtre statut
              </button>
            )}
          </div>
        )}

        {/* ── Résultats ─────────────────────────────────────────────────────── */}
        {hasQuery && !isLoading && sorted.length > 0 && (
          <div className="space-y-2.5">
            {sorted.map(result => (
              <SearchResultCard
                key={result.id}
                result={result as unknown as SearchResultItem}
                query={qParam}
                onPreview={r => setPreviewDoc(r as unknown as SearchResultItem)}
              />
            ))}
          </div>
        )}
      </div>

      {/* ── Aperçu rapide ────────────────────────────────────────────────────── */}
      {previewDocDTO && (
        <DocumentPreviewModal
          doc={previewDocDTO as Parameters<typeof DocumentPreviewModal>[0]['doc']}
          open={!!previewDoc}
          onClose={() => setPreviewDoc(null)}
        />
      )}
    </div>
  )
}
