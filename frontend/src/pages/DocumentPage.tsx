import { useState, useRef } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  Download, Trash2, Send, CheckCircle, XCircle, Clock, History,
  Upload, FileText, ChevronRight, Home, Eye, Tag, Pencil, FolderOpen,
  FileSignature, MoreHorizontal, FileImage, FileSpreadsheet, FileArchive,
  CheckCircle2, AlertCircle, RotateCcw, PenLine, Star, GitCompare,
} from 'lucide-react'
import {
  getDocument, submitForReview, approveDocument, rejectDocument,
  deleteDocument, downloadUrl, addVersion, updateDocumentTags,
  renameDocument, moveDocument, addFavorite, removeFavorite,
} from '@/api/documents'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { TagEditor } from '@/components/ui/TagEditor'
import { ConfirmModal } from '@/components/ui/ConfirmModal'
import { PageSpinner } from '@/components/ui/Spinner'
import { DocumentPreviewModal } from '@/features/documents/DocumentPreviewModal'
import { VersionDiffModal } from '@/features/documents/VersionDiffModal'
import { MoveDocumentModal } from '@/features/documents/MoveDocumentModal'
import { DocumentCommentSection } from '@/features/documents/DocumentCommentSection'
import { RequestSignatureModal } from '@/features/signatures/RequestSignatureModal'
import { PlaceSignatureModal } from '@/features/signatures/PlaceSignatureModal'
import { downloadAuthenticatedFile } from '@/lib/download'
import { useAuthStore } from '@/store/auth'
import { formatBytes, formatDate, STATUS_LABELS } from '@/lib/utils'
import { usePageTitle } from '@/hooks/usePageTitle'
import type { DocumentStatus } from '@/types'

// ── Helpers ──────────────────────────────────────────────────────────────────

function getMimeIcon(mime: string) {
  if (mime.startsWith('image/'))
    return { icon: <FileImage size={28} /> }
  if (mime === 'application/pdf')
    return { icon: <FileText size={28} /> }
  if (mime.includes('spreadsheet') || mime.includes('excel'))
    return { icon: <FileSpreadsheet size={28} /> }
  if (mime.includes('zip') || mime.includes('rar'))
    return { icon: <FileArchive size={28} /> }
  return { icon: <FileText size={28} /> }
}

const WORKFLOW_STEPS: { key: DocumentStatus; label: string }[] = [
  { key: 'draft',          label: 'Brouillon' },
  { key: 'pending_review', label: 'En révision' },
  { key: 'approved',       label: 'Approuvé' },
]

const STATUS_STEP: Record<DocumentStatus, number> = {
  draft:          0,
  pending_review: 1,
  approved:       2,
  rejected:       1,
  archived:       2,
}

// ── Sub-components ────────────────────────────────────────────────────────────

function RejectModal({ open, onClose, onConfirm, loading }: {
  open: boolean; onClose: () => void; onConfirm: (reason: string) => void; loading: boolean
}) {
  const [reason, setReason] = useState('')
  return (
    <Modal open={open} onClose={onClose} title="Rejeter le document">
      <div className="space-y-4">
        <label className="block text-sm font-medium text-[#333] mb-1.5">Motif du rejet</label>
        <textarea
          autoFocus
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          rows={3}
          className="w-full px-3.5 py-2.5 rounded-lg border border-[#E8E8E8] text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"
          placeholder="Expliquez pourquoi le document est rejeté…"
        />
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>Annuler</Button>
          <Button variant="danger" loading={loading} onClick={() => onConfirm(reason)}>Rejeter</Button>
        </div>
      </div>
    </Modal>
  )
}

function RenameModal({ open, onClose, onConfirm, loading, currentTitle }: {
  open: boolean; onClose: () => void; onConfirm: (title: string) => void; loading: boolean; currentTitle: string
}) {
  const [title, setTitle] = useState(currentTitle)
  return (
    <Modal open={open} onClose={onClose} title="Renommer le document">
      <div className="space-y-4">
        <input
          autoFocus
          type="text"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          className="w-full px-3.5 py-2.5 rounded-lg border border-[#E8E8E8] text-sm focus:outline-none focus:ring-2 focus:ring-[#F5A800]"
          placeholder="Titre du document"
          maxLength={255}
        />
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>Annuler</Button>
          <Button
            loading={loading}
            disabled={!title.trim() || title === currentTitle}
            onClick={() => onConfirm(title)}
          >
            Renommer
          </Button>
        </div>
      </div>
    </Modal>
  )
}

// ── Dropdown "Plus d'actions" ─────────────────────────────────────────────────

function ActionsDropdown({ onRename, onMove, onDelete, loading }: {
  onRename: () => void; onMove: () => void; onDelete: () => void; loading: boolean
}) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  // Fermeture au clic extérieur
  useState(() => {
    function handle(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', handle)
    return () => document.removeEventListener('mousedown', handle)
  })

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen(!open)}
        className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-[#555] bg-white border border-[#E8E8E8] hover:border-[#D0D0D0] hover:bg-[#F5F5F5] transition-all shadow-sm"
      >
        <MoreHorizontal size={16} />
        <span className="hidden sm:inline">Plus</span>
      </button>
      {open && (
        <div className="absolute right-0 top-full mt-1.5 w-48 bg-white rounded-lg shadow-xl border border-[#E8E8E8] py-1 z-20">
          <button
            onClick={() => { onRename(); setOpen(false) }}
            className="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-[#333] hover:bg-[#F5F5F5] transition-colors"
          >
            <Pencil size={15} className="text-[#AAA]" />Renommer
          </button>
          <button
            onClick={() => { onMove(); setOpen(false) }}
            className="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-[#333] hover:bg-[#F5F5F5] transition-colors"
          >
            <FolderOpen size={15} className="text-[#AAA]" />Déplacer
          </button>
          <div className="mx-3 my-1 border-t border-[#E8E8E8]" />
          <button
            onClick={() => { onDelete(); setOpen(false) }}
            disabled={loading}
            className="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors disabled:opacity-50"
          >
            <Trash2 size={15} />Supprimer
          </button>
        </div>
      )}
    </div>
  )
}

// ── Status badge helper ───────────────────────────────────────────────────────

function StatusBadge({ status }: { status: DocumentStatus }) {
  const base = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold shrink-0 border'
  const variants: Record<DocumentStatus, string> = {
    approved:       'bg-green-50 border-green-200 text-green-700',
    rejected:       'bg-red-50 border-red-200 text-red-700',
    pending_review: 'bg-[#FFF3CC] border-[#F5D580] text-[#A07000]',
    draft:          'bg-[#F5F5F5] border-[#E0E0E0] text-[#555]',
    archived:       'bg-[#F5F5F5] border-[#E0E0E0] text-[#555]',
  }
  return (
    <span className={`${base} ${variants[status]}`}>
      {status === 'approved'       && <CheckCircle2 size={14} />}
      {status === 'rejected'       && <XCircle size={14} />}
      {status === 'pending_review' && <Clock size={14} />}
      {STATUS_LABELS[status]}
    </span>
  )
}

// ── Section title helper ──────────────────────────────────────────────────────

function SectionTitle({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex items-center gap-2">
      <div className="w-1 h-5 bg-[#F5A800] rounded-full" />
      <h2 className="text-xs font-bold text-[#555] uppercase tracking-widest">{children}</h2>
    </div>
  )
}

// ── Main page ─────────────────────────────────────────────────────────────────

export function DocumentPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const isAdmin = useAuthStore((s) => s.isAdmin)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const [showReject, setShowReject] = useState(false)
  const [showPreview, setShowPreview] = useState(false)
  const [showDiff, setShowDiff]       = useState(false)
  const [showRename, setShowRename] = useState(false)
  const [showMove, setShowMove] = useState(false)
  const [showDelete, setShowDelete] = useState(false)
  const [showSignatureRequest, setShowSignatureRequest] = useState(false)
  const [showPlaceSignature, setShowPlaceSignature]     = useState(false)
  const [editingTags, setEditingTags] = useState(false)
  const [localTags, setLocalTags] = useState<string[]>([])

  const { data: doc, isLoading } = useQuery({
    queryKey: ['document', id],
    queryFn: () => getDocument(id!),
    enabled: !!id,
  })

  usePageTitle(doc?.title ?? 'Document')

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['document', id] })
    if (doc?.folderId) qc.invalidateQueries({ queryKey: ['folder', doc.folderId] })
  }

  const submitMutation  = useMutation({ mutationFn: () => submitForReview(id!),
    onSuccess: () => { invalidate(); toast.success('Document soumis pour révision.') },
    onError: () => toast.error('Impossible de soumettre le document.') })
  const approveMutation = useMutation({ mutationFn: () => approveDocument(id!),
    onSuccess: () => { invalidate(); toast.success('Document approuvé ✓') },
    onError: () => toast.error('Impossible d\'approuver le document.') })
  const rejectMutation  = useMutation({ mutationFn: (reason: string) => rejectDocument(id!, reason),
    onSuccess: () => { setShowReject(false); invalidate(); toast.success('Document rejeté.') },
    onError: () => toast.error('Impossible de rejeter le document.') })
  const deleteMutation  = useMutation({ mutationFn: () => deleteDocument(id!),
    onSuccess: () => {
      setShowDelete(false)
      toast.success(`"${doc?.title}" supprimé.`)
      doc?.folderId ? navigate(`/folders/${doc.folderId}`) : navigate('/folders')
    },
    onError: () => toast.error('Impossible de supprimer le document.') })
  const renameMutation  = useMutation({ mutationFn: (title: string) => renameDocument(id!, title),
    onSuccess: () => { setShowRename(false); invalidate(); toast.success('Document renommé.') },
    onError: () => toast.error('Impossible de renommer le document.') })
  const moveMutation    = useMutation({ mutationFn: (folderId: string) => moveDocument(id!, folderId),
    onSuccess: () => { setShowMove(false); invalidate(); toast.success('Document déplacé.') },
    onError: () => toast.error('Impossible de déplacer le document.') })
  const tagsMutation    = useMutation({ mutationFn: (tags: string[]) => updateDocumentTags(id!, tags),
    onSuccess: () => { setEditingTags(false); invalidate(); toast.success('Tags mis à jour.') },
    onError: () => toast.error('Impossible de mettre à jour les tags.') })
  const favMutation     = useMutation({
    mutationFn: () => doc?.isFavorite ? removeFavorite(id!) : addFavorite(id!),
    onSuccess: () => { invalidate(); qc.invalidateQueries({ queryKey: ['favorites'] }) },
    onError: () => toast.error('Impossible de modifier les favoris.'),
  })

  const handleAddVersion = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file || !id) return
    addVersion(id, file)
      .then(() => { invalidate(); toast.success('Nouvelle version ajoutée.') })
      .catch(() => toast.error('Impossible d\'ajouter la version.'))
    e.target.value = ''
  }

  if (isLoading) return <PageSpinner />
  if (!doc) return (
    <div className="flex flex-col items-center justify-center h-full py-20">
      <AlertCircle size={40} className="text-red-400 mb-3" />
      <p className="text-[#555] font-medium">Document introuvable</p>
    </div>
  )

  const mimeStyle   = getMimeIcon(doc.mimeType)
  const currentStep = STATUS_STEP[doc.status]

  // Hero stripe color
  const heroStripe =
    doc.status === 'approved'       ? 'bg-green-500'
    : doc.status === 'rejected'     ? 'bg-red-500'
    : doc.status === 'pending_review' ? 'bg-[#F5A800]'
    : 'bg-[#D0D0D0]'

  return (
    <div className="min-h-full bg-[#F4F4F4]">

      {/* ── Top breadcrumb bar ──────────────────────────────────────────── */}
      <div className="bg-white border-b border-[#E8E8E8] px-4 sm:px-6 py-3">
        <nav className="flex items-center gap-1.5 text-sm text-[#888]">
          <Link to="/folders" className="hover:text-[#F5A800] transition-colors">
            <Home size={14} />
          </Link>
          <ChevronRight size={13} className="text-[#D0D0D0]" />
          {doc.folderId && (
            <>
              <Link to={`/folders/${doc.folderId}`} className="hover:text-[#F5A800] transition-colors">
                Dossier
              </Link>
              <ChevronRight size={13} className="text-[#D0D0D0]" />
            </>
          )}
          <span className="text-[#1A1A1A] font-medium truncate max-w-xs">{doc.title}</span>
        </nav>
      </div>

      <div className="max-w-5xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-5">

        {/* ── Hero card ─────────────────────────────────────────────────── */}
        <div className="bg-white rounded-lg border border-[#E8E8E8] shadow-sm overflow-hidden">
          {/* Colored top stripe */}
          <div className={`h-1 w-full ${heroStripe}`} />

          <div className="p-6">
            <div className="flex items-start gap-5">
              {/* File type icon */}
              <div className="w-16 h-16 bg-[#1A1A1A] rounded-lg flex items-center justify-center shrink-0">
                <span className="text-[#F5A800]">{mimeStyle.icon}</span>
              </div>

              {/* Title + meta */}
              <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-4 flex-wrap">
                  <div>
                    <h1 className="text-xl font-black tracking-tight text-[#1A1A1A] leading-tight">
                      {doc.title}
                    </h1>
                    <p className="text-sm text-[#AAA] mt-0.5">{doc.originalFilename}</p>
                  </div>
                  <StatusBadge status={doc.status} />
                </div>

                {/* File details row */}
                <div className="flex items-center gap-3 mt-3 flex-wrap">
                  <span className="bg-[#F5F5F5] text-[#666] text-xs px-2.5 py-1 rounded-md">
                    {formatBytes(doc.fileSizeBytes)}
                  </span>
                  <span className="bg-[#F5F5F5] text-[#666] text-xs px-2.5 py-1 rounded-md">
                    {doc.mimeType}
                  </span>
                  <span className="text-xs text-[#AAA]">Modifié {formatDate(doc.updatedAt)}</span>
                </div>
              </div>
            </div>

            {/* Actions toolbar */}
            <div className="flex items-center gap-2 mt-5 pt-5 border-t border-[#F0F0F0] flex-wrap">
              <Button size="sm" variant="secondary" onClick={() => setShowPreview(true)}>
                <Eye size={15} />Aperçu
              </Button>
              <Button
                size="sm"
                variant="secondary"
                onClick={() => downloadAuthenticatedFile(downloadUrl(doc.id), doc.originalFilename)}
              >
                <Download size={15} />Télécharger
              </Button>
              <button
                onClick={() => favMutation.mutate()}
                disabled={favMutation.isPending}
                className={`flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border transition-all shadow-sm disabled:opacity-50 ${
                  doc.isFavorite
                    ? 'bg-[#FFF3CC] border-[#F5D580] text-[#A07000] hover:bg-[#FFE999]'
                    : 'bg-white border-[#E8E8E8] text-[#555] hover:border-[#D0D0D0] hover:bg-[#F5F5F5]'
                }`}
                title={doc.isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'}
              >
                <Star
                  size={15}
                  className={doc.isFavorite ? 'fill-[#F5A800] text-[#F5A800]' : ''}
                />
                <span className="hidden sm:inline">{doc.isFavorite ? 'Favori' : 'Favoris'}</span>
              </button>
              <Button size="sm" variant="secondary" onClick={() => setShowSignatureRequest(true)}>
                <FileSignature size={15} />Demander une signature
              </Button>
              {doc.mimeType === 'application/pdf' && (
                <Button size="sm" onClick={() => setShowPlaceSignature(true)}>
                  <PenLine size={15} />Signer
                </Button>
              )}
              <div className="ml-auto">
                <ActionsDropdown
                  onRename={() => setShowRename(true)}
                  onMove={() => setShowMove(true)}
                  onDelete={() => setShowDelete(true)}
                  loading={deleteMutation.isPending}
                />
              </div>
            </div>
          </div>
        </div>

        {/* ── Two-column layout ──────────────────────────────────────────── */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">

          {/* Left column — workflow + versions + comments */}
          <div className="lg:col-span-2 space-y-5">

            {/* Workflow card */}
            <div className="bg-white rounded-lg border border-[#E8E8E8] shadow-sm overflow-hidden">
              <div className="px-5 py-4 border-b border-[#E8E8E8] flex items-center gap-3">
                <SectionTitle>Circuit d'approbation</SectionTitle>
              </div>
              <div className="p-5">
                {/* Step indicator */}
                <div className="flex items-center gap-0 mb-6">
                  {WORKFLOW_STEPS.map((step, i) => {
                    const isDone     = currentStep > i || (doc.status === 'approved' && i <= 2)
                    const isCurrent  = doc.status !== 'rejected' && currentStep === i
                    const isRejected = doc.status === 'rejected' && i === 1

                    return (
                      <div key={step.key} className="flex items-center flex-1">
                        <div className="flex flex-col items-center flex-1">
                          <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all ${
                            isRejected  ? 'bg-red-500 text-white'
                            : isDone    ? 'bg-[#1A1A1A] text-white'
                            : isCurrent ? 'bg-[#F5A800] text-[#1A1A1A] ring-2 ring-[#F5A800] ring-offset-1'
                            : 'bg-[#E8E8E8] text-[#AAA]'
                          }`}>
                            {isRejected ? <XCircle size={16} /> : isDone ? <CheckCircle2 size={16} /> : i + 1}
                          </div>
                          <span className={`text-xs mt-1.5 font-medium ${
                            isRejected  ? 'text-red-600'
                            : isDone    ? 'text-[#1A1A1A]'
                            : isCurrent ? 'text-[#A07000]'
                            : 'text-[#AAA]'
                          }`}>
                            {isRejected ? 'Rejeté' : step.label}
                          </span>
                        </div>
                        {i < WORKFLOW_STEPS.length - 1 && (
                          <div className={`h-0.5 flex-1 mx-1 rounded-full mb-5 ${
                            currentStep > i && doc.status !== 'rejected'
                              ? 'bg-[#1A1A1A]'
                              : 'bg-[#E8E8E8]'
                          }`} />
                        )}
                      </div>
                    )
                  })}
                </div>

                {/* Action buttons */}
                <div className="flex items-center gap-2 flex-wrap">
                  {/* Utilisateur non-admin */}
                  {!isAdmin && doc.status === 'draft' && (
                    <Button size="sm" onClick={() => submitMutation.mutate()} loading={submitMutation.isPending}>
                      <Send size={14} />Soumettre pour révision
                    </Button>
                  )}
                  {!isAdmin && doc.status === 'rejected' && (
                    <Button size="sm" onClick={() => submitMutation.mutate()} loading={submitMutation.isPending}>
                      <RotateCcw size={14} />Resoumettre
                    </Button>
                  )}
                  {!isAdmin && doc.status === 'pending_review' && (
                    <p className="text-sm text-[#A07000] bg-[#FFF3CC] px-3 py-2 rounded-lg border border-[#F5D580] flex items-center gap-2">
                      <Clock size={14} />En attente de validation par un administrateur
                    </p>
                  )}

                  {/* Admin — uniquement pour les docs soumis par des utilisateurs */}
                  {isAdmin && doc.status === 'pending_review' && (
                    <>
                      <Button
                        size="sm"
                        className="bg-green-600 hover:bg-green-700 text-white shadow-sm"
                        onClick={() => approveMutation.mutate()}
                        loading={approveMutation.isPending}
                      >
                        <CheckCircle size={14} />Approuver
                      </Button>
                      <Button variant="danger" size="sm" onClick={() => setShowReject(true)}>
                        <XCircle size={14} />Rejeter
                      </Button>
                    </>
                  )}

                  {doc.status === 'approved' && (
                    <p className="text-sm text-green-700 bg-green-50 px-3 py-2 rounded-lg border border-green-100 flex items-center gap-2">
                      <CheckCircle2 size={14} />Ce document a été approuvé
                    </p>
                  )}
                </div>
              </div>
            </div>

            {/* Versions card */}
            <div className="bg-white rounded-lg border border-[#E8E8E8] shadow-sm overflow-hidden">
              <div className="px-5 py-4 border-b border-[#E8E8E8] flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <SectionTitle>Historique des versions</SectionTitle>
                  {doc.versions && doc.versions.length > 0 && (
                    <span className="bg-[#1A1A1A] text-white text-xs font-semibold px-2 py-0.5 rounded-full">
                      {doc.versions.length}
                    </span>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  {(doc.versions?.length ?? 0) >= 2 && (
                    <Button variant="secondary" size="sm" onClick={() => setShowDiff(true)}>
                      <GitCompare size={14} />Comparer
                    </Button>
                  )}
                  <label className="cursor-pointer">
                    <input ref={fileInputRef} type="file" className="hidden" onChange={handleAddVersion} />
                    <Button variant="secondary" size="sm" onClick={() => fileInputRef.current?.click()}>
                      <Upload size={14} />Nouvelle version
                    </Button>
                  </label>
                </div>
              </div>
              <div className="p-4">
                {doc.versions && doc.versions.length > 0 ? (
                  <div className="space-y-2">
                    {[...doc.versions].reverse().map((v, i) => (
                      <div
                        key={v.id}
                        className={`flex items-center justify-between p-3.5 rounded-lg transition-colors group ${
                          i === 0
                            ? 'bg-[#FFF8E7] border border-[#F5D580]'
                            : 'bg-[#FAFAFA] hover:bg-[#F5F5F5] border border-transparent'
                        }`}
                      >
                        <div className="flex items-center gap-3">
                          <div className={`w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold shrink-0 ${
                            i === 0
                              ? 'bg-[#1A1A1A] text-white'
                              : 'bg-white border border-[#E0E0E0] text-[#555]'
                          }`}>
                            v{v.versionNumber}
                          </div>
                          <div>
                            <p className="text-sm font-medium text-[#1A1A1A] flex items-center gap-2">
                              {v.originalFilename}
                              {i === 0 && (
                                <span className="text-[10px] bg-[#1A1A1A] text-white px-1.5 py-0.5 rounded-full font-semibold">
                                  actuelle
                                </span>
                              )}
                            </p>
                            <p className="text-xs text-[#AAA] mt-0.5">
                              {formatBytes(v.fileSizeBytes)} · {formatDate(v.uploadedAt)}
                            </p>
                            {v.comment && (
                              <p className="text-xs text-[#666] mt-0.5 italic">{v.comment}</p>
                            )}
                          </div>
                        </div>
                        <button
                          onClick={() =>
                            downloadAuthenticatedFile(downloadUrl(doc.id, v.versionNumber), v.originalFilename)
                          }
                          className="p-2 rounded-lg text-[#AAA] hover:text-[#F5A800] hover:bg-white transition-all opacity-0 group-hover:opacity-100"
                          title="Télécharger cette version"
                        >
                          <Download size={15} />
                        </button>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-8 text-[#AAA]">
                    <History size={28} className="mx-auto mb-2 text-[#D0D0D0]" />
                    <p className="text-sm">Aucune version disponible</p>
                  </div>
                )}
              </div>
            </div>

            {/* Comments */}
            <DocumentCommentSection documentId={id!} />
          </div>

          {/* Right sidebar — details + tags */}
          <div className="space-y-5">

            {/* Details card */}
            <div className="bg-white rounded-lg border border-[#E8E8E8] shadow-sm overflow-hidden">
              <div className="px-5 py-4 border-b border-[#E8E8E8]">
                <SectionTitle>Détails</SectionTitle>
              </div>
              <div className="p-5 space-y-4">
                <div>
                  <p className="text-[10px] font-bold text-[#AAA] uppercase tracking-widest mb-1">Format</p>
                  <p className="text-sm text-[#333] font-medium">
                    {doc.mimeType.split('/')[1]?.toUpperCase() ?? doc.mimeType}
                  </p>
                </div>
                <div>
                  <p className="text-[10px] font-bold text-[#AAA] uppercase tracking-widest mb-1">Taille</p>
                  <p className="text-sm text-[#333] font-medium">{formatBytes(doc.fileSizeBytes)}</p>
                </div>
                <div>
                  <p className="text-[10px] font-bold text-[#AAA] uppercase tracking-widest mb-1">Créé le</p>
                  <p className="text-sm text-[#333] font-medium">{formatDate(doc.createdAt)}</p>
                </div>
                <div>
                  <p className="text-[10px] font-bold text-[#AAA] uppercase tracking-widest mb-1">Modifié le</p>
                  <p className="text-sm text-[#333] font-medium">{formatDate(doc.updatedAt)}</p>
                </div>
                {doc.comment && (
                  <div>
                    <p className="text-[10px] font-bold text-[#AAA] uppercase tracking-widest mb-1">Note</p>
                    <p className="text-sm text-[#555] italic">"{doc.comment}"</p>
                  </div>
                )}
              </div>
            </div>

            {/* Tags card */}
            <div className="bg-white rounded-lg border border-[#E8E8E8] shadow-sm overflow-hidden">
              <div className="px-5 py-4 border-b border-[#E8E8E8] flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Tag size={15} className="text-[#F5A800]" />
                  <SectionTitle>Tags</SectionTitle>
                </div>
                {!editingTags && (
                  <button
                    onClick={() => { setLocalTags(doc.tags ?? []); setEditingTags(true) }}
                    className="text-xs text-[#F5A800] hover:text-[#D48F00] font-medium transition-colors"
                  >
                    {(doc.tags?.length ?? 0) === 0 ? '+ Ajouter' : 'Modifier'}
                  </button>
                )}
              </div>
              <div className="p-5">
                {editingTags ? (
                  <div className="space-y-3">
                    <TagEditor tags={localTags} onChange={setLocalTags} disabled={tagsMutation.isPending} />
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        onClick={() => tagsMutation.mutate(localTags)}
                        loading={tagsMutation.isPending}
                        className="flex-1"
                      >
                        Enregistrer
                      </Button>
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => setEditingTags(false)}
                        className="flex-1"
                      >
                        Annuler
                      </Button>
                    </div>
                  </div>
                ) : (doc.tags?.length ?? 0) > 0 ? (
                  <div className="flex flex-wrap gap-1.5">
                    {(doc.tags ?? []).map((tag) => (
                      <span
                        key={tag}
                        className="bg-[#F5F5F5] border border-[#E0E0E0] text-[#555] text-xs px-2 py-0.5 rounded-md"
                      >
                        {tag}
                      </span>
                    ))}
                  </div>
                ) : (
                  <button
                    onClick={() => { setLocalTags([]); setEditingTags(true) }}
                    className="w-full text-center text-sm text-[#AAA] py-4 border-2 border-dashed border-[#E8E8E8] rounded-lg hover:border-[#F5A800] hover:text-[#F5A800] transition-colors"
                  >
                    + Ajouter des tags
                  </button>
                )}
              </div>
            </div>

          </div>
        </div>
      </div>

      {/* ── Modals ──────────────────────────────────────────────────────── */}
      <RejectModal
        open={showReject}
        onClose={() => setShowReject(false)}
        onConfirm={(reason) => rejectMutation.mutate(reason)}
        loading={rejectMutation.isPending}
      />
      <RenameModal
        open={showRename}
        onClose={() => setShowRename(false)}
        currentTitle={doc.title}
        onConfirm={(title) => renameMutation.mutate(title)}
        loading={renameMutation.isPending}
      />
      <MoveDocumentModal
        open={showMove}
        onClose={() => setShowMove(false)}
        currentFolderId={doc.folderId}
        onConfirm={(fId) => moveMutation.mutate(fId)}
        loading={moveMutation.isPending}
      />
      <ConfirmModal
        open={showDelete}
        onClose={() => setShowDelete(false)}
        onConfirm={() => deleteMutation.mutate()}
        loading={deleteMutation.isPending}
        title="Supprimer le document"
        message={`Êtes-vous sûr de vouloir supprimer "${doc.title}" ? Cette action est irréversible.`}
        confirmLabel="Supprimer"
      />
      <DocumentPreviewModal doc={doc} open={showPreview} onClose={() => setShowPreview(false)} />
      <VersionDiffModal doc={doc} open={showDiff} onClose={() => setShowDiff(false)} />
      <RequestSignatureModal
        open={showSignatureRequest}
        onClose={() => setShowSignatureRequest(false)}
        documentId={doc.id}
        documentTitle={doc.title}
      />
      <PlaceSignatureModal
        open={showPlaceSignature}
        onClose={() => setShowPlaceSignature(false)}
        doc={doc}
        onSigned={invalidate}
      />
    </div>
  )
}
