import { useState, useRef, useCallback, type DragEvent, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  Upload, X, CheckCircle2, FileText, FileImage,
  FileSpreadsheet, FileArchive, File,
} from 'lucide-react'
import { toast } from 'sonner'
import { api } from '@/lib/axios'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { formatBytes } from '@/lib/utils'

interface Props {
  open: boolean
  onClose: () => void
  folderId: string
}

const ACCEPTED_TYPES = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp,.svg,.tiff,.zip'

// ── File type icon helper ─────────────────────────────────────────────────────
function FileTypeIcon({ mime, size = 24 }: { mime: string; size?: number }) {
  if (mime.startsWith('image/'))
    return <FileImage size={size} className="text-blue-500" />
  if (mime === 'application/pdf')
    return <FileText size={size} className="text-red-500" />
  if (mime.includes('spreadsheet') || mime.includes('excel') || mime.includes('csv'))
    return <FileSpreadsheet size={size} className="text-green-600" />
  if (mime.includes('zip') || mime.includes('rar') || mime.includes('archive'))
    return <FileArchive size={size} className="text-purple-500" />
  return <File size={size} className="text-[#F5A800]" />
}

// ── Extension badge ────────────────────────────────────────────────────────────
function getExtension(filename: string): string {
  return filename.split('.').pop()?.toUpperCase() ?? 'FILE'
}

// ── Main component ─────────────────────────────────────────────────────────────
export function UploadModal({ open, onClose, folderId }: Props) {
  const [file, setFile]       = useState<File | null>(null)
  const [title, setTitle]     = useState('')
  const [comment, setComment] = useState('')
  const [dragging, setDragging] = useState(false)
  const [progress, setProgress] = useState(0)
  const inputRef = useRef<HTMLInputElement>(null)
  const qc = useQueryClient()

  const mutation = useMutation({
    mutationFn: () => {
      const form = new FormData()
      form.append('file', file!)
      form.append('title', title || file!.name)
      form.append('folder_id', folderId)
      if (comment) form.append('comment', comment)

      return api.post('/documents', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          if (e.total) setProgress(Math.round((e.loaded * 100) / e.total))
        },
      })
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['folder', folderId] })
      toast.success(`"${title || file?.name}" importé avec succès.`)
      handleClose()
    },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
      toast.error(msg ?? "Erreur lors de l'upload.")
      setProgress(0)
    },
  })

  const handleClose = () => {
    setFile(null); setTitle(''); setComment(''); setProgress(0); onClose()
  }

  const handleFile = useCallback((f: File) => {
    setFile(f)
    setTitle(f.name.replace(/\.[^.]+$/, ''))
  }, [])

  const handleDrop = (e: DragEvent) => {
    e.preventDefault()
    setDragging(false)
    const dropped = e.dataTransfer.files[0]
    if (dropped) handleFile(dropped)
  }

  const handleDragOver = (e: DragEvent) => { e.preventDefault(); setDragging(true) }
  const handleDragLeave = (e: DragEvent) => {
    // Only set false if leaving the drop zone entirely
    if (!e.currentTarget.contains(e.relatedTarget as Node)) setDragging(false)
  }

  const handleSubmit = (e: FormEvent) => { e.preventDefault(); if (!file) return; mutation.mutate() }

  const isPending = mutation.isPending
  const isSuccess = mutation.isSuccess

  return (
    <Modal open={open} onClose={handleClose} title="Importer un document" className="max-w-xl">
      <form onSubmit={handleSubmit} className="space-y-4">

        {/* ── Drop zone ── */}
        <div
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          onClick={() => !file && inputRef.current?.click()}
          className={`
            relative border-2 border-dashed rounded-2xl transition-all duration-200 overflow-hidden
            ${file ? 'border-transparent cursor-default' : 'cursor-pointer'}
            ${!file && dragging
              ? 'border-[#F5A800] bg-[#FFF8E7] scale-[1.01]'
              : !file
                ? 'border-[#E0E0E0] hover:border-[#F5A800] hover:bg-[#FFFDF5]'
                : ''
            }
          `}
        >
          {/* Dragging overlay */}
          {dragging && !file && (
            <div className="absolute inset-0 flex flex-col items-center justify-center z-10 pointer-events-none">
              <div className="w-16 h-16 rounded-full bg-[#F5A800]/20 flex items-center justify-center animate-bounce">
                <Upload size={28} className="text-[#F5A800]" />
              </div>
              <p className="text-sm font-bold text-[#F5A800] mt-3">Déposez le fichier ici</p>
            </div>
          )}

          {file ? (
            /* File selected preview */
            <div className="flex items-center gap-4 p-4 bg-[#FAFAFA] border border-[#E8E8E8] rounded-2xl">
              {/* Icon */}
              <div className="w-14 h-14 bg-white rounded-xl border border-[#E8E8E8] shadow-sm flex flex-col items-center justify-center gap-0.5 shrink-0">
                <FileTypeIcon mime={file.type || ''} size={22} />
                <span className="text-[9px] font-bold text-[#AAA] leading-none">
                  {getExtension(file.name)}
                </span>
              </div>

              {/* Info */}
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-[#1A1A1A] truncate">{file.name}</p>
                <p className="text-xs text-[#888] mt-0.5">{formatBytes(file.size)}</p>
                {/* Mini progress if uploading */}
                {isPending && (
                  <div className="mt-2 flex items-center gap-2">
                    <div className="flex-1 bg-[#E8E8E8] rounded-full h-1.5 overflow-hidden">
                      <div
                        className="bg-[#F5A800] h-1.5 rounded-full transition-all duration-150"
                        style={{ width: `${progress}%` }}
                      />
                    </div>
                    <span className="text-[10px] text-[#AAA] shrink-0">{progress}%</span>
                  </div>
                )}
              </div>

              {/* Remove button */}
              {!isPending && (
                <button
                  type="button"
                  onClick={(e) => { e.stopPropagation(); setFile(null); setTitle(''); setProgress(0) }}
                  className="p-1.5 rounded-lg text-[#AAA] hover:text-red-500 hover:bg-red-50 transition-colors shrink-0"
                  title="Retirer ce fichier"
                >
                  <X size={15} />
                </button>
              )}
            </div>
          ) : (
            /* Empty drop zone */
            <div className={`flex flex-col items-center justify-center py-10 px-6 transition-opacity ${dragging ? 'opacity-0' : 'opacity-100'}`}>
              <div className="w-14 h-14 rounded-2xl bg-[#F5F5F5] flex items-center justify-center mb-3 group-hover:bg-[#FFF3CC] transition-colors">
                <Upload size={24} className="text-[#AAA]" />
              </div>
              <p className="text-sm font-semibold text-[#333]">
                Glissez un fichier ici
              </p>
              <p className="text-xs text-[#AAA] mt-1">ou <span className="text-[#F5A800] font-medium">cliquez pour sélectionner</span></p>
              <div className="flex flex-wrap justify-center gap-1 mt-4">
                {['PDF', 'Word', 'Excel', 'Images', 'ZIP'].map(t => (
                  <span key={t} className="text-[10px] bg-[#F0F0F0] text-[#888] px-2 py-0.5 rounded-md font-medium">
                    {t}
                  </span>
                ))}
                <span className="text-[10px] text-[#CCC] px-2 py-0.5">· max 128 MB</span>
              </div>
            </div>
          )}
        </div>

        <input
          ref={inputRef}
          type="file"
          className="hidden"
          accept={ACCEPTED_TYPES}
          onChange={(e) => { const f = e.target.files?.[0]; if (f) handleFile(f) }}
        />

        {/* Success state */}
        {isSuccess && progress === 100 && (
          <div className="flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 px-4 py-2.5 rounded-xl">
            <CheckCircle2 size={16} />
            Importé avec succès !
          </div>
        )}

        {/* ── Title ── */}
        <div>
          <label className="block text-xs font-bold text-[#555] uppercase tracking-wider mb-2">
            Titre <span className="text-red-500">*</span>
          </label>
          <input
            type="text"
            required
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            className="w-full px-3.5 py-2.5 rounded-xl border border-[#E8E8E8] text-sm focus:outline-none focus:ring-2 focus:ring-[#F5A800] bg-white text-[#1A1A1A] placeholder-[#CCC] transition-shadow"
            placeholder="Titre du document"
          />
        </div>

        {/* ── Comment ── */}
        <div>
          <label className="block text-xs font-bold text-[#555] uppercase tracking-wider mb-2">
            Commentaire <span className="text-[#CCC] font-normal normal-case text-xs tracking-normal">(optionnel)</span>
          </label>
          <textarea
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            rows={2}
            className="w-full px-3.5 py-2.5 rounded-xl border border-[#E8E8E8] text-sm focus:outline-none focus:ring-2 focus:ring-[#F5A800] bg-white text-[#1A1A1A] placeholder-[#CCC] resize-none transition-shadow"
            placeholder="Description ou note sur ce document…"
          />
        </div>

        {/* ── Actions ── */}
        <div className="flex justify-end gap-2 pt-1">
          <Button variant="secondary" type="button" onClick={handleClose} disabled={isPending}>
            Annuler
          </Button>
          <Button
            type="submit"
            loading={isPending}
            disabled={!file || !title.trim()}
          >
            {isPending ? `Envoi en cours… ${progress}%` : 'Importer le document'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
