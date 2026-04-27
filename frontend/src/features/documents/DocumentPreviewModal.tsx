import { useEffect, useRef, useState, useCallback } from 'react'
import {
  X, Download, Loader2, AlertCircle, FileText,
  ChevronLeft, ChevronRight, ZoomIn, ZoomOut, RotateCcw,
  Maximize2, Minimize2,
} from 'lucide-react'
import * as pdfjsLib from 'pdfjs-dist'
import { api } from '@/lib/axios'
import { downloadAuthenticatedFile } from '@/lib/download'
import type { DocumentDTO } from '@/types'

// ── PDF.js worker setup ───────────────────────────────────────────────────────
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
  'pdfjs-dist/build/pdf.worker.min.mjs',
  import.meta.url,
).toString()

// ── Types ─────────────────────────────────────────────────────────────────────
interface Props {
  doc: DocumentDTO
  open: boolean
  onClose: () => void
}

type LoadState =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'ready'; blob: Blob; type: 'pdf' | 'image' | 'unsupported' }
  | { status: 'error'; message: string }

// ── Helpers ───────────────────────────────────────────────────────────────────
const isPdf   = (m: string) => m === 'application/pdf'
const isImage = (m: string) => m.startsWith('image/')

const ZOOM_LEVELS = [0.5, 0.75, 1, 1.25, 1.5, 2, 2.5, 3]
const DEFAULT_ZOOM_IDX = 2  // 1.0

// ── PDF Viewer ────────────────────────────────────────────────────────────────
function PdfViewer({ blob, filename }: { blob: Blob; filename: string }) {
  const canvasRef  = useRef<HTMLCanvasElement>(null)
  const containerRef = useRef<HTMLDivElement>(null)
  const pdfRef     = useRef<pdfjsLib.PDFDocumentProxy | null>(null)
  const renderTask = useRef<pdfjsLib.RenderTask | null>(null)

  const [totalPages, setTotalPages]   = useState(0)
  const [currentPage, setCurrentPage] = useState(1)
  const [zoomIdx, setZoomIdx]         = useState(DEFAULT_ZOOM_IDX)
  const [rendering, setRendering]     = useState(false)
  const [error, setError]             = useState<string | null>(null)

  const zoom = ZOOM_LEVELS[zoomIdx]

  // Load document
  useEffect(() => {
    let cancelled = false
    const url = URL.createObjectURL(blob)

    pdfjsLib.getDocument({ url }).promise
      .then(pdf => {
        if (cancelled) return
        pdfRef.current = pdf
        setTotalPages(pdf.numPages)
        setCurrentPage(1)
      })
      .catch(() => {
        if (!cancelled) setError('Impossible de charger le PDF.')
      })

    return () => {
      cancelled = true
      URL.revokeObjectURL(url)
      pdfRef.current?.destroy()
      pdfRef.current = null
    }
  }, [blob])

  // Render page
  const renderPage = useCallback(async (pageNum: number, scale: number) => {
    const pdf = pdfRef.current
    const canvas = canvasRef.current
    if (!pdf || !canvas) return

    // Cancel any pending render
    if (renderTask.current) {
      renderTask.current.cancel()
      renderTask.current = null
    }

    setRendering(true)
    try {
      const page = await pdf.getPage(pageNum)
      const viewport = page.getViewport({ scale })

      const ctx = canvas.getContext('2d')!
      const devicePixelRatio = window.devicePixelRatio || 1

      canvas.width  = viewport.width  * devicePixelRatio
      canvas.height = viewport.height * devicePixelRatio
      canvas.style.width  = `${viewport.width}px`
      canvas.style.height = `${viewport.height}px`
      ctx.scale(devicePixelRatio, devicePixelRatio)

      const task = page.render({ canvasContext: ctx, viewport, canvas })
      renderTask.current = task
      await task.promise
    } catch (e: unknown) {
      // RenderingCancelledException is expected when navigating fast — ignore
      const msg = e instanceof Error ? e.message : ''
      if (!msg.includes('cancelled') && !msg.includes('RenderingCancelled')) {
        setError('Erreur lors du rendu de la page.')
      }
    } finally {
      setRendering(false)
    }
  }, [])

  useEffect(() => {
    if (pdfRef.current) renderPage(currentPage, zoom)
  }, [currentPage, zoom, renderPage, totalPages])

  const goTo = (n: number) => setCurrentPage(Math.min(Math.max(1, n), totalPages))

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center h-full gap-3 text-red-500">
        <AlertCircle size={40} />
        <p className="text-sm font-medium">{error}</p>
      </div>
    )
  }

  return (
    <div className="flex flex-col h-full">
      {/* PDF toolbar */}
      <div className="flex items-center justify-between px-4 py-2 bg-[#1A1A1A] border-b border-[#2E2E2E] shrink-0">
        {/* Page nav */}
        <div className="flex items-center gap-2">
          <button
            onClick={() => goTo(currentPage - 1)}
            disabled={currentPage <= 1}
            className="p-1.5 rounded-md text-white/60 hover:text-white hover:bg-white/10 disabled:opacity-30 transition-colors"
          >
            <ChevronLeft size={16} />
          </button>
          <div className="flex items-center gap-1.5 text-sm text-white/80">
            <input
              type="number"
              min={1}
              max={totalPages}
              value={currentPage}
              onChange={e => goTo(Number(e.target.value))}
              className="w-12 text-center bg-white/10 border border-white/20 rounded px-1 py-0.5 text-white text-xs focus:outline-none focus:ring-1 focus:ring-[#F5A800]"
            />
            <span className="text-white/40">/</span>
            <span>{totalPages}</span>
          </div>
          <button
            onClick={() => goTo(currentPage + 1)}
            disabled={currentPage >= totalPages}
            className="p-1.5 rounded-md text-white/60 hover:text-white hover:bg-white/10 disabled:opacity-30 transition-colors"
          >
            <ChevronRight size={16} />
          </button>
        </div>

        {/* Zoom controls */}
        <div className="flex items-center gap-1.5">
          <button
            onClick={() => setZoomIdx(i => Math.max(0, i - 1))}
            disabled={zoomIdx === 0}
            className="p-1.5 rounded-md text-white/60 hover:text-white hover:bg-white/10 disabled:opacity-30 transition-colors"
            title="Zoom arrière"
          >
            <ZoomOut size={15} />
          </button>
          <button
            onClick={() => setZoomIdx(DEFAULT_ZOOM_IDX)}
            className="text-xs text-white/60 hover:text-white hover:bg-white/10 px-2 py-1 rounded-md transition-colors min-w-[46px] text-center"
            title="Réinitialiser le zoom"
          >
            {Math.round(zoom * 100)}%
          </button>
          <button
            onClick={() => setZoomIdx(i => Math.min(ZOOM_LEVELS.length - 1, i + 1))}
            disabled={zoomIdx === ZOOM_LEVELS.length - 1}
            className="p-1.5 rounded-md text-white/60 hover:text-white hover:bg-white/10 disabled:opacity-30 transition-colors"
            title="Zoom avant"
          >
            <ZoomIn size={15} />
          </button>
          <button
            onClick={() => setZoomIdx(DEFAULT_ZOOM_IDX)}
            className="p-1.5 rounded-md text-white/60 hover:text-white hover:bg-white/10 transition-colors"
            title="Réinitialiser"
          >
            <RotateCcw size={14} />
          </button>
        </div>

        {/* Filename */}
        <span className="text-xs text-white/40 hidden sm:block truncate max-w-[200px]">{filename}</span>
      </div>

      {/* Canvas area */}
      <div
        ref={containerRef}
        className="flex-1 overflow-auto bg-[#2A2A2A] flex items-start justify-center py-6"
      >
        <div className="relative">
          {rendering && (
            <div className="absolute inset-0 flex items-center justify-center z-10">
              <div className="bg-black/60 rounded-lg px-3 py-2 flex items-center gap-2">
                <Loader2 size={16} className="animate-spin text-[#F5A800]" />
                <span className="text-xs text-white">Rendu…</span>
              </div>
            </div>
          )}
          <canvas
            ref={canvasRef}
            className="shadow-2xl rounded"
            style={{ display: 'block' }}
          />
        </div>
      </div>

      {/* Keyboard shortcut hint */}
      <div className="flex items-center justify-center gap-4 px-4 py-2 bg-[#1A1A1A] border-t border-[#2E2E2E] shrink-0">
        <span className="text-[10px] text-white/25">← → naviguer les pages</span>
        <span className="text-[10px] text-white/25">+ / - zoomer</span>
      </div>
    </div>
  )
}

// ── Image Viewer ──────────────────────────────────────────────────────────────
function ImageViewer({ blob, title }: { blob: Blob; title: string }) {
  const [url] = useState(() => URL.createObjectURL(blob))

  useEffect(() => () => URL.revokeObjectURL(url), [url])

  return (
    <div className="flex items-center justify-center h-full p-6 bg-[#2A2A2A]">
      <img
        src={url}
        alt={title}
        className="max-w-full max-h-full object-contain rounded-lg shadow-2xl"
      />
    </div>
  )
}

// ── Unsupported fallback ───────────────────────────────────────────────────────
function UnsupportedViewer({
  doc, onDownload,
}: {
  doc: DocumentDTO
  onDownload: () => void
}) {
  return (
    <div className="flex flex-col items-center justify-center h-full gap-5 bg-[#F4F4F4]">
      <div className="w-20 h-20 bg-[#1A1A1A] rounded-2xl flex items-center justify-center shadow-xl">
        <FileText size={36} className="text-[#F5A800]" />
      </div>
      <div className="text-center">
        <p className="text-base font-semibold text-[#1A1A1A]">Aperçu non disponible</p>
        <p className="text-sm text-[#888] mt-1">
          Le format <code className="bg-[#F0F0F0] px-1.5 py-0.5 rounded text-xs">{doc.mimeType}</code> ne peut pas être prévisualisé en ligne.
        </p>
      </div>
      <button
        onClick={onDownload}
        className="flex items-center gap-2 px-5 py-2.5 bg-[#1A1A1A] text-white text-sm font-semibold rounded-xl hover:bg-[#F5A800] hover:text-[#1A1A1A] transition-all duration-200 shadow-lg"
      >
        <Download size={16} />
        Télécharger le fichier
      </button>
    </div>
  )
}

// ── Main Modal ─────────────────────────────────────────────────────────────────
export function DocumentPreviewModal({ doc, open, onClose }: Props) {
  const [loadState, setLoadState] = useState<LoadState>({ status: 'idle' })
  const [fullscreen, setFullscreen] = useState(false)

  const mimeType = doc.latestVersion?.mimeType ?? doc.mimeType ?? ''

  const load = useCallback(async () => {
    setLoadState({ status: 'loading' })
    try {
      const response = await api.get(`/documents/${doc.id}/download`, {
        responseType: 'blob',
      })
      const blob: Blob = response.data
      const type = isPdf(mimeType) ? 'pdf' : isImage(mimeType) ? 'image' : 'unsupported'
      setLoadState({ status: 'ready', blob, type })
    } catch {
      setLoadState({ status: 'error', message: 'Impossible de charger le fichier.' })
    }
  }, [doc.id, mimeType])

  useEffect(() => {
    if (open) {
      load()
    } else {
      setLoadState({ status: 'idle' })
      setFullscreen(false)
    }
  }, [open, load])

  // Keyboard shortcuts
  useEffect(() => {
    if (!open) return
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
      if (e.key === 'f' || e.key === 'F') setFullscreen(f => !f)
    }
    document.addEventListener('keydown', handler)
    return () => document.removeEventListener('keydown', handler)
  }, [open, onClose])

  if (!open) return null

  const filename = doc.latestVersion?.originalFilename ?? doc.originalFilename ?? doc.title

  const handleDownload = () =>
    downloadAuthenticatedFile(`/api/documents/${doc.id}/download`, filename)

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/80 backdrop-blur-md"
        onClick={onClose}
      />

      {/* Panel */}
      <div
        className={`relative z-10 flex flex-col shadow-2xl transition-all duration-300 ${
          fullscreen
            ? 'w-full h-full rounded-none'
            : 'w-full max-w-6xl h-[92vh] rounded-2xl'
        } overflow-hidden`}
        style={{ background: 'var(--bg-card)' }}
      >
        {/* ── Header ── */}
        <div className="flex items-center justify-between px-5 py-3 border-b shrink-0"
          style={{ borderColor: 'var(--border)', background: 'var(--bg-subtle)' }}
        >
          {/* Left: icon + title + mime */}
          <div className="flex items-center gap-3 min-w-0">
            <div className="w-8 h-8 bg-[#1A1A1A] rounded-lg flex items-center justify-center shrink-0">
              <FileText size={15} className="text-[#F5A800]" />
            </div>
            <div className="min-w-0">
              <p className="text-sm font-semibold truncate" style={{ color: 'var(--text-primary)' }}>
                {doc.title}
              </p>
              <p className="text-[11px] truncate" style={{ color: 'var(--text-faint)' }}>
                {filename}
                {doc.latestVersion && (
                  <span className="ml-2 bg-[#F5A800]/15 text-[#F5A800] px-1.5 py-0.5 rounded text-[10px] font-semibold">
                    v{doc.latestVersion.versionNumber}
                  </span>
                )}
              </p>
            </div>
          </div>

          {/* Right: actions */}
          <div className="flex items-center gap-1.5 shrink-0 ml-3">
            <button
              onClick={handleDownload}
              className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
              style={{ color: 'var(--text-secondary)' }}
              onMouseEnter={e => (e.currentTarget.style.background = 'var(--bg-muted)')}
              onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
              title="Télécharger"
            >
              <Download size={13} />
              <span className="hidden sm:inline">Télécharger</span>
            </button>
            <button
              onClick={() => setFullscreen(f => !f)}
              className="p-1.5 rounded-lg text-xs transition-colors"
              style={{ color: 'var(--text-faint)' }}
              onMouseEnter={e => (e.currentTarget.style.background = 'var(--bg-muted)')}
              onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
              title={fullscreen ? 'Quitter le plein écran (F)' : 'Plein écran (F)'}
            >
              {fullscreen ? <Minimize2 size={15} /> : <Maximize2 size={15} />}
            </button>
            <button
              onClick={onClose}
              className="p-1.5 rounded-lg transition-colors"
              style={{ color: 'var(--text-faint)' }}
              onMouseEnter={e => (e.currentTarget.style.background = 'var(--bg-muted)')}
              onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
              title="Fermer (Échap)"
            >
              <X size={16} />
            </button>
          </div>
        </div>

        {/* ── Body ── */}
        <div className="flex-1 overflow-hidden">
          {loadState.status === 'loading' && (
            <div className="flex flex-col items-center justify-center h-full gap-4" style={{ background: 'var(--bg-page)' }}>
              <div className="relative">
                <div className="w-16 h-16 rounded-full border-4 border-[#F5A800]/20 border-t-[#F5A800] animate-spin" />
                <div className="absolute inset-0 flex items-center justify-center">
                  <FileText size={18} className="text-[#F5A800]" />
                </div>
              </div>
              <p className="text-sm font-medium" style={{ color: 'var(--text-muted)' }}>
                Chargement de l'aperçu…
              </p>
            </div>
          )}

          {loadState.status === 'error' && (
            <div className="flex flex-col items-center justify-center h-full gap-3 text-red-500">
              <AlertCircle size={40} />
              <p className="text-sm font-medium">{loadState.message}</p>
              <button
                onClick={load}
                className="text-sm text-[#F5A800] hover:underline"
              >
                Réessayer
              </button>
            </div>
          )}

          {loadState.status === 'ready' && loadState.type === 'pdf' && (
            <PdfViewer blob={loadState.blob} filename={filename} />
          )}

          {loadState.status === 'ready' && loadState.type === 'image' && (
            <ImageViewer blob={loadState.blob} title={doc.title} />
          )}

          {loadState.status === 'ready' && loadState.type === 'unsupported' && (
            <UnsupportedViewer doc={doc} onDownload={handleDownload} />
          )}
        </div>
      </div>
    </div>
  )
}
