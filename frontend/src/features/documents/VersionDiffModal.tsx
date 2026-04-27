import { useState, useEffect, useRef, useCallback } from 'react'
import {
  X, ChevronLeft, ChevronRight, Loader2, AlertCircle,
  GitCompare, ArrowLeftRight, FileText, Download,
} from 'lucide-react'
import * as pdfjsLib from 'pdfjs-dist'
import { api } from '@/lib/axios'
import { downloadAuthenticatedFile } from '@/lib/download'
import { formatBytes, formatDate } from '@/lib/utils'
import type { DocumentDTO, DocumentVersionDTO } from '@/types'

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

type BlobState =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'ready'; blob: Blob }
  | { status: 'error' }

// ── Helpers ───────────────────────────────────────────────────────────────────
const isPdf   = (m: string) => m === 'application/pdf'
const isImage = (m: string) => m.startsWith('image/')
const isText  = (m: string) =>
  m.startsWith('text/') || m === 'application/json' || m === 'application/xml'

function versionLabel(v: DocumentVersionDTO) {
  return `v${v.versionNumber} — ${v.originalFilename} (${formatBytes(v.fileSizeBytes)})`
}

// ── Text diff engine (pas de librairie externe) ───────────────────────────────
interface DiffLine {
  type: 'added' | 'removed' | 'unchanged'
  text: string
  lineA?: number
  lineB?: number
}

function computeDiff(a: string, b: string): DiffLine[] {
  const linesA = a.split('\n')
  const linesB = b.split('\n')
  const result: DiffLine[] = []
  const n = linesA.length, m = linesB.length
  // Simple patience-like diff using LCS length matrix
  const dp: number[][] = Array.from({ length: n + 1 }, () => new Array(m + 1).fill(0))
  for (let i = 1; i <= n; i++)
    for (let j = 1; j <= m; j++)
      dp[i][j] = linesA[i - 1] === linesB[j - 1]
        ? dp[i - 1][j - 1] + 1
        : Math.max(dp[i - 1][j], dp[i][j - 1])

  let i = n, j = m
  const ops: DiffLine[] = []
  while (i > 0 || j > 0) {
    if (i > 0 && j > 0 && linesA[i - 1] === linesB[j - 1]) {
      ops.push({ type: 'unchanged', text: linesA[i - 1], lineA: i, lineB: j })
      i--; j--
    } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
      ops.push({ type: 'added', text: linesB[j - 1], lineB: j })
      j--
    } else {
      ops.push({ type: 'removed', text: linesA[i - 1], lineA: i })
      i--
    }
  }
  return ops.reverse().concat(result)
}

// ── PDF Side-by-Side ──────────────────────────────────────────────────────────
function PdfPane({
  blob, label, page, totalPages, onTotalPages,
}: {
  blob: Blob; label: string; page: number; totalPages: number; onTotalPages: (n: number) => void
}) {
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const pdfRef    = useRef<pdfjsLib.PDFDocumentProxy | null>(null)
  const taskRef   = useRef<pdfjsLib.RenderTask | null>(null)
  const [rendering, setRendering] = useState(false)

  useEffect(() => {
    let cancelled = false
    const url = URL.createObjectURL(blob)
    pdfjsLib.getDocument({ url }).promise.then(pdf => {
      if (cancelled) return
      pdfRef.current = pdf
      onTotalPages(pdf.numPages)
    })
    return () => { cancelled = true; URL.revokeObjectURL(url); pdfRef.current?.destroy() }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [blob])

  useEffect(() => {
    const pdf = pdfRef.current
    const canvas = canvasRef.current
    if (!pdf || !canvas || page < 1 || page > totalPages) return
    taskRef.current?.cancel()
    setRendering(true)
    pdf.getPage(page).then(p => {
      const viewport = p.getViewport({ scale: 1.2 })
      const dpr = window.devicePixelRatio || 1
      const ctx = canvas.getContext('2d')!
      canvas.width  = viewport.width  * dpr
      canvas.height = viewport.height * dpr
      canvas.style.width  = `${viewport.width}px`
      canvas.style.height = `${viewport.height}px`
      ctx.scale(dpr, dpr)
      const task = p.render({ canvasContext: ctx, viewport, canvas })
      taskRef.current = task
      return task.promise
    }).catch(() => {}).finally(() => setRendering(false))
  }, [blob, page, totalPages])

  return (
    <div className="flex-1 flex flex-col min-w-0 border border-[#E8E8E8] rounded-xl overflow-hidden">
      <div className="px-3 py-2 bg-[#1A1A1A] flex items-center gap-2">
        <FileText size={13} className="text-[#F5A800] shrink-0" />
        <span className="text-xs text-white/70 truncate">{label}</span>
        {rendering && <Loader2 size={12} className="animate-spin text-[#F5A800] ml-auto shrink-0" />}
      </div>
      <div className="flex-1 overflow-auto bg-[#2A2A2A] flex items-start justify-center py-4">
        <canvas ref={canvasRef} className="shadow-xl rounded" />
      </div>
    </div>
  )
}

// ── Image slider ─────────────────────────────────────────────────────────────
function ImageSlider({ blobA, blobB, labelA, labelB }: {
  blobA: Blob; blobB: Blob; labelA: string; labelB: string
}) {
  const [urlA] = useState(() => URL.createObjectURL(blobA))
  const [urlB] = useState(() => URL.createObjectURL(blobB))
  const [pos, setPos]   = useState(50)
  const dragging = useRef(false)
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => () => { URL.revokeObjectURL(urlA); URL.revokeObjectURL(urlB) }, [urlA, urlB])

  const move = useCallback((clientX: number) => {
    const rect = containerRef.current?.getBoundingClientRect()
    if (!rect) return
    const pct = Math.min(100, Math.max(0, ((clientX - rect.left) / rect.width) * 100))
    setPos(pct)
  }, [])

  return (
    <div
      ref={containerRef}
      className="relative overflow-hidden rounded-xl cursor-col-resize select-none bg-[#2A2A2A] flex items-center justify-center h-full"
      onMouseMove={e => dragging.current && move(e.clientX)}
      onMouseUp={() => { dragging.current = false }}
      onMouseLeave={() => { dragging.current = false }}
      onTouchMove={e => move(e.touches[0].clientX)}
    >
      {/* Image B (droite = nouvelle) */}
      <img src={urlB} alt={labelB} className="max-h-full max-w-full object-contain absolute inset-0 m-auto" />
      {/* Image A (gauche = ancienne) clippée */}
      <div className="absolute inset-0 overflow-hidden" style={{ width: `${pos}%` }}>
        <img src={urlA} alt={labelA} className="max-h-full max-w-full object-contain absolute inset-0 m-auto" style={{ minWidth: `${100 / (pos / 100)}%` }} />
      </div>
      {/* Ligne de séparation */}
      <div
        className="absolute top-0 bottom-0 w-0.5 bg-[#F5A800] cursor-col-resize z-10"
        style={{ left: `${pos}%` }}
        onMouseDown={() => { dragging.current = true }}
        onTouchStart={() => { dragging.current = true }}
      >
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-8 h-8 bg-[#F5A800] rounded-full flex items-center justify-center shadow-lg">
          <ArrowLeftRight size={14} className="text-[#1A1A1A]" />
        </div>
      </div>
      {/* Labels */}
      <span className="absolute top-3 left-3 bg-black/60 text-white text-[10px] px-2 py-1 rounded-md">{labelA}</span>
      <span className="absolute top-3 right-3 bg-[#F5A800]/90 text-[#1A1A1A] text-[10px] px-2 py-1 rounded-md font-bold">{labelB}</span>
    </div>
  )
}

// ── Text diff viewer ──────────────────────────────────────────────────────────
function TextDiff({ blobA, blobB }: { blobA: Blob; blobB: Blob }) {
  const [lines, setLines] = useState<DiffLine[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    Promise.all([blobA.text(), blobB.text()]).then(([a, b]) => {
      setLines(computeDiff(a, b))
      setLoading(false)
    })
  }, [blobA, blobB])

  const added   = lines.filter(l => l.type === 'added').length
  const removed = lines.filter(l => l.type === 'removed').length

  if (loading) return (
    <div className="flex items-center justify-center h-full gap-2 text-[#888]">
      <Loader2 size={18} className="animate-spin text-[#F5A800]" />
      <span className="text-sm">Calcul des différences…</span>
    </div>
  )

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center gap-4 px-4 py-2 bg-[#1A1A1A] border-b border-[#2E2E2E] text-xs shrink-0">
        <span className="text-green-400 font-semibold">+{added} ajout{added !== 1 ? 's' : ''}</span>
        <span className="text-red-400 font-semibold">-{removed} suppression{removed !== 1 ? 's' : ''}</span>
        <span className="text-white/30">{lines.filter(l => l.type === 'unchanged').length} lignes inchangées</span>
      </div>
      <div className="flex-1 overflow-auto bg-[#1E1E1E] font-mono text-xs">
        <table className="w-full border-collapse">
          <tbody>
            {lines.map((line, i) => (
              <tr key={i} className={
                line.type === 'added'   ? 'bg-green-950/50' :
                line.type === 'removed' ? 'bg-red-950/50'   : ''
              }>
                <td className="w-10 text-right pr-3 py-0.5 text-[#444] select-none border-r border-[#2A2A2A]">
                  {line.lineA ?? ''}
                </td>
                <td className="w-10 text-right pr-3 py-0.5 text-[#444] select-none border-r border-[#2A2A2A]">
                  {line.lineB ?? ''}
                </td>
                <td className="w-5 text-center py-0.5 select-none">
                  {line.type === 'added'   && <span className="text-green-400 font-bold">+</span>}
                  {line.type === 'removed' && <span className="text-red-400 font-bold">-</span>}
                </td>
                <td className={`pl-2 py-0.5 whitespace-pre-wrap break-all ${
                  line.type === 'added'   ? 'text-green-300' :
                  line.type === 'removed' ? 'text-red-300'   :
                  'text-[#CCCCCC]'
                }`}>
                  {line.text || ' '}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

// ── Metadata diff ─────────────────────────────────────────────────────────────
function MetaDiff({ vA, vB, docId }: { vA: DocumentVersionDTO; vB: DocumentVersionDTO; docId: string }) {
  const rows = [
    { label: 'Nom du fichier', a: vA.originalFilename, b: vB.originalFilename },
    { label: 'Type MIME',      a: vA.mimeType,         b: vB.mimeType },
    { label: 'Taille',         a: formatBytes(vA.fileSizeBytes), b: formatBytes(vB.fileSizeBytes) },
    { label: 'Importé le',     a: formatDate(vA.uploadedAt),    b: formatDate(vB.uploadedAt) },
    { label: 'Commentaire',    a: vA.comment ?? '—',  b: vB.comment ?? '—' },
  ]
  return (
    <div className="flex flex-col gap-5 p-6 max-w-2xl mx-auto w-full">
      <div className="flex items-center gap-2 text-[#888] text-sm">
        <AlertCircle size={15} className="text-[#F5A800]" />
        Ce type de fichier ne peut pas être prévisualisé. Voici la comparaison des métadonnées.
      </div>
      <div className="bg-white border border-[#E8E8E8] rounded-xl overflow-hidden shadow-sm">
        <div className="grid grid-cols-3 bg-[#F5F5F5] text-[10px] font-bold uppercase tracking-widest text-[#AAA] px-5 py-2.5 border-b border-[#E8E8E8]">
          <span>Propriété</span>
          <span className="text-center">v{vA.versionNumber} (ancienne)</span>
          <span className="text-center text-[#F5A800]">v{vB.versionNumber} (nouvelle)</span>
        </div>
        {rows.map(row => {
          const changed = row.a !== row.b
          return (
            <div key={row.label} className={`grid grid-cols-3 px-5 py-3 border-b border-[#F0F0F0] last:border-0 ${changed ? 'bg-amber-50' : ''}`}>
              <span className="text-xs font-semibold text-[#555]">{row.label}</span>
              <span className={`text-xs text-center ${changed ? 'text-red-600 line-through opacity-60' : 'text-[#333]'}`}>{row.a}</span>
              <span className={`text-xs text-center font-medium ${changed ? 'text-green-700' : 'text-[#333]'}`}>{row.b}</span>
            </div>
          )
        })}
      </div>
      <div className="flex gap-3">
        {[vA, vB].map(v => (
          <button
            key={v.id}
            onClick={() => downloadAuthenticatedFile(`/api/documents/${docId}/download?version=${v.versionNumber}`, v.originalFilename)}
            className="flex items-center gap-2 px-4 py-2 rounded-xl border border-[#E8E8E8] bg-white text-sm text-[#555] hover:border-[#F5A800] hover:text-[#F5A800] transition-all"
          >
            <Download size={14} />
            Télécharger v{v.versionNumber}
          </button>
        ))}
      </div>
    </div>
  )
}

// ── Main Modal ────────────────────────────────────────────────────────────────
export function VersionDiffModal({ doc, open, onClose }: Props) {
  const versions = doc.versions ?? []

  const [idxA, setIdxA] = useState(0)
  const [idxB, setIdxB] = useState(1)
  const [blobA, setBlobA] = useState<BlobState>({ status: 'idle' })
  const [blobB, setBlobB] = useState<BlobState>({ status: 'idle' })
  const [pdfPage, setPdfPage]     = useState(1)
  const [totalPages, setTotalPages] = useState(0)

  // Sorted versions: oldest first
  const sorted = [...versions].sort((a, b) => a.versionNumber - b.versionNumber)

  const vA = sorted[idxA]
  const vB = sorted[idxB]

  const loadBlob = useCallback(async (
    version: DocumentVersionDTO,
    setter: (s: BlobState) => void,
  ) => {
    setter({ status: 'loading' })
    try {
      const res = await api.get(`/documents/${doc.id}/download?version=${version.versionNumber}`, {
        responseType: 'blob',
      })
      setter({ status: 'ready', blob: res.data as Blob })
    } catch {
      setter({ status: 'error' })
    }
  }, [doc.id])

  useEffect(() => {
    if (!open || !vA || !vB) return
    setPdfPage(1)
    loadBlob(vA, setBlobA)
    loadBlob(vB, setBlobB)
  }, [open, idxA, idxB, vA, vB, loadBlob])

  useEffect(() => {
    if (!open) return
    const h = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
      if (e.key === 'ArrowLeft')  setPdfPage(p => Math.max(1, p - 1))
      if (e.key === 'ArrowRight') setPdfPage(p => Math.min(totalPages, p + 1))
    }
    document.addEventListener('keydown', h)
    return () => document.removeEventListener('keydown', h)
  }, [open, onClose, totalPages])

  // Set default selection: compare last two versions
  useEffect(() => {
    if (open && sorted.length >= 2) {
      setIdxA(sorted.length - 2)
      setIdxB(sorted.length - 1)
      setPdfPage(1)
    }
  }, [open, sorted.length])

  if (!open) return null
  if (sorted.length < 2) return null

  const mimeA = vA?.mimeType ?? ''
  const mimeB = vB?.mimeType ?? ''
  const commonMime = mimeA === mimeB ? mimeA : 'application/octet-stream'

  const loadingEither = blobA.status === 'loading' || blobB.status === 'loading'
  const bothReady     = blobA.status === 'ready'   && blobB.status === 'ready'

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3">
      <div className="absolute inset-0 bg-black/80 backdrop-blur-md" onClick={onClose} />

      <div className="relative z-10 flex flex-col w-full max-w-7xl h-[95vh] bg-white rounded-2xl shadow-2xl overflow-hidden">

        {/* ── Header ── */}
        <div className="flex items-center justify-between px-5 py-3 border-b border-[#E8E8E8] bg-[#FAFAFA] shrink-0">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 bg-[#1A1A1A] rounded-lg flex items-center justify-center shrink-0">
              <GitCompare size={15} className="text-[#F5A800]" />
            </div>
            <div>
              <p className="text-sm font-bold text-[#1A1A1A]">Comparaison de versions</p>
              <p className="text-[11px] text-[#AAA]">{doc.title}</p>
            </div>
          </div>
          <button onClick={onClose} className="p-1.5 rounded-lg text-[#AAA] hover:text-[#555] hover:bg-[#F0F0F0] transition-colors">
            <X size={16} />
          </button>
        </div>

        {/* ── Version selectors ── */}
        <div className="flex items-center gap-3 px-5 py-3 border-b border-[#E8E8E8] bg-white shrink-0 flex-wrap">
          <div className="flex items-center gap-2 flex-1 min-w-[200px]">
            <span className="text-[10px] font-bold text-[#AAA] uppercase tracking-widest shrink-0">Ancienne</span>
            <select
              value={idxA}
              onChange={e => {
                const v = Number(e.target.value)
                if (v !== idxB) setIdxA(v)
              }}
              className="flex-1 text-sm border border-[#E8E8E8] rounded-lg px-2.5 py-1.5 bg-white text-[#333] focus:outline-none focus:ring-2 focus:ring-[#F5A800]"
            >
              {sorted.map((v, i) => (
                <option key={v.id} value={i} disabled={i === idxB}>
                  {versionLabel(v)}
                </option>
              ))}
            </select>
          </div>

          <ArrowLeftRight size={16} className="text-[#D0D0D0] shrink-0" />

          <div className="flex items-center gap-2 flex-1 min-w-[200px]">
            <span className="text-[10px] font-bold text-[#F5A800] uppercase tracking-widest shrink-0">Nouvelle</span>
            <select
              value={idxB}
              onChange={e => {
                const v = Number(e.target.value)
                if (v !== idxA) setIdxB(v)
              }}
              className="flex-1 text-sm border border-[#F5D580] rounded-lg px-2.5 py-1.5 bg-[#FFFDF5] text-[#333] focus:outline-none focus:ring-2 focus:ring-[#F5A800]"
            >
              {sorted.map((v, i) => (
                <option key={v.id} value={i} disabled={i === idxA}>
                  {versionLabel(v)}
                </option>
              ))}
            </select>
          </div>

          {/* PDF page nav */}
          {isPdf(commonMime) && totalPages > 1 && (
            <div className="flex items-center gap-2 ml-auto shrink-0">
              <button
                onClick={() => setPdfPage(p => Math.max(1, p - 1))}
                disabled={pdfPage <= 1}
                className="p-1.5 rounded-lg border border-[#E8E8E8] text-[#555] hover:bg-[#F5F5F5] disabled:opacity-30 transition-colors"
              >
                <ChevronLeft size={15} />
              </button>
              <span className="text-sm text-[#555] min-w-[70px] text-center">
                Page {pdfPage} / {totalPages}
              </span>
              <button
                onClick={() => setPdfPage(p => Math.min(totalPages, p + 1))}
                disabled={pdfPage >= totalPages}
                className="p-1.5 rounded-lg border border-[#E8E8E8] text-[#555] hover:bg-[#F5F5F5] disabled:opacity-30 transition-colors"
              >
                <ChevronRight size={15} />
              </button>
            </div>
          )}
        </div>

        {/* ── Body ── */}
        <div className="flex-1 overflow-hidden">
          {loadingEither && (
            <div className="flex flex-col items-center justify-center h-full gap-3 bg-[#F9F9F9]">
              <div className="w-12 h-12 rounded-full border-4 border-[#F5A800]/20 border-t-[#F5A800] animate-spin" />
              <p className="text-sm text-[#888]">Chargement des versions…</p>
            </div>
          )}

          {!loadingEither && (blobA.status === 'error' || blobB.status === 'error') && (
            <div className="flex flex-col items-center justify-center h-full gap-3 text-red-500">
              <AlertCircle size={36} />
              <p className="text-sm font-medium">Impossible de charger une ou plusieurs versions.</p>
            </div>
          )}

          {bothReady && isPdf(commonMime) && (
            <div className="flex gap-3 h-full p-3">
              <PdfPane
                blob={blobA.blob}
                label={`v${vA.versionNumber} — ${vA.originalFilename}`}
                page={pdfPage}
                totalPages={totalPages}
                onTotalPages={setTotalPages}
              />
              <PdfPane
                blob={blobB.blob}
                label={`v${vB.versionNumber} — ${vB.originalFilename} ★ Actuelle`}
                page={pdfPage}
                totalPages={totalPages}
                onTotalPages={n => { if (n > totalPages) setTotalPages(n) }}
              />
            </div>
          )}

          {bothReady && isImage(commonMime) && (
            <div className="p-4 h-full">
              <ImageSlider
                blobA={blobA.blob}
                blobB={blobB.blob}
                labelA={`v${vA.versionNumber}`}
                labelB={`v${vB.versionNumber}`}
              />
            </div>
          )}

          {bothReady && isText(commonMime) && (
            <TextDiff blobA={blobA.blob} blobB={blobB.blob} />
          )}

          {bothReady && !isPdf(commonMime) && !isImage(commonMime) && !isText(commonMime) && (
            <div className="overflow-auto h-full">
              <MetaDiff vA={vA} vB={vB} docId={doc.id} />
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
