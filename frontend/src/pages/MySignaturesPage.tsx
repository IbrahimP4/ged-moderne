import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  FileSignature, Clock, CheckCircle2, XCircle, MessageSquare,
  ChevronRight, ArrowDownToLine, ArrowUpFromLine,
} from 'lucide-react'
import { Link } from 'react-router-dom'
import { getSignatureRequests, signRequest, declineRequest } from '@/api/signatureRequests'
import { PageSpinner } from '@/components/ui/Spinner'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useAuthStore } from '@/store/auth'
import type { SignatureRequestDTO, SignatureRequestStatus } from '@/types'

const STATUS_CONFIG: Record<SignatureRequestStatus, { label: string; icon: React.ReactNode; badge: string }> = {
  pending:  { label: 'En attente',  icon: <Clock size={13} />,         badge: 'bg-[#FFF8E7] text-[#B37700] border border-[#F5A800]' },
  signed:   { label: 'Signé',       icon: <CheckCircle2 size={13} />,  badge: 'bg-[#E6F9EF] text-[#1A7A45] border border-[#34C472]' },
  declined: { label: 'Refusé',      icon: <XCircle size={13} />,       badge: 'bg-[#FEE9E9] text-[#9B1C1C] border border-[#F87171]' },
}

/** Modal de confirmation sign / decline — identique à l'admin */
function ActionModal({
  open, onClose, onConfirm, loading, type,
}: {
  open: boolean; onClose: () => void; onConfirm: (comment: string) => void; loading: boolean; type: 'sign' | 'decline'
}) {
  const [comment, setComment] = useState('')
  return (
    <Modal open={open} onClose={onClose} title={type === 'sign' ? 'Signer le document' : 'Refuser la demande'}>
      <div className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-[#333] mb-1.5">
            Commentaire <span className="text-[#AAAAAA] font-normal">(optionnel)</span>
          </label>
          <textarea
            autoFocus
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            rows={3}
            placeholder={type === 'sign' ? 'Note de signature…' : 'Raison du refus…'}
            className="w-full px-3.5 py-2.5 rounded-md border border-[#E0E0E0] text-sm focus:outline-none focus:ring-2 focus:ring-[#F5A800] focus:border-[#F5A800] resize-none"
          />
        </div>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>Annuler</Button>
          <Button
            variant={type === 'decline' ? 'danger' : 'primary'}
            loading={loading}
            onClick={() => onConfirm(comment)}
          >
            {type === 'sign' ? <><CheckCircle2 size={15} /> Signer</> : <><XCircle size={15} /> Refuser</>}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

/** Carte pour une demande REÇUE (l'utilisateur courant est le signataire) */
function IncomingCard({
  req,
  onSign,
  onDecline,
}: {
  req: SignatureRequestDTO
  onSign: () => void
  onDecline: () => void
}) {
  const cfg = STATUS_CONFIG[req.status]
  return (
    <div className="bg-white rounded-lg border border-[#E8E8E8] p-4 hover:border-[#F5A800] hover:shadow-sm transition-all">
      <div className="flex items-start gap-3">
        <div className={`w-9 h-9 rounded-md flex items-center justify-center shrink-0 ${
          req.status === 'pending' ? 'bg-[#FFF8E7]' : req.status === 'signed' ? 'bg-[#E6F9EF]' : 'bg-[#FEE9E9]'
        }`}>
          <FileSignature size={17} className={
            req.status === 'pending' ? 'text-[#F5A800]' : req.status === 'signed' ? 'text-[#1A7A45]' : 'text-[#9B1C1C]'
          } />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between gap-2">
            <Link
              to={`/documents/${req.documentId}`}
              className="font-semibold text-[#1A1A1A] hover:text-[#F5A800] transition-colors text-sm flex items-center gap-1 truncate"
            >
              {req.documentTitle}
              <ChevronRight size={13} className="text-[#AAA] shrink-0" />
            </Link>
            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium shrink-0 ${cfg.badge}`}>
              {cfg.icon}{cfg.label}
            </span>
          </div>
          <p className="text-xs text-[#888] mt-0.5">
            Demandé par <span className="font-medium text-[#555]">{req.requesterUsername}</span>
            {' · '}
            {new Date(req.requestedAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
          </p>

          {req.message && (
            <div className="mt-2 flex items-start gap-1.5 bg-[#F4F4F4] rounded-md px-2.5 py-1.5">
              <MessageSquare size={13} className="text-[#AAA] shrink-0 mt-0.5" />
              <p className="text-xs text-[#555] italic">"{req.message}"</p>
            </div>
          )}

          {req.comment && (
            <div className={`mt-1.5 flex items-start gap-1.5 rounded-md px-2.5 py-1.5 ${
              req.status === 'signed' ? 'bg-[#E6F9EF]' : 'bg-[#FEE9E9]'
            }`}>
              <MessageSquare size={13} className={`shrink-0 mt-0.5 ${req.status === 'signed' ? 'text-[#1A7A45]' : 'text-[#9B1C1C]'}`} />
              <p className={`text-xs italic ${req.status === 'signed' ? 'text-[#1A7A45]' : 'text-[#9B1C1C]'}`}>
                Votre réponse : {req.comment}
              </p>
            </div>
          )}

          {req.resolvedAt && (
            <p className="text-xs text-[#AAAAAA] mt-1.5">
              Traité le {new Date(req.resolvedAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
            </p>
          )}
        </div>
      </div>

      {/* Boutons d'action — uniquement pour les demandes en attente */}
      {req.status === 'pending' && (
        <div className="flex gap-2 mt-4 pt-4 border-t border-[#F0F0F0]">
          <button
            onClick={onSign}
            className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md bg-[#F5A800] text-[#1A1A1A] text-xs font-bold hover:bg-[#e09900] transition-colors"
          >
            <CheckCircle2 size={14} /> Signer
          </button>
          <button
            onClick={onDecline}
            className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md bg-[#FEE2E2] text-[#B91C1C] border border-[#FECACA] text-xs font-bold hover:bg-[#FECACA] transition-colors"
          >
            <XCircle size={14} /> Refuser
          </button>
        </div>
      )}
    </div>
  )
}

/** Carte pour une demande ENVOYÉE (l'utilisateur courant est le demandeur) */
function OutgoingCard({ req }: { req: SignatureRequestDTO }) {
  const cfg = STATUS_CONFIG[req.status]
  return (
    <div className="bg-white rounded-lg border border-[#E8E8E8] p-4 hover:border-[#F5A800] transition-colors">
      <div className="flex items-start gap-3">
        <div className={`w-9 h-9 rounded-md flex items-center justify-center shrink-0 ${
          req.status === 'pending' ? 'bg-[#FFF8E7]' : req.status === 'signed' ? 'bg-[#E6F9EF]' : 'bg-[#FEE9E9]'
        }`}>
          <FileSignature size={17} className={
            req.status === 'pending' ? 'text-[#F5A800]' : req.status === 'signed' ? 'text-[#1A7A45]' : 'text-[#9B1C1C]'
          } />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between gap-2">
            <Link
              to={`/documents/${req.documentId}`}
              className="font-semibold text-[#1A1A1A] hover:text-[#F5A800] transition-colors text-sm flex items-center gap-1 truncate"
            >
              {req.documentTitle}
              <ChevronRight size={13} className="text-[#AAA] shrink-0" />
            </Link>
            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium shrink-0 ${cfg.badge}`}>
              {cfg.icon}{cfg.label}
            </span>
          </div>
          <p className="text-xs text-[#888] mt-0.5">
            Signataire : <span className="font-medium text-[#555]">{req.signerUsername}</span>
            {' · '}
            {new Date(req.requestedAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
          </p>
          {req.message && (
            <div className="mt-2 flex items-start gap-1.5 bg-[#F4F4F4] rounded-md px-2.5 py-1.5">
              <MessageSquare size={13} className="text-[#AAA] shrink-0 mt-0.5" />
              <p className="text-xs text-[#555] italic">"{req.message}"</p>
            </div>
          )}
          {req.comment && (
            <div className={`mt-1.5 flex items-start gap-1.5 rounded-md px-2.5 py-1.5 ${
              req.status === 'signed' ? 'bg-[#E6F9EF]' : 'bg-[#FEE9E9]'
            }`}>
              <MessageSquare size={13} className={`shrink-0 mt-0.5 ${req.status === 'signed' ? 'text-[#1A7A45]' : 'text-[#9B1C1C]'}`} />
              <p className={`text-xs italic ${req.status === 'signed' ? 'text-[#1A7A45]' : 'text-[#9B1C1C]'}`}>
                Réponse : {req.comment}
              </p>
            </div>
          )}
          {req.resolvedAt && (
            <p className="text-xs text-[#AAAAAA] mt-1.5">
              Traité le {new Date(req.resolvedAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
            </p>
          )}
        </div>
      </div>
    </div>
  )
}

export function MySignaturesPage() {
  usePageTitle('Mes signatures')

  const { username } = useAuthStore()
  const qc = useQueryClient()
  const [actionModal, setActionModal] = useState<{ id: string; type: 'sign' | 'decline' } | null>(null)

  const { data: requests, isLoading } = useQuery({
    queryKey: ['my-signature-requests'],
    queryFn: getSignatureRequests,
    refetchInterval: 30_000,
  })

  const signMutation = useMutation({
    mutationFn: ({ id, comment }: { id: string; comment: string }) =>
      signRequest(id, comment || undefined),
    onSuccess: () => {
      toast.success('Document signé avec succès.')
      qc.invalidateQueries({ queryKey: ['my-signature-requests'] })
      qc.invalidateQueries({ queryKey: ['signature-pending-count'] })
      setActionModal(null)
    },
    onError: () => toast.error('Impossible de signer.'),
  })

  const declineMutation = useMutation({
    mutationFn: ({ id, comment }: { id: string; comment: string }) =>
      declineRequest(id, comment || undefined),
    onSuccess: () => {
      toast.success('Demande refusée.')
      qc.invalidateQueries({ queryKey: ['my-signature-requests'] })
      qc.invalidateQueries({ queryKey: ['signature-pending-count'] })
      setActionModal(null)
    },
    onError: () => toast.error('Impossible de refuser.'),
  })

  if (isLoading) return <PageSpinner />

  const all = requests ?? []

  // Demandes REÇUES : l'utilisateur courant est le signataire désigné
  const incoming = all.filter((r) => r.signerUsername === username)
  const incomingPending = incoming.filter((r) => r.status === 'pending')
  const incomingResolved = incoming.filter((r) => r.status !== 'pending')

  // Demandes ENVOYÉES : l'utilisateur courant est le demandeur
  const outgoing = all.filter((r) => r.requesterUsername === username)

  const totalPending = incomingPending.length

  return (
    <div className="min-h-full bg-[#F4F4F4]">
      <div className="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-8">

        {/* Header card */}
        <div className="bg-white rounded-lg border border-[#E8E8E8] overflow-hidden mb-6">
          <div className="bg-[#1A1A1A] px-6 py-5 flex items-center gap-4">
            <div className="w-10 h-10 bg-[#F5A800] rounded-md flex items-center justify-center shrink-0">
              <FileSignature size={22} className="text-[#1A1A1A]" />
            </div>
            <div className="flex-1">
              <h1 className="text-xl font-bold text-white">Mes signatures</h1>
              <p className="text-sm text-white/60 mt-0.5">
                {totalPending > 0
                  ? <span className="text-[#F5A800] font-semibold">{totalPending} demande{totalPending > 1 ? 's' : ''} à traiter</span>
                  : 'Aucune action requise'}
              </p>
            </div>
            <div className="w-1 h-10 bg-[#F5A800] rounded-full shrink-0" />
          </div>

          {/* Stats strip */}
          {all.length > 0 && (
            <div className="px-6 py-3 flex items-center gap-6 border-t border-[#E8E8E8] flex-wrap">
              <div className="flex items-center gap-2">
                <ArrowDownToLine size={13} className="text-[#F5A800]" />
                <span className="text-xs text-[#888]">
                  <span className="font-bold text-[#1A1A1A]">{incoming.length}</span> reçue{incoming.length !== 1 ? 's' : ''}
                </span>
              </div>
              <div className="flex items-center gap-2">
                <ArrowUpFromLine size={13} className="text-[#888]" />
                <span className="text-xs text-[#888]">
                  <span className="font-bold text-[#1A1A1A]">{outgoing.length}</span> envoyée{outgoing.length !== 1 ? 's' : ''}
                </span>
              </div>
              <div className="flex items-center gap-2">
                <CheckCircle2 size={13} className="text-[#34C472]" />
                <span className="text-xs text-[#888]">
                  <span className="font-bold text-[#1A1A1A]">{all.filter(r => r.status === 'signed').length}</span> signée{all.filter(r => r.status === 'signed').length !== 1 ? 's' : ''}
                </span>
              </div>
            </div>
          )}
        </div>

        {all.length === 0 ? (
          <div className="bg-white rounded-lg border border-[#E8E8E8] text-center py-20">
            <div className="w-14 h-14 bg-[#F4F4F4] rounded-lg flex items-center justify-center mx-auto mb-4">
              <FileSignature size={28} className="text-[#CCC]" />
            </div>
            <p className="text-[#555] font-medium">Aucune demande de signature</p>
            <p className="text-[#AAA] text-sm mt-1">
              Ouvrez un document et cliquez sur "Demander une signature"
            </p>
          </div>
        ) : (
          <div className="space-y-8">

            {/* ─── DEMANDES REÇUES ──────────────────────────────────── */}
            {incoming.length > 0 && (
              <section>
                <div className="flex items-center gap-2 mb-3">
                  <ArrowDownToLine size={14} className="text-[#F5A800]" />
                  <h2 className="text-xs font-bold text-[#555] uppercase tracking-widest">
                    Demandes reçues — à traiter ({incoming.length})
                  </h2>
                </div>

                {incomingPending.length > 0 && (
                  <div className="space-y-2 mb-4">
                    {incomingPending.map((r) => (
                      <IncomingCard
                        key={r.id}
                        req={r}
                        onSign={() => setActionModal({ id: r.id, type: 'sign' })}
                        onDecline={() => setActionModal({ id: r.id, type: 'decline' })}
                      />
                    ))}
                  </div>
                )}

                {incomingResolved.length > 0 && (
                  <>
                    {incomingPending.length > 0 && (
                      <div className="flex items-center gap-2 mb-2 mt-4">
                        <div className="w-1 h-4 bg-[#E8E8E8] rounded-full" />
                        <p className="text-xs text-[#AAA] uppercase tracking-wider font-medium">Historique</p>
                      </div>
                    )}
                    <div className="space-y-2">
                      {incomingResolved.map((r) => (
                        <IncomingCard
                          key={r.id}
                          req={r}
                          onSign={() => setActionModal({ id: r.id, type: 'sign' })}
                          onDecline={() => setActionModal({ id: r.id, type: 'decline' })}
                        />
                      ))}
                    </div>
                  </>
                )}
              </section>
            )}

            {/* ─── DEMANDES ENVOYÉES ────────────────────────────────── */}
            {outgoing.length > 0 && (
              <section>
                <div className="flex items-center gap-2 mb-3">
                  <ArrowUpFromLine size={14} className="text-[#888]" />
                  <h2 className="text-xs font-bold text-[#555] uppercase tracking-widest">
                    Demandes envoyées ({outgoing.length})
                  </h2>
                </div>
                <div className="space-y-2">
                  {outgoing.map((r) => <OutgoingCard key={r.id} req={r} />)}
                </div>
              </section>
            )}
          </div>
        )}

        {/* Modal sign / decline */}
        {actionModal && (
          <ActionModal
            open={true}
            onClose={() => setActionModal(null)}
            type={actionModal.type}
            loading={signMutation.isPending || declineMutation.isPending}
            onConfirm={(comment) => {
              if (actionModal.type === 'sign') {
                signMutation.mutate({ id: actionModal.id, comment })
              } else {
                declineMutation.mutate({ id: actionModal.id, comment })
              }
            }}
          />
        )}
      </div>
    </div>
  )
}
