import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  FileSignature, Clock, CheckCircle2, XCircle, MessageSquare, ChevronRight, Filter,
} from 'lucide-react'
import { Link } from 'react-router-dom'
import { getSignatureRequests, signRequest, declineRequest } from '@/api/signatureRequests'
import { PageSpinner } from '@/components/ui/Spinner'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { usePageTitle } from '@/hooks/usePageTitle'
import type { SignatureRequestDTO, SignatureRequestStatus } from '@/types'

const STATUS_CONFIG: Record<SignatureRequestStatus, { label: string; icon: React.ReactNode; badge: string }> = {
  pending:  { label: 'En attente',  icon: <Clock size={14} />,         badge: 'bg-[#FFF3CC] text-[#A07000] border border-[#F5D580]' },
  signed:   { label: 'Signé',       icon: <CheckCircle2 size={14} />,  badge: 'bg-green-50 text-green-700 border border-green-100' },
  declined: { label: 'Refusé',      icon: <XCircle size={14} />,       badge: 'bg-[#FEE2E2] text-[#B91C1C] border border-[#FECACA]' },
}

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
          <label className="block text-sm font-medium text-primary mb-1.5">
            Commentaire <span className="text-faint font-normal">(optionnel)</span>
          </label>
          <textarea
            autoFocus
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            rows={3}
            placeholder={type === 'sign' ? 'Note de signature…' : 'Raison du refus…'}
            className="w-full px-3.5 py-2.5 rounded-md border border-strong text-sm focus:outline-none focus:ring-2 focus:ring-[#F5A800] focus:border-[#F5A800] resize-none"
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

export function SignatureRequestsPage() {
  usePageTitle('Demandes de signature')

  const qc = useQueryClient()
  const [statusFilter, setStatusFilter] = useState<SignatureRequestStatus | 'all'>('all')
  const [actionModal, setActionModal] = useState<{ id: string; type: 'sign' | 'decline' } | null>(null)

  const { data: requests, isLoading } = useQuery({
    queryKey: ['signature-requests'],
    queryFn: getSignatureRequests,
    refetchInterval: 30_000,
  })

  const signMutation = useMutation({
    mutationFn: ({ id, comment }: { id: string; comment: string }) =>
      signRequest(id, comment || undefined),
    onSuccess: () => {
      toast.success('Document signé avec succès.')
      qc.invalidateQueries({ queryKey: ['signature-requests'] })
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
      qc.invalidateQueries({ queryKey: ['signature-requests'] })
      qc.invalidateQueries({ queryKey: ['signature-pending-count'] })
      setActionModal(null)
    },
    onError: () => toast.error('Impossible de refuser.'),
  })

  if (isLoading) return <PageSpinner />

  const all = requests ?? []
  const filtered = statusFilter === 'all' ? all : all.filter((r) => r.status === statusFilter)
  const pendingCount = all.filter((r) => r.status === 'pending').length

  return (
    <div className="min-h-screen bg-page">
      <div className="max-w-5xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-1 h-5 bg-[#F5A800] rounded-full" />
            <h1 className="text-xs font-bold text-secondary uppercase tracking-widest">Demandes de signature</h1>
          </div>
          <p className="text-xs text-muted ml-4">
            {pendingCount > 0
              ? <span className="text-[#A07000] font-semibold">{pendingCount} demande{pendingCount > 1 ? 's' : ''} en attente</span>
              : 'Toutes les demandes sont traitées'}
          </p>
        </div>

        {/* Filtres */}
        <div className="flex items-center gap-2 mb-6 flex-wrap">
          <Filter size={15} className="text-faint" />
          {(['all', 'pending', 'signed', 'declined'] as const).map((s) => (
            <button
              key={s}
              onClick={() => setStatusFilter(s)}
              className={`px-3 py-1.5 rounded-full text-xs font-medium transition-colors border ${
                statusFilter === s
                  ? 'bg-[#F5A800] text-primary border-[#F5A800]'
                  : 'bg-card text-secondary border-strong hover:border-[#F5A800] hover:text-[#A07000]'
              }`}
            >
              {s === 'all'
                ? `Tout (${all.length})`
                : `${STATUS_CONFIG[s].label} (${all.filter(r => r.status === s).length})`}
            </button>
          ))}
        </div>

        {/* Liste */}
        {filtered.length === 0 ? (
          <div className="bg-card rounded-lg border border-base text-center py-20">
            <CheckCircle2 size={40} className="text-ghost mx-auto mb-3" />
            <p className="text-muted font-medium text-sm">Aucune demande</p>
          </div>
        ) : (
          <div className="space-y-3">
            {filtered.map((req) => (
              <RequestCard
                key={req.id}
                req={req}
                onSign={() => setActionModal({ id: req.id, type: 'sign' })}
                onDecline={() => setActionModal({ id: req.id, type: 'decline' })}
              />
            ))}
          </div>
        )}

        {/* Modal d'action */}
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

function RequestCard({
  req, onSign, onDecline,
}: {
  req: SignatureRequestDTO
  onSign: () => void
  onDecline: () => void
}) {
  const cfg = STATUS_CONFIG[req.status]

  return (
    <div className="bg-card rounded-lg border border-base p-5 hover:border-[#F5A800] hover:shadow-sm transition-all">
      <div className="flex items-start gap-4">
        {/* Icone statut */}
        <div className={`w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ${
          req.status === 'pending'
            ? 'bg-[#FFF3CC]'
            : req.status === 'signed'
            ? 'bg-green-50'
            : 'bg-[#FEE2E2]'
        }`}>
          <FileSignature size={20} className={
            req.status === 'pending'
              ? 'text-[#A07000]'
              : req.status === 'signed'
              ? 'text-green-600'
              : 'text-[#B91C1C]'
          } />
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-3">
            <div>
              <Link
                to={`/documents/${req.documentId}`}
                className="font-semibold text-primary hover:text-[#A07000] transition-colors flex items-center gap-1 text-sm"
              >
                {req.documentTitle}
                <ChevronRight size={14} className="text-ghost" />
              </Link>
              <p className="text-xs text-muted mt-0.5">
                Demandé par <span className="font-medium text-secondary">{req.requesterUsername}</span>
                {' · '}
                {new Date(req.requestedAt).toLocaleDateString('fr-FR', {
                  day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
                })}
              </p>
            </div>
            <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium shrink-0 ${cfg.badge}`}>
              {cfg.icon}{cfg.label}
            </span>
          </div>

          {req.message && (
            <div className="mt-3 flex items-start gap-2 bg-muted rounded-md px-3 py-2 border border-base">
              <MessageSquare size={14} className="text-faint shrink-0 mt-0.5" />
              <p className="text-sm text-secondary italic">"{req.message}"</p>
            </div>
          )}

          {req.comment && (
            <div className={`mt-2 flex items-start gap-2 rounded-md px-3 py-2 ${
              req.status === 'signed'
                ? 'bg-green-50 border border-green-100'
                : 'bg-[#FEE2E2] border border-[#FECACA]'
            }`}>
              <MessageSquare size={14} className={`shrink-0 mt-0.5 ${req.status === 'signed' ? 'text-green-500' : 'text-[#B91C1C]'}`} />
              <p className={`text-sm italic ${req.status === 'signed' ? 'text-green-700' : 'text-[#B91C1C]'}`}>
                {req.comment}
              </p>
            </div>
          )}

          {req.resolvedAt && (
            <p className="text-xs text-faint mt-2">
              Traité le {new Date(req.resolvedAt).toLocaleDateString('fr-FR', {
                day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
              })}
            </p>
          )}
        </div>
      </div>

      {req.status === 'pending' && (
        <div className="flex gap-2 mt-4 pt-4 border-t border-muted">
          <button
            onClick={onSign}
            className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md bg-[#F5A800] text-primary text-xs font-bold hover:bg-[#e09900] transition-colors"
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
