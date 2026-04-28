import { useState, useRef, useEffect, useCallback } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { PDFDocument } from 'pdf-lib'
import * as pdfjsLib from 'pdfjs-dist'
import {
  ZoomIn, ZoomOut, ChevronLeft, ChevronRight, Loader2,
  Calendar, Stamp, PenLine, X, ArrowLeft, Check, Move, AlertCircle,
} from 'lucide-react'
import { getProfileSignature, getCompanyStamp } from '@/api/profileSignature'
import { Button } from '@/components/ui/Button'
import { useAuthStore } from '@/store/auth'
import type { DocumentDTO } from '@/types'

// ── PDF.js worker ─────────────────────────────────────────────────────────────
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
  'pdfjs-dist/build/pdf.worker.mjs',
  import.meta.url,
).toString()

// ── Types ─────────────────────────────────────────────────────────────────────

interface PlacedItem {
  id: string
  type: 'signature' | 'stamp' | 'date'
  dataUrl: string
  x: number   // fraction 0–1 of canvas width
  y: number   // fraction 0–1 of canvas height
  w: number   // fraction 0–1 of canvas width
  h: number   // fraction 0–1 of canvas height
  page: number
}

interface DragState {
  id: string
  action: 'move' | 'resize'
  startX: number; startY: number
  startW: number; startH: number
  mouseX0: number; mouseY0: number
  canvasW: number; canvasH: number
}

interface Props {
  open: boolean
  onClose: () => void
  doc: DocumentDTO
  onSigned: () => void
}

// ── Constants ─────────────────────────────────────────────────────────────────

const DATE_TEXT = new Date().toLocaleDateString('fr-FR', {
  day: '2-digit', month: 'long', year: 'numeric',
})

const ITEM_META: Record<PlacedItem['type'], { label: string; ring: string; bg: string }> = {
  signature: { label: 'Signature',         ring: '#5f61e6', bg: 'rgba(99,102,241,0.08)'  },
  stamp:     { label: 'Tampon entreprise', ring: '#8b5cf6', bg: 'rgba(139,92,246,0.08)' },
  date:      { label: 'Date de signature', ring: '#f59e0b', bg: 'rgba(245,158,11,0.08)' },
}

// ── Toggle switch ─────────────────────────────────────────────────────────────

function Toggle({ value, onChange }: { value: boolean; onChange: (v: boolean) => void }) {
  return (
    <button
      type="button"
      onClick={() => onChange(!value)}
      className={`relative inline-flex w-9 h-5 rounded-full transition-colors duration-200 focus:outline-none ${
        value ? 'bg-indigo-500' : 'bg-gray-300'
      }`}
    >
      <span
        className={`absolute top-0.5 w-4 h-4 rounded-full bg-card shadow transition-transform duration-200 ${
          value ? 'translate-x-4' : 'translate-x-0.5'
        }`}
      />
    </button>
  )
}

// ── SectionCard ───────────────────────────────────────────────────────────────

function SectionCard({
  iconNode, iconBg, label, badge, toggle, children,
}: {
  iconNode: React.ReactNode
  iconBg: string
  label: string
  badge?: React.ReactNode
  toggle?: React.ReactNode
  children: React.ReactNode
}) {
  return (
    <div className="rounded-xl border border-gray-200 overflow-hidden">
      <div className="flex items-center gap-2 px-3 py-2.5 bg-gray-50 border-b border-gray-100">
        <div className={`w-6 h-6 rounded-md flex items-center justify-center ${iconBg}`}>
          {iconNode}
        </div>
        <span className="text-xs font-semibold text-gray-800 flex-1 min-w-0 truncate">{label}</span>
        {badge}
        {toggle}
      </div>
      <div className="p-3">{children}</div>
    </div>
  )
}

// ── OverlayItem: draggable + resizable annotation element ─────────────────────

function OverlayItem({
  item,
  isDragging,
  onDown,
  onRemove,
}: {
  item: PlacedItem
  isDragging: boolean
  onDown: (e: React.PointerEvent<HTMLDivElement>, id: string, action: 'move' | 'resize') => void
  onRemove: (id: string) => void
}) {
  const [hovered, setHovered] = useState(false)
  const meta = ITEM_META[item.type]

  return (
    <div
      style={{
        position: 'absolute',
        left:   `${item.x * 100}%`,
        top:    `${item.y * 100}%`,
        width:  `${item.w * 100}%`,
        height: `${item.h * 100}%`,
        userSelect: 'none',
        touchAction: 'none',
      }}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => !isDragging && setHovered(false)}
    >
      {/* Drag area */}
      <div
        style={{
          position: 'absolute', inset: 0,
          border: `2px solid ${meta.ring}`,
          background: meta.bg,
          borderRadius: 4,
          cursor: isDragging ? 'grabbing' : 'grab',
          boxShadow: (hovered || isDragging)
            ? `0 0 0 3px ${meta.ring}33, 0 8px 32px rgba(0,0,0,0.28)`
            : `0 2px 10px rgba(0,0,0,0.20)`,
          transition: isDragging ? 'none' : 'box-shadow 0.15s',
          overflow: 'hidden',
        }}
        onPointerDown={e => onDown(e, item.id, 'move')}
      >
        <img
          src={item.dataUrl}
          alt={item.type}
          style={{ width: '100%', height: '100%', objectFit: 'contain', display: 'block', pointerEvents: 'none' }}
          draggable={false}
        />
      </div>

      {/* Label tooltip */}
      <div
        style={{
          position: 'absolute',
          top: -26, left: '50%', transform: 'translateX(-50%)',
          background: 'rgba(10,10,20,0.90)',
          color: '#fff',
          fontSize: 10, fontWeight: 600,
          padding: '2px 10px', borderRadius: 20,
          whiteSpace: 'nowrap', pointerEvents: 'none',
          opacity: hovered && !isDragging ? 1 : 0,
          transition: 'opacity 0.15s',
          backdropFilter: 'blur(4px)',
          border: `1px solid ${meta.ring}55`,
        }}
      >
        {meta.label}
      </div>

      {/* Delete button */}
      <button
        type="button"
        tabIndex={-1}
        onClick={e => { e.stopPropagation(); onRemove(item.id) }}
        style={{
          position: 'absolute', top: -9, right: -9,
          width: 18, height: 18,
          background: '#ef4444', border: '2px solid #fff',
          borderRadius: '50%', display: 'flex',
          alignItems: 'center', justifyContent: 'center',
          cursor: 'pointer', zIndex: 10,
          opacity: hovered && !isDragging ? 1 : 0,
          transform: hovered && !isDragging ? 'scale(1)' : 'scale(0.6)',
          transition: 'opacity 0.15s, transform 0.15s',
          pointerEvents: hovered && !isDragging ? 'auto' : 'none',
          boxShadow: '0 2px 6px rgba(0,0,0,0.25)',
        }}
      >
        <X size={8} strokeWidth={3.5} color="white" />
      </button>

      {/* Resize handle — bottom-right */}
      <div
        style={{
          position: 'absolute', bottom: -1, right: -1,
          width: 20, height: 20,
          cursor: 'nwse-resize', touchAction: 'none',
          opacity: hovered || isDragging ? 1 : 0.4,
          transition: isDragging ? 'none' : 'opacity 0.15s',
        }}
        onPointerDown={e => { e.stopPropagation(); onDown(e, item.id, 'resize') }}
      >
        <svg viewBox="0 0 20 20" style={{ width: '100%', height: '100%' }}>
          <path d="M6 18 L18 18 L18 6" fill="none" stroke="rgba(255,255,255,0.6)"
            strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
          <path d="M11 18 L18 18 L18 11" fill="none" stroke={meta.ring}
            strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </div>
    </div>
  )
}

// ── Main component ────────────────────────────────────────────────────────────

export function PlaceSignatureModal({ open, onClose, doc, onSigned }: Props) {
  const isAdmin   = useAuthStore((s) => s.isAdmin)
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const dragRef   = useRef<DragState | null>(null)
  const [activeDragId, setActiveDragId] = useState<string | null>(null)

  const [pdfDoc,      setPdfDoc]   = useState<pdfjsLib.PDFDocumentProxy | null>(null)
  const [currentPage, setPage]     = useState(1)
  const [totalPages,  setTotal]    = useState(1)
  const [scale,       setScale]    = useState(1.3)
  const [loading,     setLoading]  = useState(false)
  const [pdfError,    setPdfError] = useState(false)
  const [signing,     setSigning]  = useState(false)
  const [items,       setItems]    = useState<PlacedItem[]>([])
  const [showDate,    setShowDate]  = useState(true)
  const [showStamp,   setShowStamp] = useState(true)
  const [dateImgUrl,  setDateImg]   = useState('')

  const { data: sigData }   = useQuery({ queryKey: ['profile-signature'], queryFn: getProfileSignature, enabled: open })
  const { data: stampData } = useQuery({ queryKey: ['company-stamp'],     queryFn: getCompanyStamp,     enabled: open && isAdmin })

  // Generate date image once
  useEffect(() => {
    const c = document.createElement('canvas')
    c.width = 320; c.height = 56
    const ctx = c.getContext('2d')!
    ctx.font = 'italic 19px Georgia, serif'
    ctx.fillStyle = '#374151'
    ctx.fillText(`Signé le ${DATE_TEXT}`, 12, 38)
    setDateImg(c.toDataURL('image/png'))
  }, [])

  // Load PDF
  useEffect(() => {
    if (!open || !doc.id) return
    setLoading(true)
    setPdfError(false)
    setItems([])
    setPage(1)

    const token = useAuthStore.getState().token
    pdfjsLib.getDocument({
      url: `/api/documents/${doc.id}/download`,
      httpHeaders: { Authorization: `Bearer ${token}` },
    }).promise
      .then((pdf) => { setPdfDoc(pdf); setTotal(pdf.numPages) })
      .catch(() => { setPdfError(true); toast.error('Impossible de charger le PDF.') })
      .finally(() => setLoading(false))
  }, [open, doc.id])

  // Render page on canvas
  const renderPage = useCallback(async () => {
    if (!pdfDoc || !canvasRef.current) return
    const page     = await pdfDoc.getPage(currentPage)
    const viewport = page.getViewport({ scale })
    const canvas   = canvasRef.current
    canvas.width   = viewport.width
    canvas.height  = viewport.height
    await page.render({ canvasContext: canvas.getContext('2d')!, canvas, viewport }).promise
  }, [pdfDoc, currentPage, scale])

  useEffect(() => { renderPage() }, [renderPage])

  // Init items when data loads
  useEffect(() => {
    if (!open) return
    const next: PlacedItem[] = []
    if (sigData)
      next.push({ id: 'signature', type: 'signature', dataUrl: sigData.dataUrl,   x: 0.46, y: 0.72, w: 0.26, h: 0.10,  page: 1 })
    if (isAdmin && stampData && showStamp)
      next.push({ id: 'stamp',     type: 'stamp',     dataUrl: stampData.dataUrl, x: 0.70, y: 0.68, w: 0.20, h: 0.12,  page: 1 })
    if (dateImgUrl && showDate)
      next.push({ id: 'date',      type: 'date',      dataUrl: dateImgUrl,        x: 0.46, y: 0.84, w: 0.26, h: 0.044, page: 1 })
    setItems(next)
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sigData, stampData, dateImgUrl, open])

  // Toggle date
  useEffect(() => {
    setItems(prev => {
      const has = prev.some(i => i.id === 'date')
      if (showDate && dateImgUrl && !has)
        return [...prev, { id: 'date', type: 'date', dataUrl: dateImgUrl, x: 0.46, y: 0.84, w: 0.26, h: 0.044, page: currentPage }]
      if (!showDate && has)
        return prev.filter(i => i.id !== 'date')
      return prev
    })
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [showDate])

  // Toggle stamp
  useEffect(() => {
    setItems(prev => {
      const has = prev.some(i => i.id === 'stamp')
      if (showStamp && stampData && !has)
        return [...prev, { id: 'stamp', type: 'stamp', dataUrl: stampData.dataUrl, x: 0.70, y: 0.68, w: 0.20, h: 0.12, page: currentPage }]
      if (!showStamp && has)
        return prev.filter(i => i.id !== 'stamp')
      return prev
    })
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [showStamp])

  // ── Drag via document-level listeners ─────────────────────────────────────
  //
  // Using document pointermove/pointerup guarantees we never miss events
  // when the cursor moves fast outside the canvas area — far more reliable
  // than React synthetic event propagation.

  useEffect(() => {
    function onMove(e: PointerEvent) {
      const d = dragRef.current
      if (!d) return

      const dx = (e.clientX - d.mouseX0) / d.canvasW
      const dy = (e.clientY - d.mouseY0) / d.canvasH

      setItems(prev => prev.map(item => {
        if (item.id !== d.id) return item
        if (d.action === 'move') return {
          ...item,
          x: Math.max(0, Math.min(1 - item.w, d.startX + dx)),
          y: Math.max(0, Math.min(1 - item.h, d.startY + dy)),
        }
        return {
          ...item,
          w: Math.max(0.04, Math.min(0.96, d.startW + dx)),
          h: Math.max(0.02, Math.min(0.65, d.startH + dy)),
        }
      }))
    }

    function onUp() {
      dragRef.current = null
      setActiveDragId(null)
    }

    document.addEventListener('pointermove', onMove)
    document.addEventListener('pointerup',   onUp)
    return () => {
      document.removeEventListener('pointermove', onMove)
      document.removeEventListener('pointerup',   onUp)
    }
  }, []) // stable: uses dragRef + functional setItems

  const handlePointerDown = useCallback((
    e: React.PointerEvent<HTMLDivElement>,
    id: string,
    action: 'move' | 'resize',
  ) => {
    e.preventDefault()
    e.stopPropagation()

    const rect  = canvasRef.current!.getBoundingClientRect()
    const item  = items.find(i => i.id === id)
    if (!item) return

    dragRef.current = {
      id, action,
      startX:  item.x, startY: item.y,
      startW:  item.w, startH: item.h,
      mouseX0: e.clientX, mouseY0: e.clientY,
      canvasW: rect.width,
      canvasH: rect.height,
    }
    setActiveDragId(id)
  }, [items])

  const removeItem = useCallback((id: string) => {
    setItems(prev => prev.filter(i => i.id !== id))
    if (id === 'date')  setShowDate(false)
    if (id === 'stamp') setShowStamp(false)
  }, [])

  const restoreSignature = useCallback(() => {
    if (!sigData) return
    setItems(prev => [
      ...prev.filter(i => i.id !== 'signature'),
      { id: 'signature', type: 'signature', dataUrl: sigData.dataUrl, x: 0.46, y: 0.72, w: 0.26, h: 0.10, page: currentPage },
    ])
  }, [sigData, currentPage])

  // ── Sign ──────────────────────────────────────────────────────────────────

  const handleSign = async () => {
    if (!sigData) { toast.error('Aucune signature configurée. Créez-en une dans "Mon profil".'); return }
    if (!items.find(i => i.type === 'signature')) { toast.error('Placez votre signature sur le document.'); return }

    setSigning(true)
    try {
      const token  = useAuthStore.getState().token
      const pdfRes = await fetch(`/api/documents/${doc.id}/download`, {
        headers: { Authorization: `Bearer ${token}` },
      })
      if (!pdfRes.ok) throw new Error('download failed')

      const pdfBytes  = await pdfRes.arrayBuffer()
      const pdfLibDoc = await PDFDocument.load(pdfBytes)

      const byPage: Record<number, PlacedItem[]> = {}
      for (const item of items) {
        const p = item.page - 1
        byPage[p] = byPage[p] ? [...byPage[p], item] : [item]
      }

      for (const [pStr, pageItems] of Object.entries(byPage)) {
        const pdfPage = pdfLibDoc.getPages()[parseInt(pStr)]
        if (!pdfPage) continue
        const { width: pW, height: pH } = pdfPage.getSize()

        for (const item of pageItems) {
          const base64   = item.dataUrl.split(',')[1]
          const imgBytes = Uint8Array.from(atob(base64), c => c.charCodeAt(0))
          let pdfImg
          try {
            pdfImg = item.dataUrl.includes('image/png')
              ? await pdfLibDoc.embedPng(imgBytes)
              : await pdfLibDoc.embedJpg(imgBytes)
          } catch { continue }

          const dw = item.w * pW
          const dh = item.h * pH
          const dx = item.x * pW
          const dy = pH - (item.y * pH) - dh  // PDF y-axis is bottom-up

          pdfPage.drawImage(pdfImg, { x: dx, y: dy, width: dw, height: dh, opacity: 0.95 })

          // if (item.type === 'signature') {
          //   pdfPage.drawRectangle({
          //     x: dx - 2, y: dy - 2, width: dw + 4, height: dh + 4,
          //     borderColor: rgb(0.43, 0.47, 0.78),
          //     borderWidth: 0.5, opacity: 0.3,
          //   })
          // }
        }
      }

      const signedBytes = await pdfLibDoc.save()
      
      // 1. Retirer l'accent de "signé" pour éviter le blocage de validation ASCII par Symfony
      const safeFilename = (doc.originalFilename || 'document.pdf').replace(/\.pdf$/i, '_signe.pdf')
      
      // On utilise directement signedBytes pour créer le fichier, c'est plus direct et sûr
      const file = new File([signedBytes as unknown as BlobPart], safeFilename, { type: 'application/pdf' })

      const formData = new FormData()
      formData.append('file', file)
      formData.append('comment', `Signé électroniquement le ${DATE_TEXT}`)

      // On utilise l'API Fetch native pour garantir l'envoi parfait du FormData
      const res = await fetch(`/api/documents/${doc.id}/versions`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` },
        body: formData,
      })

      if (!res.ok) {
        const errData = await res.json().catch(() => ({}))
        throw { response: { status: res.status, data: errData } } // Mimique l'erreur Axios pour le bloc catch
      }

      toast.success('Document signé et nouvelle version enregistrée ✓')
      onSigned()
      onClose()
    } catch (err: any) {
      console.error(err)
      
      // 2. Extraire et afficher la vraie erreur de validation renvoyée par Symfony
      const serverError = err.response?.data?.error || err.response?.data?.detail || err.response?.data?.violations?.[0]?.message
      if (serverError) {
        toast.error(`Refusé par le serveur : ${serverError}`)
      } else if (err.response?.status === 422) {
        toast.error("Erreur 422 : Fichier invalide ou champ non autorisé. (Testez de retirer le formData.append('comment'))")
      } else {
        toast.error('Erreur lors de la signature du document.')
      }
    } finally {
      setSigning(false)
    }
  }

  if (!open) return null

  const pageItems = items.filter(i => i.page === currentPage)
  const hasSig    = items.some(i => i.type === 'signature')

  return (
    <div
      className="fixed inset-0 z-50 flex flex-col select-none"
      style={{ background: '#0f0f1a' }}
    >
      {/* ── Top bar ─────────────────────────────────────────────────────── */}
      <div
        className="flex items-center justify-between px-4 shrink-0"
        style={{ height: 48, background: '#14141f', borderBottom: '1px solid rgba(255,255,255,0.07)' }}
      >
        {/* Back */}
        <button
          onClick={onClose}
          className="flex items-center gap-2 text-sm font-medium text-gray-400 hover:text-white transition-colors px-2.5 py-1.5 rounded-lg hover:bg-card/5"
        >
          <ArrowLeft size={15} />
          Retour
        </button>

        {/* Centre — title + page nav */}
        <div className="flex items-center gap-3">
          <span className="text-sm font-semibold text-white/80 max-w-[260px] truncate">
            {doc.title}
          </span>
          {totalPages > 1 && (
            <>
              <div className="w-px h-4" style={{ background: 'rgba(255,255,255,0.1)' }} />
              <div className="flex items-center gap-1">
                <button
                  onClick={() => setPage(p => Math.max(1, p - 1))}
                  disabled={currentPage <= 1}
                  className="p-1 rounded text-gray-500 hover:text-white hover:bg-card/8 disabled:opacity-20 transition-colors"
                >
                  <ChevronLeft size={14} />
                </button>
                <span className="text-xs font-mono text-gray-300 px-2.5 py-1 rounded-md" style={{ background: 'rgba(255,255,255,0.06)' }}>
                  {currentPage} / {totalPages}
                </span>
                <button
                  onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                  disabled={currentPage >= totalPages}
                  className="p-1 rounded text-gray-500 hover:text-white hover:bg-card/8 disabled:opacity-20 transition-colors"
                >
                  <ChevronRight size={14} />
                </button>
              </div>
            </>
          )}
        </div>

        {/* Zoom controls */}
        <div
          className="flex items-center gap-0.5 rounded-lg px-1"
          style={{ background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.08)' }}
        >
          <button
            onClick={() => setScale(s => Math.max(0.4, parseFloat((s - 0.15).toFixed(2))))}
            className="p-1.5 rounded text-gray-500 hover:text-white transition-colors"
          >
            <ZoomOut size={14} />
          </button>
          <span className="text-xs font-mono text-gray-400 w-10 text-center">{Math.round(scale * 100)}%</span>
          <button
            onClick={() => setScale(s => Math.min(3, parseFloat((s + 0.15).toFixed(2))))}
            className="p-1.5 rounded text-gray-500 hover:text-white transition-colors"
          >
            <ZoomIn size={14} />
          </button>
        </div>
      </div>

      {/* ── Main ────────────────────────────────────────────────────────── */}
      <div className="flex flex-1 min-h-0">

        {/* PDF area */}
        <div
          className="flex-1 overflow-auto flex items-start justify-center"
          style={{ padding: '48px 40px', background: '#111118' }}
        >
          {loading && (
            <div className="flex flex-col items-center justify-center gap-4 w-full min-h-[60vh]">
              <div
                className="w-14 h-14 rounded-2xl flex items-center justify-center"
                style={{ background: 'rgba(255,255,255,0.04)', border: '1px solid rgba(255,255,255,0.07)' }}
              >
                <Loader2 size={24} className="animate-spin text-indigo-400" />
              </div>
              <p className="text-sm text-gray-600">Chargement du document…</p>
            </div>
          )}

          {pdfError && (
            <div className="flex flex-col items-center justify-center gap-3 w-full min-h-[60vh]">
              <div className="w-14 h-14 rounded-2xl flex items-center justify-center"
                style={{ background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)' }}>
                <AlertCircle size={24} className="text-red-400" />
              </div>
              <p className="text-sm font-medium text-red-400">Impossible de charger le PDF</p>
            </div>
          )}

          {!loading && !pdfError && (
            <div
              className="relative"
              style={{
                display: 'inline-block',
                boxShadow: '0 40px 100px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.04)',
              }}
            >
              <canvas ref={canvasRef} style={{ display: 'block' }} />

              {/* Annotation overlays */}
              <div style={{ position: 'absolute', inset: 0 }}>
                {pageItems.map(item => (
                  <OverlayItem
                    key={item.id}
                    item={item}
                    isDragging={activeDragId === item.id}
                    onDown={handlePointerDown}
                    onRemove={removeItem}
                  />
                ))}
              </div>
            </div>
          )}
        </div>

        {/* ── Right panel ──────────────────────────────────────────────── */}
        <div
          className="flex flex-col shrink-0"
          style={{ width: 296, background: '#ffffff', borderLeft: '1px solid #e5e7eb' }}
        >
          {/* Header */}
          <div className="px-4 py-4 border-b border-gray-100">
            <h2 className="text-sm font-bold text-gray-900">Apposer la signature</h2>
            <p className="text-[11px] text-gray-400 mt-0.5 leading-relaxed">
              Glissez · Coin ↘ pour redimensionner
            </p>
          </div>

          {/* Sections */}
          <div className="flex-1 overflow-y-auto p-4 space-y-3">

            {/* Signature */}
            <SectionCard
              iconNode={<PenLine size={13} className="text-indigo-600" />}
              iconBg="bg-indigo-50"
              label="Ma signature"
              badge={
                sigData ? (
                  <span className={`text-[10px] font-semibold px-2 py-0.5 rounded-full ${hasSig ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500'}`}>
                    {hasSig ? '● Sur le doc' : '○ Retirée'}
                  </span>
                ) : undefined
              }
            >
              {sigData ? (
                <div>
                  <div className="bg-indigo-50/70 rounded-xl p-3 border border-indigo-100">
                    <img src={sigData.dataUrl} alt="Ma signature" className="max-h-14 max-w-full object-contain mx-auto" />
                  </div>
                  {!hasSig && (
                    <button
                      onClick={restoreSignature}
                      className="mt-2.5 w-full text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 py-1.5 border border-indigo-200 hover:border-indigo-400 rounded-lg transition-colors"
                    >
                      + Replacer sur le document
                    </button>
                  )}
                </div>
              ) : (
                <div className="text-center py-4">
                  <p className="text-xs text-gray-400 mb-2">Aucune signature enregistrée</p>
                  <a href="/profile" target="_blank" className="text-xs font-semibold text-indigo-600 hover:underline">
                    Créer ma signature →
                  </a>
                </div>
              )}
            </SectionCard>

            {/* Date */}
            <SectionCard
              iconNode={<Calendar size={13} className="text-amber-600" />}
              iconBg="bg-amber-50"
              label="Date de signature"
              toggle={<Toggle value={showDate} onChange={setShowDate} />}
            >
              <div className={`rounded-xl px-3 py-2.5 border transition-all ${showDate ? 'bg-amber-50 border-amber-100' : 'bg-gray-50 border-gray-100 opacity-40'}`}>
                <p className="text-xs font-medium text-gray-700 italic">Signé le {DATE_TEXT}</p>
              </div>
            </SectionCard>

            {/* Stamp — admin only */}
            {isAdmin && (
              <SectionCard
                iconNode={<Stamp size={13} className="text-violet-600" />}
                iconBg="bg-violet-50"
                label="Tampon entreprise"
                toggle={stampData ? <Toggle value={showStamp} onChange={setShowStamp} /> : undefined}
              >
                {stampData ? (
                  <div className={`rounded-xl p-3 border transition-all ${showStamp ? 'bg-violet-50 border-violet-100' : 'bg-gray-50 border-gray-100 opacity-40'}`}>
                    <img src={stampData.dataUrl} alt="Tampon" className="max-h-14 max-w-full object-contain mx-auto" />
                  </div>
                ) : (
                  <div className="text-center py-4">
                    <p className="text-xs text-gray-400 mb-2">Aucun tampon enregistré</p>
                    <a href="/profile" target="_blank" className="text-xs font-semibold text-violet-600 hover:underline">
                      Configurer le tampon →
                    </a>
                  </div>
                )}
              </SectionCard>
            )}

            {/* Usage tips */}
            <div className="rounded-xl bg-gray-50 border border-gray-100 p-3.5">
              <p className="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2.5">Guide rapide</p>
              <div className="space-y-2">
                {([
                  [<Move size={11} />,                      'Glissez pour repositionner'],
                  [<span className="font-bold text-[11px]">↘</span>, 'Coin bas-droit pour redimensionner'],
                  [<X size={11} />,                         'Survolez → croix rouge pour retirer'],
                ] as [React.ReactNode, string][]).map(([icon, text], i) => (
                  <div key={i} className="flex items-center gap-2.5">
                    <span className="text-gray-400 w-4 shrink-0 flex justify-center">{icon}</span>
                    <p className="text-[11px] text-gray-500 leading-tight">{text}</p>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Footer */}
          <div className="p-4 border-t border-gray-100 space-y-2">
            {/* Warning if no signature */}
            {!sigData && (
              <div className="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-xl p-3 mb-1">
                <AlertCircle size={13} className="text-amber-500 shrink-0 mt-0.5" />
                <p className="text-[11px] text-amber-700 leading-relaxed">
                  Créez d'abord une signature dans{' '}
                  <a href="/profile" target="_blank" className="font-bold underline">Mon profil</a>.
                </p>
              </div>
            )}
            {/* Warning if signature not placed */}
            {sigData && !hasSig && (
              <div className="flex items-start gap-2 bg-orange-50 border border-orange-200 rounded-xl p-3 mb-1">
                <AlertCircle size={13} className="text-orange-500 shrink-0 mt-0.5" />
                <p className="text-[11px] text-orange-700">Placez votre signature sur le document.</p>
              </div>
            )}

            <Button
              className="w-full justify-center gap-2"
              loading={signing}
              disabled={!sigData || !hasSig || loading || pdfError}
              onClick={handleSign}
            >
              <Check size={15} />
              {signing ? 'Signature en cours…' : 'Signer le document'}
            </Button>
            <Button variant="secondary" className="w-full justify-center" onClick={onClose} disabled={signing}>
              Annuler
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
