import { useRef, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { PenLine, Upload, Trash2, Save, Building2, User, CheckCircle2, RefreshCw } from 'lucide-react'
import {
  getProfileSignature, saveProfileSignature, deleteProfileSignature,
  getCompanyStamp, saveCompanyStamp, deleteCompanyStamp,
} from '@/api/profileSignature'
import { SignatureCanvas, type SignatureCanvasHandle } from '@/features/signatures/SignatureCanvas'
import { Button } from '@/components/ui/Button'
import { useAuthStore } from '@/store/auth'
import { usePageTitle } from '@/hooks/usePageTitle'

// ── Signature manager ─────────────────────────────────────────────────────────

function SignatureManager({ title, subtitle, value, onSave, onDelete, loading, deleteLoading }: {
  title: string
  subtitle: string
  value: { dataUrl: string; updatedAt: string } | null | undefined
  onSave: (dataUrl: string, type: 'drawn' | 'uploaded') => void
  onDelete: () => void
  loading: boolean
  deleteLoading: boolean
}) {
  const [mode, setMode] = useState<'draw' | 'upload'>('draw')
  const [editing, setEditing] = useState(false)
  const canvasRef = useRef<SignatureCanvasHandle>(null)
  const fileRef   = useRef<HTMLInputElement>(null)
  const [uploadPreview, setUploadPreview] = useState<string | null>(null)

  const handleSaveDraw = () => {
    if (canvasRef.current?.isEmpty()) {
      toast.error('La zone de signature est vide.')
      return
    }
    const dataUrl = canvasRef.current!.toDataURL()
    onSave(dataUrl, 'drawn')
    setEditing(false)
  }

  const handleSaveUpload = () => {
    if (!uploadPreview) {
      toast.error('Veuillez choisir une image.')
      return
    }
    onSave(uploadPreview, 'uploaded')
    setEditing(false)
    setUploadPreview(null)
  }

  const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    if (!file.type.startsWith('image/')) { toast.error('Fichier image requis.'); return }
    if (file.size > 2_000_000) { toast.error('Image trop grande (max 2 MB).'); return }
    const reader = new FileReader()
    reader.onload = (ev) => setUploadPreview(ev.target?.result as string)
    reader.readAsDataURL(file)
    e.target.value = ''
  }

  return (
    <div className="bg-white rounded-lg border border-[#E8E8E8] overflow-hidden">
      <div className="px-6 py-5 border-b border-[#E8E8E8]">
        <div className="flex items-center gap-2 mb-1">
          <div className="w-1 h-5 bg-[#F5A800] rounded-full" />
          <h2 className="text-xs font-bold text-[#555] uppercase tracking-widest">{title}</h2>
        </div>
        <p className="text-sm text-[#888] mt-1 pl-3">{subtitle}</p>
      </div>

      <div className="p-6">
        {/* Aperçu signature actuelle */}
        {!editing && value && (
          <div className="mb-5">
            <p className="text-xs font-bold text-[#AAA] uppercase tracking-widest mb-3">Signature actuelle</p>
            <div className="relative inline-block group">
              <div className="border-2 border-dashed border-[#E8E8E8] rounded-lg p-4 bg-[#F4F4F4] min-w-[260px] hover:border-[#F5A800] transition-colors">
                <img
                  src={value.dataUrl}
                  alt="Signature"
                  className="max-h-24 max-w-full object-contain mx-auto"
                  style={{ filter: 'drop-shadow(0 1px 2px rgba(0,0,0,0.1))' }}
                />
              </div>
              <p className="text-xs text-[#AAA] mt-2">
                Mise à jour le {new Date(value.updatedAt).toLocaleDateString('fr-FR', {
                  day: 'numeric', month: 'long', year: 'numeric',
                })}
              </p>
            </div>
            <div className="flex gap-2 mt-4">
              <Button size="sm" variant="secondary" onClick={() => { setEditing(true); setUploadPreview(null) }}>
                <RefreshCw size={14} />Modifier
              </Button>
              <Button size="sm" variant="danger" onClick={onDelete} loading={deleteLoading}>
                <Trash2 size={14} />Supprimer
              </Button>
            </div>
          </div>
        )}

        {/* État vide */}
        {!editing && !value && (
          <div
            onClick={() => setEditing(true)}
            className="border-2 border-dashed border-[#E8E8E8] rounded-lg p-10 text-center cursor-pointer hover:border-[#F5A800] hover:bg-[#FFF8E7] transition-all mb-4 group"
          >
            <PenLine size={32} className="text-[#CCC] group-hover:text-[#F5A800] mx-auto mb-3 transition-colors" />
            <p className="text-sm font-medium text-[#888] group-hover:text-[#1A1A1A]">Créer ma signature</p>
            <p className="text-xs text-[#AAA] mt-1">Dessiner ou importer une image</p>
          </div>
        )}

        {/* Éditeur */}
        {editing && (
          <div>
            {/* Tabs draw / upload */}
            <div className="flex gap-1 mb-5 bg-[#F4F4F4] p-1 rounded-md w-fit">
              <button
                onClick={() => setMode('draw')}
                className={`flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all ${
                  mode === 'draw'
                    ? 'bg-white shadow-sm text-[#1A1A1A] border border-[#E8E8E8]'
                    : 'text-[#888] hover:text-[#555]'
                }`}
              >
                <PenLine size={15} />Dessiner
              </button>
              <button
                onClick={() => setMode('upload')}
                className={`flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all ${
                  mode === 'upload'
                    ? 'bg-white shadow-sm text-[#1A1A1A] border border-[#E8E8E8]'
                    : 'text-[#888] hover:text-[#555]'
                }`}
              >
                <Upload size={15} />Importer
              </button>
            </div>

            {mode === 'draw' && (
              <div>
                <div className="border-2 border-[#E8E8E8] rounded-lg overflow-hidden bg-white relative">
                  {/* Guide lines */}
                  <div className="absolute inset-0 pointer-events-none">
                    <div className="absolute bottom-10 left-6 right-6 h-px bg-[#E8E8E8]" />
                    <p className="absolute bottom-2 left-6 text-[10px] text-[#CCC]">Signez ici</p>
                  </div>
                  <SignatureCanvas ref={canvasRef} width={460} height={180} className="w-full" />
                </div>
                <div className="flex items-center gap-2 mt-3">
                  <Button size="sm" loading={loading} onClick={handleSaveDraw}>
                    <Save size={14} />Enregistrer
                  </Button>
                  <Button size="sm" variant="secondary" onClick={() => canvasRef.current?.clear()}>
                    Effacer
                  </Button>
                  <Button size="sm" variant="ghost" onClick={() => { setEditing(false); setUploadPreview(null) }}>
                    Annuler
                  </Button>
                </div>
              </div>
            )}

            {mode === 'upload' && (
              <div>
                <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={handleFile} />
                {uploadPreview ? (
                  <div className="border-2 border-[#F5A800] rounded-lg p-6 bg-[#FFF8E7] text-center">
                    <img src={uploadPreview} alt="Aperçu" className="max-h-28 max-w-full object-contain mx-auto" />
                    <button onClick={() => fileRef.current?.click()}
                      className="text-xs text-[#F5A800] hover:underline mt-3 block mx-auto font-medium">
                      Changer l'image
                    </button>
                  </div>
                ) : (
                  <button
                    onClick={() => fileRef.current?.click()}
                    className="w-full border-2 border-dashed border-[#E8E8E8] rounded-lg p-10 text-center hover:border-[#F5A800] hover:bg-[#FFF8E7] transition-all"
                  >
                    <Upload size={28} className="text-[#CCC] mx-auto mb-2" />
                    <p className="text-sm font-medium text-[#888]">Cliquez pour importer</p>
                    <p className="text-xs text-[#AAA] mt-1">PNG, JPG, SVG — fond transparent recommandé</p>
                  </button>
                )}
                <div className="flex items-center gap-2 mt-3">
                  <Button size="sm" loading={loading} disabled={!uploadPreview} onClick={handleSaveUpload}>
                    <Save size={14} />Enregistrer
                  </Button>
                  <Button size="sm" variant="ghost" onClick={() => { setEditing(false); setUploadPreview(null) }}>
                    Annuler
                  </Button>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}

// ── Page principale ───────────────────────────────────────────────────────────

export function ProfilePage() {
  usePageTitle('Mon profil')
  const qc      = useAuthStore()
  const isAdmin = qc.isAdmin
  const username = qc.username
  const queryClient = useQueryClient()

  const { data: sig, isLoading: sigLoading } = useQuery({
    queryKey: ['profile-signature'],
    queryFn: getProfileSignature,
  })

  const { data: stamp, isLoading: stampLoading } = useQuery({
    queryKey: ['company-stamp'],
    queryFn: getCompanyStamp,
    enabled: isAdmin,
  })

  const saveSigMutation = useMutation({
    mutationFn: ({ dataUrl, type }: { dataUrl: string; type: 'drawn' | 'uploaded' }) =>
      saveProfileSignature(dataUrl, type),
    onSuccess: () => {
      toast.success('Signature enregistrée ✓')
      queryClient.invalidateQueries({ queryKey: ['profile-signature'] })
    },
    onError: () => toast.error('Impossible d\'enregistrer la signature.'),
  })

  const deleteSigMutation = useMutation({
    mutationFn: deleteProfileSignature,
    onSuccess: () => {
      toast.success('Signature supprimée.')
      queryClient.invalidateQueries({ queryKey: ['profile-signature'] })
    },
    onError: () => toast.error('Impossible de supprimer la signature.'),
  })

  const saveStampMutation = useMutation({
    mutationFn: (dataUrl: string) => saveCompanyStamp(dataUrl),
    onSuccess: () => {
      toast.success('Tampon entreprise mis à jour ✓')
      queryClient.invalidateQueries({ queryKey: ['company-stamp'] })
    },
    onError: () => toast.error('Impossible d\'enregistrer le tampon.'),
  })

  const deleteStampMutation = useMutation({
    mutationFn: deleteCompanyStamp,
    onSuccess: () => {
      toast.success('Tampon supprimé.')
      queryClient.invalidateQueries({ queryKey: ['company-stamp'] })
    },
    onError: () => toast.error('Impossible de supprimer le tampon.'),
  })

  return (
    <div className="min-h-full bg-[#F4F4F4]">
      <div className="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-8">

        {/* Profile card header */}
        <div className="bg-white rounded-lg border border-[#E8E8E8] overflow-hidden mb-6">
          {/* Dark top stripe with yellow accent */}
          <div className="bg-[#1A1A1A] px-6 py-5 flex items-center gap-4">
            <div className="w-14 h-14 rounded-lg bg-[#F5A800] flex items-center justify-center text-[#1A1A1A] text-2xl font-bold shrink-0">
              {username?.[0]?.toUpperCase() ?? '?'}
            </div>
            <div className="flex-1 min-w-0">
              <h1 className="text-xl font-bold text-white truncate">{username}</h1>
              <div className="flex items-center gap-2 mt-1">
                {isAdmin ? (
                  <span className="inline-flex items-center gap-1.5 text-xs bg-[#F5A800] text-[#1A1A1A] px-2.5 py-1 rounded-md font-bold uppercase tracking-wide">
                    <Building2 size={12} />Administrateur
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1.5 text-xs bg-white/10 text-white px-2.5 py-1 rounded-md font-medium">
                    <User size={12} />Utilisateur
                  </span>
                )}
              </div>
            </div>
            <div className="w-1 h-10 bg-[#F5A800] rounded-full shrink-0" />
          </div>

          {/* Profile fields */}
          <div className="px-6 py-4 grid grid-cols-2 gap-x-8 gap-y-4">
            <div>
              <p className="text-xs font-bold text-[#AAA] uppercase tracking-widest mb-0.5">Nom d'utilisateur</p>
              <p className="text-sm text-[#1A1A1A] font-medium">{username}</p>
            </div>
            <div>
              <p className="text-xs font-bold text-[#AAA] uppercase tracking-widest mb-0.5">Rôle</p>
              <p className="text-sm text-[#1A1A1A] font-medium">{isAdmin ? 'Administrateur' : 'Utilisateur'}</p>
            </div>
          </div>
        </div>

        <div className="space-y-6">
          {/* Signature personnelle */}
          {!sigLoading && (
            <SignatureManager
              title="Ma signature"
              subtitle="Apposée sur les documents que vous signez électroniquement"
              value={sig ?? null}
              loading={saveSigMutation.isPending}
              deleteLoading={deleteSigMutation.isPending}
              onSave={(dataUrl, type) => saveSigMutation.mutate({ dataUrl, type })}
              onDelete={() => deleteSigMutation.mutate()}
            />
          )}

          {/* Tampon entreprise (admin uniquement) */}
          {isAdmin && !stampLoading && (
            <SignatureManager
              title="Tampon de l'entreprise"
              subtitle="Apposé en complément de votre signature sur les documents officiels"
              value={stamp ?? null}
              loading={saveStampMutation.isPending}
              deleteLoading={deleteStampMutation.isPending}
              onSave={(dataUrl) => saveStampMutation.mutate(dataUrl)}
              onDelete={() => deleteStampMutation.mutate()}
            />
          )}

          {/* Info sécurité */}
          <div className="bg-white border border-[#E8E8E8] rounded-lg p-5 flex items-start gap-3">
            <div className="w-8 h-8 bg-[#F5A800] rounded-md flex items-center justify-center shrink-0 mt-0.5">
              <CheckCircle2 size={16} className="text-[#1A1A1A]" />
            </div>
            <div>
              <p className="text-sm font-bold text-[#1A1A1A]">Signature sécurisée</p>
              <p className="text-xs text-[#888] mt-1 leading-relaxed">
                Votre signature est stockée de façon sécurisée et n'est utilisée que lors de vos actions explicites de signature. Elle est intégrée directement dans le PDF signé.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
