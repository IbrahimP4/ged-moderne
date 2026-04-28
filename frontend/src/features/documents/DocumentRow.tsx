import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { FileText, FileImage, FileVideo, File, Trash2, Download, Eye, ExternalLink } from 'lucide-react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { deleteDocument, downloadUrl } from '@/api/documents'
import { Badge } from '@/components/ui/Badge'
import { TagBadge } from '@/components/ui/TagBadge'
import { ConfirmModal } from '@/components/ui/ConfirmModal'
import { downloadAuthenticatedFile } from '@/lib/download'
import { formatBytes, formatDate, STATUS_LABELS, STATUS_COLORS } from '@/lib/utils'
import { cn } from '@/lib/utils'
import type { DocumentDTO } from '@/types'

function FileIcon({ mimeType }: { mimeType: string }) {
  if (mimeType.startsWith('image/')) return <FileImage size={18} className="text-violet-500" />
  if (mimeType.startsWith('video/')) return <FileVideo size={18} className="text-pink-500" />
  if (mimeType === 'application/pdf') return <FileText size={18} className="text-red-500" />
  return <File size={18} className="text-muted" />
}

interface Props {
  doc: DocumentDTO
  folderId: string
  selected?: boolean
  onToggle?: (id: string) => void
  bulkMode?: boolean
  onPreview?: () => void
}

export function DocumentRow({ doc, folderId, selected = false, onToggle, bulkMode = false, onPreview }: Props) {
  const navigate = useNavigate()
  const qc = useQueryClient()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const deleteMutation = useMutation({
    mutationFn: () => deleteDocument(doc.id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['folder', folderId] })
      toast.success(`"${doc.title}" supprimé.`)
    },
    onError: () => toast.error('Impossible de supprimer le document.'),
  })

  const handleRowClick = () => {
    if (bulkMode && onToggle) {
      onToggle(doc.id)
    } else if (onPreview) {
      // Aperçu rapide depuis la liste de dossier
      onPreview()
    } else {
      navigate(`/documents/${doc.id}`)
    }
  }

  return (
    <>
      <tr
        className={cn(
          'group cursor-pointer transition-colors border-l-2',
          selected
            ? 'bg-[#FFFBF0] border-[#F5A800]'
            : 'hover:bg-[#FFFBF0] border-transparent hover:border-[#F5A800]',
        )}
        onClick={handleRowClick}
      >
        {/* Checkbox column */}
        {bulkMode && (
          <td className="pl-4 pr-2 py-3 w-8" onClick={(e) => e.stopPropagation()}>
            <input
              type="checkbox"
              checked={selected}
              onChange={() => onToggle?.(doc.id)}
              className="w-4 h-4 rounded border-strong accent-[#F5A800] cursor-pointer"
            />
          </td>
        )}

        <td className="px-4 py-3">
          <div className="flex items-center gap-3">
            <div className={cn(
              'w-8 h-8 rounded-md flex items-center justify-center shrink-0 transition-colors',
              selected ? 'bg-[#FFF3CC]' : 'bg-muted group-hover:bg-card',
            )}>
              <FileIcon mimeType={doc.mimeType} />
            </div>
            <div className="min-w-0">
              <p className="text-sm font-medium text-primary truncate">{doc.title}</p>
              <div className="flex items-center gap-1.5 flex-wrap mt-0.5">
                <p className="text-xs text-muted truncate">{doc.originalFilename}</p>
                {doc.tags.slice(0, 2).map((tag) => (
                  <TagBadge key={tag} tag={tag} className="text-[10px] py-0 px-1.5" />
                ))}
                {doc.tags.length > 2 && (
                  <span className="text-[10px] text-muted">+{doc.tags.length - 2}</span>
                )}
              </div>
            </div>
          </div>
        </td>
        <td className="px-4 py-3">
          <Badge className={STATUS_COLORS[doc.status]}>
            {STATUS_LABELS[doc.status]}
          </Badge>
        </td>
        <td className="px-4 py-3 text-xs text-muted hidden md:table-cell">
          {formatBytes(doc.fileSizeBytes)}
        </td>
        <td className="px-4 py-3 text-xs text-muted hidden lg:table-cell">
          {formatDate(doc.updatedAt)}
        </td>
        <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
          <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity justify-end">
            <button
              type="button"
              onClick={() => downloadAuthenticatedFile(downloadUrl(doc.id), doc.originalFilename)}
              className="p-1.5 rounded-md text-muted hover:text-[#F5A800] hover:bg-[#FFF3CC] transition-colors"
              title="Télécharger"
            >
              <Download size={15} />
            </button>
            {/* Aperçu rapide */}
            {onPreview && (
              <button
                type="button"
                onClick={onPreview}
                className="p-1.5 rounded-md text-muted hover:text-[#6366F1] hover:bg-indigo-50 transition-colors"
                title="Aperçu rapide"
              >
                <Eye size={15} />
              </button>
            )}
            <button
              type="button"
              onClick={() => navigate(`/documents/${doc.id}`)}
              className="p-1.5 rounded-md text-muted hover:text-[#F5A800] hover:bg-[#FFF3CC] transition-colors"
              title="Ouvrir la page du document"
            >
              <ExternalLink size={15} />
            </button>
            <button
              type="button"
              onClick={() => setConfirmDelete(true)}
              className="p-1.5 rounded-md text-muted hover:text-red-600 hover:bg-red-50 transition-colors"
              title="Supprimer"
            >
              <Trash2 size={15} />
            </button>
          </div>
        </td>
      </tr>

      <ConfirmModal
        open={confirmDelete}
        onClose={() => setConfirmDelete(false)}
        onConfirm={() => deleteMutation.mutate()}
        title="Supprimer le document"
        message={`Êtes-vous sûr de vouloir supprimer "${doc.title}" ? Cette action est irréversible.`}
        confirmLabel="Supprimer"
        loading={deleteMutation.isPending}
      />
    </>
  )
}
