import { useState, useCallback } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  FolderOpen, Upload, FolderPlus, ChevronRight, Home,
  Pencil, Trash2, Filter, X, Shield, Lock, Download,
  CheckSquare, Square, Layers, MoveRight, Archive,
} from 'lucide-react'
import { getFolder, getRootFolder, renameFolder, deleteFolder } from '@/api/folders'
import {
  bulkDeleteDocuments, bulkMoveDocuments, bulkExportDocuments,
} from '@/api/documents'
import { FolderTree } from '@/features/folders/FolderTree'
import { CreateFolderModal } from '@/features/folders/CreateFolderModal'
import { PermissionsModal } from '@/features/folders/PermissionsModal'
import { UploadModal } from '@/features/documents/UploadModal'
import { MoveDocumentModal } from '@/features/documents/MoveDocumentModal'
import { DocumentRow } from '@/features/documents/DocumentRow'
import { DocumentPreviewModal } from '@/features/documents/DocumentPreviewModal'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { ConfirmModal } from '@/components/ui/ConfirmModal'
import { Pagination } from '@/components/ui/Pagination'
import { TagBadge } from '@/components/ui/TagBadge'
import { PageSpinner } from '@/components/ui/Spinner'
import { STATUS_LABELS } from '@/lib/utils'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useAuthStore } from '@/store/auth'
import type { DocumentDTO, DocumentStatus } from '@/types'

const STATUS_OPTIONS: { value: DocumentStatus | 'all'; label: string }[] = [
  { value: 'all', label: 'Tous les statuts' },
  { value: 'draft', label: STATUS_LABELS.draft },
  { value: 'pending_review', label: STATUS_LABELS.pending_review },
  { value: 'approved', label: STATUS_LABELS.approved },
  { value: 'rejected', label: STATUS_LABELS.rejected },
]

function RenameFolderModal({ open, onClose, onConfirm, loading, currentName }: {
  open: boolean; onClose: () => void; onConfirm: (name: string) => void; loading: boolean; currentName: string
}) {
  const [name, setName] = useState(currentName)
  return (
    <Modal open={open} onClose={onClose} title="Renommer le dossier">
      <div className="space-y-4">
        <input autoFocus type="text" value={name} onChange={(e) => setName(e.target.value)} maxLength={255}
          className="w-full px-3.5 py-2.5 rounded-lg border border-[#E8E8E8] text-sm focus:outline-none focus:ring-2 focus:ring-[#F5A800] bg-white text-[#1A1A1A]"
          placeholder="Nom du dossier" />
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>Annuler</Button>
          <Button loading={loading} disabled={!name.trim() || name === currentName} onClick={() => onConfirm(name)}>
            Renommer
          </Button>
        </div>
      </div>
    </Modal>
  )
}

export function FolderPage() {
  const { id } = useParams<{ id: string }>()
  const qc      = useQueryClient()
  const isAdmin = useAuthStore((s) => s.isAdmin)

  const [page, setPage]                 = useState(1)
  const [statusFilter, setStatusFilter] = useState<DocumentStatus | 'all'>('all')
  const [tagFilter, setTagFilter]       = useState<string | null>(null)
  const [showUpload, setShowUpload]     = useState(false)
  const [showCreate, setShowCreate]     = useState(false)
  const [showRename, setShowRename]     = useState(false)
  const [showDelete, setShowDelete]     = useState(false)
  const [showFilters, setShowFilters]   = useState(false)
  const [showPerms, setShowPerms]       = useState(false)

  // ── Quick preview ───────────────────────────────────────────────────────────
  const [previewDoc, setPreviewDoc] = useState<DocumentDTO | null>(null)

  // ── Bulk selection state ────────────────────────────────────────────────────
  const [bulkMode, setBulkMode]         = useState(false)
  const [selected, setSelected]         = useState<Set<string>>(new Set())
  const [showBulkMove, setShowBulkMove] = useState(false)
  const [showBulkDelete, setShowBulkDelete] = useState(false)
  const [bulkExporting, setBulkExporting] = useState(false)

  const queryKey = ['folder', id ?? 'root', page]
  const { data, isLoading, isError } = useQuery({
    queryKey,
    queryFn: () => id ? getFolder(id, page) : getRootFolder(page),
  })

  usePageTitle(data?.folder?.name ?? 'Documents')

  const renameMutation = useMutation({
    mutationFn: (name: string) => renameFolder(id!, name),
    onSuccess: () => {
      setShowRename(false)
      qc.invalidateQueries({ queryKey: ['folder'] })
      toast.success('Dossier renommé.')
    },
    onError: () => toast.error('Impossible de renommer le dossier.'),
  })

  const deleteMutation = useMutation({
    mutationFn: () => deleteFolder(id!),
    onSuccess: () => {
      toast.success('Dossier supprimé.')
      qc.invalidateQueries({ queryKey: ['folder'] })
    },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
      toast.error(msg ?? 'Impossible de supprimer le dossier.')
    },
  })

  // ── Bulk handlers ───────────────────────────────────────────────────────────
  const toggleSelect = useCallback((docId: string) => {
    setSelected((prev) => {
      const next = new Set(prev)
      if (next.has(docId)) next.delete(docId)
      else next.add(docId)
      return next
    })
  }, [])

  const toggleSelectAll = () => {
    if (selected.size === filteredDocs.length) {
      setSelected(new Set())
    } else {
      setSelected(new Set(filteredDocs.map((d) => d.id)))
    }
  }

  const exitBulkMode = () => {
    setBulkMode(false)
    setSelected(new Set())
  }

  const handleBulkDelete = async () => {
    try {
      await bulkDeleteDocuments(Array.from(selected))
      qc.invalidateQueries({ queryKey: ['folder'] })
      toast.success(`${selected.size} document(s) supprimé(s).`)
      exitBulkMode()
    } catch {
      toast.error('Erreur lors de la suppression.')
    }
  }

  const handleBulkMove = async (targetFolderId: string) => {
    try {
      await bulkMoveDocuments(Array.from(selected), targetFolderId)
      qc.invalidateQueries({ queryKey: ['folder'] })
      toast.success(`${selected.size} document(s) déplacé(s).`)
      setShowBulkMove(false)
      exitBulkMode()
    } catch {
      toast.error('Erreur lors du déplacement.')
    }
  }

  const handleBulkExport = async () => {
    setBulkExporting(true)
    try {
      const date = new Date().toISOString().slice(0, 10)
      await bulkExportDocuments(Array.from(selected), `export_${date}.zip`)
      toast.success('Archive ZIP téléchargée.')
    } catch {
      toast.error('Erreur lors de l\'export ZIP.')
    } finally {
      setBulkExporting(false)
    }
  }

  if (isLoading) return (
    <div className="flex h-screen bg-[#F4F4F4]">
      <FolderTree onCreateFolder={() => setShowCreate(true)} />
      <div className="flex-1"><PageSpinner /></div>
    </div>
  )

  if (isError || !data) return <div className="p-8 text-red-600">Erreur de chargement.</div>

  const currentFolder = data.folder
  const folderId      = currentFolder?.id ?? ''
  const totalPages    = Math.ceil(data.totalDocuments / data.pageSize)
  const isRestricted  = currentFolder?.restricted ?? false

  const filteredDocs = data.documents
    .filter((doc) => statusFilter === 'all' || doc.status === statusFilter)
    .filter((doc) => !tagFilter || doc.tags.includes(tagFilter))

  const allTags        = Array.from(new Set(data.documents.flatMap((d) => d.tags)))
  const hasActiveFilters = statusFilter !== 'all' || tagFilter !== null
  const allSelected    = filteredDocs.length > 0 && selected.size === filteredDocs.length

  return (
    <div className="flex h-full overflow-hidden bg-[#F4F4F4]">
      <div className="hidden md:block">
        <FolderTree onCreateFolder={() => setShowCreate(true)} />
      </div>

      <div className="flex-1 flex flex-col overflow-hidden">
        {/* ── Header ─────────────────────────────────────────────────────── */}
        <div className="bg-white border-b border-[#E8E8E8] px-4 sm:px-6 py-4">
          <nav className="flex items-center gap-1.5 text-sm text-[#888] mb-3">
            <Link to="/folders" className="hover:text-[#F5A800] transition-colors"><Home size={14} /></Link>
            {currentFolder && (
              <>
                <ChevronRight size={14} className="text-[#D0D0D0]" />
                <span className="text-[#1A1A1A] font-medium">{currentFolder.name}</span>
                {isRestricted && (
                  <span className="inline-flex items-center gap-1 ml-1 px-2 py-0.5 rounded-full bg-[#FFF8E6] text-[#F5A800] text-[11px] font-semibold">
                    <Lock size={10} /> Restreint
                  </span>
                )}
              </>
            )}
          </nav>

          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 bg-[#1A1A1A] rounded-lg flex items-center justify-center">
                <FolderOpen size={20} className="text-[#F5A800]" />
              </div>
              <div>
                <h1 className="text-lg font-black tracking-tight text-[#1A1A1A]">{currentFolder?.name ?? 'Documents'}</h1>
                <p className="text-xs text-[#888]">
                  {data.totalDocuments} document{data.totalDocuments !== 1 ? 's' : ''}
                  {data.subfolders.length > 0 && ` · ${data.subfolders.length} sous-dossier${data.subfolders.length !== 1 ? 's' : ''}`}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2 flex-wrap">
              {isAdmin && id && (
                <button onClick={() => setShowPerms(true)} title="Gérer les permissions"
                  className="p-1.5 rounded-lg transition-colors bg-[#141414] text-white hover:bg-[#2A2A2A]">
                  <Shield size={15} />
                </button>
              )}
              {id && (
                <>
                  <button onClick={() => setShowRename(true)} title="Renommer"
                    className="p-1.5 rounded-lg text-[#888] hover:text-[#F5A800] hover:bg-[#FFFBF0] transition-colors">
                    <Pencil size={15} />
                  </button>
                  <button onClick={() => setShowDelete(true)} title="Supprimer"
                    className="p-1.5 rounded-lg text-[#888] hover:text-red-600 hover:bg-red-50 transition-colors">
                    <Trash2 size={15} />
                  </button>
                </>
              )}

              {/* Bulk mode toggle */}
              <button
                onClick={() => { setBulkMode(!bulkMode); setSelected(new Set()) }}
                title="Sélection multiple"
                className={`p-1.5 rounded-lg transition-colors ${
                  bulkMode
                    ? 'bg-[#F5A800] text-[#1A1A1A]'
                    : 'text-[#888] hover:text-[#F5A800] hover:bg-[#FFFBF0]'
                }`}
              >
                <Layers size={15} />
              </button>

              <Button variant="secondary" size="sm" onClick={() => setShowFilters(!showFilters)}>
                <Filter size={15} />Filtrer
                {hasActiveFilters && <span className="w-2 h-2 bg-[#F5A800] rounded-full" />}
              </Button>
              <Button variant="secondary" size="sm" onClick={() => setShowCreate(true)}>
                <FolderPlus size={15} />Nouveau dossier
              </Button>
              {folderId && (
                <>
                  <a href={`/api/folders/${folderId}/export`} download
                    className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-[#333] bg-[#F5F5F5] border border-[#D0D0D0] hover:bg-[#EAEAEA] transition-all">
                    <Download size={15} />ZIP
                  </a>
                  <Button size="sm" onClick={() => setShowUpload(true)}>
                    <Upload size={15} />Importer
                  </Button>
                </>
              )}
            </div>
          </div>

          {/* Filter bar */}
          {showFilters && (
            <div className="mt-3 pt-3 border-t border-[#E8E8E8] flex items-center gap-3 flex-wrap">
              <select value={statusFilter}
                onChange={(e) => { setStatusFilter(e.target.value as DocumentStatus | 'all'); setPage(1) }}
                className="text-sm border border-[#E8E8E8] rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#F5A800] bg-white text-[#333]">
                {STATUS_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
              {allTags.length > 0 && (
                <div className="flex items-center gap-1.5 flex-wrap">
                  <span className="text-xs text-[#888]">Tags :</span>
                  {allTags.map((tag) => (
                    <button key={tag} onClick={() => setTagFilter(tagFilter === tag ? null : tag)}>
                      <TagBadge tag={tag}
                        className={`cursor-pointer transition-opacity ${tagFilter === tag ? 'ring-2 ring-offset-1 ring-current' : 'opacity-70 hover:opacity-100'}`} />
                    </button>
                  ))}
                </div>
              )}
              {hasActiveFilters && (
                <button onClick={() => { setStatusFilter('all'); setTagFilter(null) }}
                  className="flex items-center gap-1 text-xs text-red-500 hover:text-red-700 transition-colors ml-auto">
                  <X size={12} />Effacer les filtres
                </button>
              )}
            </div>
          )}
        </div>

        {/* ── Content ────────────────────────────────────────────────────── */}
        <div className="flex-1 overflow-y-auto p-4 sm:p-6">
          {data.subfolders.length > 0 && (
            <div className="mb-6">
              <div className="flex items-center gap-2 mb-3">
                <div className="w-1 h-5 bg-[#F5A800] rounded-full" />
                <h2 className="text-xs font-semibold text-[#555] uppercase tracking-widest">Sous-dossiers</h2>
              </div>
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                {data.subfolders.map((folder) => (
                  <Link key={folder.id} to={`/folders/${folder.id}`}
                    className="relative flex flex-col items-center gap-2 p-3 rounded-lg bg-white border border-[#E8E8E8] hover:border-[#F5A800] hover:bg-[#FFFBF0] transition-all group">
                    <div className="w-10 h-10 bg-[#1A1A1A] rounded-lg flex items-center justify-center group-hover:bg-[#2A2A2A] transition-colors">
                      <FolderOpen size={20} className="text-[#F5A800]" />
                    </div>
                    <span className="text-xs text-[#333] font-medium text-center leading-tight line-clamp-2">{folder.name}</span>
                    {folder.restricted && <Lock size={10} className="absolute top-2 right-2 text-[#F5A800]" />}
                  </Link>
                ))}
              </div>
            </div>
          )}

          {filteredDocs.length > 0 ? (
            <div>
              <div className="flex items-center gap-2 mb-3">
                <div className="w-1 h-5 bg-[#F5A800] rounded-full" />
                <h2 className="text-xs font-semibold text-[#555] uppercase tracking-widest">
                  Documents
                  {hasActiveFilters && <span className="ml-2 text-[#F5A800] normal-case font-normal">({filteredDocs.length} résultat{filteredDocs.length !== 1 ? 's' : ''})</span>}
                </h2>
                {bulkMode && (
                  <span className="ml-auto text-xs text-[#888]">
                    {selected.size} sélectionné{selected.size !== 1 ? 's' : ''}
                  </span>
                )}
              </div>
              <div className="bg-white rounded-lg border border-[#E8E8E8] overflow-hidden">
                <table className="w-full">
                  <thead>
                    <tr className="border-b border-[#E8E8E8] bg-[#F5F5F5]">
                      {bulkMode && (
                        <th className="pl-4 pr-2 py-3 w-8">
                          <button onClick={toggleSelectAll} className="text-[#888] hover:text-[#F5A800] transition-colors">
                            {allSelected
                              ? <CheckSquare size={16} className="text-[#F5A800]" />
                              : <Square size={16} />
                            }
                          </button>
                        </th>
                      )}
                      <th className="text-left px-4 py-3 text-xs font-semibold text-[#555] uppercase tracking-widest">Nom</th>
                      <th className="text-left px-4 py-3 text-xs font-semibold text-[#555] uppercase tracking-widest">Statut</th>
                      <th className="text-left px-4 py-3 text-xs font-semibold text-[#555] uppercase tracking-widest hidden md:table-cell">Taille</th>
                      <th className="text-left px-4 py-3 text-xs font-semibold text-[#555] uppercase tracking-widest hidden lg:table-cell">Modifié</th>
                      <th className="px-4 py-3" />
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[#E8E8E8]">
                    {filteredDocs.map((doc) => (
                      <DocumentRow
                        key={doc.id}
                        doc={doc}
                        folderId={folderId}
                        selected={selected.has(doc.id)}
                        onToggle={toggleSelect}
                        bulkMode={bulkMode}
                        onPreview={() => setPreviewDoc(doc)}
                      />
                    ))}
                  </tbody>
                </table>
              </div>
              {totalPages > 1 && (
                <Pagination currentPage={page} totalPages={totalPages} onPageChange={setPage} className="mt-4" />
              )}
            </div>
          ) : (
            <div className="flex flex-col items-center justify-center py-20 text-center">
              <div className="w-16 h-16 bg-[#F5F5F5] rounded-lg border border-[#E8E8E8] flex items-center justify-center mb-4">
                <Upload size={28} className="text-[#888]" />
              </div>
              {hasActiveFilters ? (
                <>
                  <p className="text-[#333] font-semibold">Aucun document pour ces filtres</p>
                  <button onClick={() => { setStatusFilter('all'); setTagFilter(null) }}
                    className="mt-2 text-sm text-[#F5A800] hover:underline">Effacer les filtres</button>
                </>
              ) : (
                <>
                  <p className="text-[#333] font-semibold">Aucun document</p>
                  <p className="text-[#888] text-sm mt-1">Importez votre premier fichier</p>
                  {folderId && (
                    <Button className="mt-4" size="sm" onClick={() => setShowUpload(true)}>
                      <Upload size={15} />Importer un document
                    </Button>
                  )}
                </>
              )}
            </div>
          )}
        </div>

        {/* ── Bulk action bar (floating) ──────────────────────────────────── */}
        {bulkMode && selected.size > 0 && (
          <div className="border-t border-[#E8E8E8] bg-[#1A1A1A] px-4 py-3 flex items-center gap-3 flex-wrap">
            <span className="text-sm font-semibold text-white">
              {selected.size} document{selected.size !== 1 ? 's' : ''} sélectionné{selected.size !== 1 ? 's' : ''}
            </span>
            <div className="flex items-center gap-2 ml-auto flex-wrap">
              <button
                onClick={handleBulkExport}
                disabled={bulkExporting}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold bg-[#2A2A2A] text-white hover:bg-[#333] border border-white/10 transition-colors disabled:opacity-50"
              >
                <Archive size={13} />
                {bulkExporting ? 'Export…' : 'Télécharger ZIP'}
              </button>
              <button
                onClick={() => setShowBulkMove(true)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold bg-[#2A2A2A] text-white hover:bg-[#333] border border-white/10 transition-colors"
              >
                <MoveRight size={13} />
                Déplacer
              </button>
              <button
                onClick={() => setShowBulkDelete(true)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold bg-red-600 text-white hover:bg-red-700 transition-colors"
              >
                <Trash2 size={13} />
                Supprimer
              </button>
              <button
                onClick={exitBulkMode}
                className="p-1.5 rounded-md text-gray-400 hover:text-white transition-colors"
              >
                <X size={15} />
              </button>
            </div>
          </div>
        )}

        {/* Bulk mode hint bar */}
        {bulkMode && selected.size === 0 && (
          <div className="border-t border-[#E8E8E8] bg-[#1A1A1A] px-4 py-2.5 flex items-center justify-between">
            <span className="text-xs text-gray-500">Cliquez sur les lignes pour sélectionner des documents</span>
            <button onClick={exitBulkMode} className="text-xs text-gray-500 hover:text-white transition-colors flex items-center gap-1">
              <X size={12} />Quitter la sélection
            </button>
          </div>
        )}
      </div>

      {/* ── Modals ─────────────────────────────────────────────────────────── */}
      {folderId && (
        <>
          <UploadModal open={showUpload} onClose={() => setShowUpload(false)} folderId={folderId} />
          <CreateFolderModal open={showCreate} onClose={() => setShowCreate(false)} parentId={folderId} />
        </>
      )}
      {!folderId && <CreateFolderModal open={showCreate} onClose={() => setShowCreate(false)} />}
      {id && currentFolder && (
        <>
          <RenameFolderModal open={showRename} onClose={() => setShowRename(false)}
            currentName={currentFolder.name} onConfirm={(name) => renameMutation.mutate(name)}
            loading={renameMutation.isPending} />
          <ConfirmModal open={showDelete} onClose={() => setShowDelete(false)}
            onConfirm={() => deleteMutation.mutate()} loading={deleteMutation.isPending}
            title="Supprimer le dossier"
            message={`Supprimer "${currentFolder.name}" ? Le dossier doit être vide. Cette action est irréversible.`}
            confirmLabel="Supprimer" />
        </>
      )}

      {showPerms && id && currentFolder && (
        <PermissionsModal folderId={id} folderName={currentFolder.name} onClose={() => setShowPerms(false)} />
      )}

      {/* Quick preview modal */}
      {previewDoc && (
        <DocumentPreviewModal
          doc={previewDoc}
          open={!!previewDoc}
          onClose={() => setPreviewDoc(null)}
        />
      )}

      {/* Bulk move modal */}
      <MoveDocumentModal
        open={showBulkMove}
        currentFolderId={folderId}
        onClose={() => setShowBulkMove(false)}
        onConfirm={(targetFolderId) => handleBulkMove(targetFolderId)}
      />

      {/* Bulk delete confirm */}
      <ConfirmModal
        open={showBulkDelete}
        onClose={() => setShowBulkDelete(false)}
        onConfirm={handleBulkDelete}
        loading={false}
        title="Supprimer les documents sélectionnés"
        message={`Supprimer ${selected.size} document${selected.size !== 1 ? 's' : ''} ? Cette action est irréversible.`}
        confirmLabel={`Supprimer ${selected.size} document${selected.size !== 1 ? 's' : ''}`}
      />
    </div>
  )
}
